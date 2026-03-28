<?php

namespace Laravel\Ai\Mcp\Transports;

use Laravel\Ai\Mcp\Exceptions\McpException;

class StdioTransport implements McpTransport
{
    /**
     * The subprocess resource.
     *
     * @var resource|null
     */
    protected $process = null;

    /**
     * The subprocess pipe resources [stdin, stdout, stderr].
     *
     * @var array<int, resource>
     */
    protected array $pipes = [];

    /**
     * Create a new stdio transport instance.
     *
     * @param  array  $command  The command to launch the MCP server.
     * @param  array  $env  Environment variables for the subprocess.
     * @param  int  $timeout  Timeout in seconds for read operations.
     */
    public function __construct(
        protected array $command,
        protected array $env = [],
        protected int $timeout = 30,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function open(): void
    {
        if ($this->isOpen()) {
            return;
        }

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'r'], // stdout
            2 => ['pipe', 'r'], // stderr (captured but not used)
        ];

        $env = filled($this->env)
            ? array_merge(getenv(), $this->env)
            : null;

        $this->process = proc_open($this->command, $descriptors, $this->pipes, null, $env);

        if (! is_resource($this->process)) {
            throw McpException::connectionFailed(
                implode(' ', $this->command),
                'Failed to start subprocess.',
            );
        }

        stream_set_blocking($this->pipes[1], false);
    }

    /**
     * {@inheritdoc}
     */
    public function send(array $request): array
    {
        $this->ensureOpen();

        $this->write($request);

        return $this->readResponse($request['id']);
    }

    /**
     * {@inheritdoc}
     */
    public function notify(array $notification): void
    {
        $this->ensureOpen();

        $this->write($notification);
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if (! is_resource($this->process)) {
            return;
        }

        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $this->pipes = [];

        proc_terminate($this->process);

        $deadline = microtime(true) + 2;

        while (microtime(true) < $deadline) {
            $status = proc_get_status($this->process);

            if (! $status['running']) {
                proc_close($this->process);
                $this->process = null;

                return;
            }

            usleep(50_000);
        }

        proc_terminate($this->process, 9); // SIGKILL
        proc_close($this->process);
        $this->process = null;
    }

    /**
     * {@inheritdoc}
     */
    public function isOpen(): bool
    {
        if (! is_resource($this->process)) {
            return false;
        }

        $status = proc_get_status($this->process);

        return $status['running'];
    }

    /**
     * Write a JSON-RPC message to stdin.
     */
    protected function write(array $message): void
    {
        $json = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $written = @fwrite($this->pipes[0], $json."\n");

        if ($written === false) {
            throw McpException::connectionFailed(
                implode(' ', $this->command),
                'Failed to write to process stdin.',
            );
        }

        @fflush($this->pipes[0]);
    }

    /**
     * Read a JSON-RPC response matching the given request ID from stdout.
     */
    protected function readResponse(string|int $requestId): array
    {
        $deadline = microtime(true) + $this->timeout;

        while (microtime(true) < $deadline) {
            $remaining = max(0.1, $deadline - microtime(true));
            $seconds = (int) $remaining;
            $microseconds = (int) (($remaining - $seconds) * 1_000_000);

            $read = [$this->pipes[1]];
            $write = null;
            $except = null;

            $ready = @stream_select($read, $write, $except, $seconds, $microseconds);

            if ($ready === false) {
                break;
            }

            if ($ready === 0) {
                continue;
            }

            $line = @fgets($this->pipes[1]);

            if ($line === false) {
                if (! $this->isOpen()) {
                    $status = proc_get_status($this->process);

                    throw McpException::processExited(
                        implode(' ', $this->command),
                        $status['exitcode'] ?? -1,
                    );
                }

                continue;
            }

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (! is_array($decoded)) {
                continue;
            }

            if (isset($decoded['id']) && $decoded['id'] === $requestId) {
                return $decoded;
            }
        }

        throw McpException::timedOut(
            implode(' ', $this->command),
            $this->timeout,
        );
    }

    /**
     * Ensure the transport is open.
     */
    protected function ensureOpen(): void
    {
        if (! $this->isOpen()) {
            throw McpException::connectionFailed(
                implode(' ', $this->command),
                'Transport is not open.',
            );
        }
    }

    /**
     * Close the transport on destruction.
     */
    public function __destruct()
    {
        $this->close();
    }
}

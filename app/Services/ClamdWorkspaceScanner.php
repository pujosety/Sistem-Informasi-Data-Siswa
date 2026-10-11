<?php

namespace App\Services;

use RuntimeException;
use Throwable;

/** Real clamd zINSTREAM: network-order length chunks, NUL-framed reply. */
class ClamdWorkspaceScanner implements WorkspaceMalwareScanner
{
    public function scan($stream, int $expectedBytes): WorkspaceMalwareScanResult
    {
        $socket = null;
        $total = 0;
        $hash = hash_init('sha256');
        $max = max(1, min(1073741824, (int) config('workspace.scanner.max_bytes', 104857600)));
        $deadline = (hrtime(true) / 1000000000) + max(1, min(120, (float) config('workspace.scanner.timeout_seconds', 30)));
        if (! is_resource($stream) || $expectedBytes < 0 || $expectedBytes > $max) {
            return new WorkspaceMalwareScanResult('unavailable', 'size_limit');
        }
        $metadata = stream_get_meta_data($stream);
        if (! ($metadata['seekable'] ?? false) || ! in_array($metadata['wrapper_type'] ?? '', ['plainfile', 'PHP'], true)) {
            return new WorkspaceMalwareScanResult('unavailable', 'storage');
        }
        try {
            // Operator-configured numeric IP (or Unix socket) only: DNS resolution
            // is not reliably bounded by stream_socket_client's connect timeout.
            $host = (string) config('workspace.scanner.host', '');
            $unix = (string) config('workspace.scanner.unix_socket', '');
            if ($unix !== '') {
                $endpoint = 'unix://'.$unix;
            } elseif (filter_var($host, FILTER_VALIDATE_IP)) {
                $port = max(1, min(65535, (int) config('workspace.scanner.port', 3310)));
                $endpoint = 'tcp://'.(str_contains($host, ':') ? '['.$host.']' : $host).':'.$port;
            } else {
                return new WorkspaceMalwareScanResult('unavailable', 'configuration');
            }
            $socket = @stream_socket_client($endpoint, $errno, $error, min(3, max(0.001, $deadline - (hrtime(true) / 1000000000))), STREAM_CLIENT_CONNECT);
            if (! is_resource($socket)) throw new RuntimeException('connection');
            stream_set_blocking($socket, false);
            $this->write($socket, "zINSTREAM\0", $deadline);
            while (! feof($stream)) {
                $this->remaining($deadline);
                // Supported input is an already-open private local seekable stream.
                // Remote storage must configure bounded adapter HTTP reads too.
                $chunk = fread($stream, 65536);
                $this->remaining($deadline);
                if ($chunk === false || ($chunk === '' && ! feof($stream))) throw new RuntimeException('storage');
                if ($chunk === '') break;
                $total += strlen($chunk);
                if ($total > $max || $total > $expectedBytes) throw new RuntimeException('size_limit');
                hash_update($hash, $chunk);
                $this->write($socket, pack('N', strlen($chunk)).$chunk, $deadline);
            }
            if ($total !== $expectedBytes) throw new RuntimeException('integrity');
            $digest = hash_final($hash);
            $this->write($socket, pack('N', 0), $deadline);
            $reply = '';
            while (! str_contains($reply, "\0")) {
                $this->ready($socket, false, $deadline);
                $part = fread($socket, 1024);
                if ($part === false || ($part === '' && feof($socket))) throw new RuntimeException('protocol');
                $reply .= $part;
                if (strlen($reply) > 4096) throw new RuntimeException('protocol');
            }
            // Never store/return raw engine signatures, internal paths or socket errors.
            if ($reply === "stream: OK\0") return new WorkspaceMalwareScanResult('clean', '', $digest, $total);
            if (preg_match('/\Astream: [^\x00\r\n]+ FOUND\x00\z/D', $reply) === 1) {
                return new WorkspaceMalwareScanResult('infected', 'malware', $digest, $total);
            }
            return new WorkspaceMalwareScanResult('unavailable', 'engine_error', $digest, $total);
        } catch (Throwable $e) {
            $reason = in_array($e->getMessage(), ['connection', 'timeout', 'storage', 'size_limit', 'integrity', 'protocol'], true) ? $e->getMessage() : 'engine_error';
            return new WorkspaceMalwareScanResult('unavailable', $reason, null, $total);
        } finally {
            if (is_resource($socket)) fclose($socket);
        }
    }

    private function remaining(float $deadline): float
    {
        $remaining = $deadline - (hrtime(true) / 1000000000);
        if ($remaining <= 0) throw new RuntimeException('timeout');
        return $remaining;
    }

    private function ready($socket, bool $write, float $deadline): void
    {
        $remaining = $this->remaining($deadline);
        $read = $write ? [] : [$socket];
        $writes = $write ? [$socket] : [];
        $except = [];
        $selected = @stream_select($read, $writes, $except, (int) $remaining, (int) (($remaining - (int) $remaining) * 1000000));
        if ($selected === false) throw new RuntimeException('connection');
        if ($selected === 0) throw new RuntimeException('timeout');
    }

    private function write($socket, string $bytes, float $deadline): void
    {
        $offset = 0;
        while ($offset < strlen($bytes)) {
            $this->ready($socket, true, $deadline);
            $written = @fwrite($socket, substr($bytes, $offset));
            if ($written === false || $written === 0) throw new RuntimeException('connection');
            $offset += $written;
        }
    }
}

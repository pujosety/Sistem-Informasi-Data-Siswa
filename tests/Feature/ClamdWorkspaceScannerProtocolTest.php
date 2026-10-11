<?php

namespace Tests\Feature;

use App\Services\ClamdWorkspaceScanner;
use Tests\TestCase;

/** The socket protocol check deliberately avoids RefreshDatabase and its PDO transaction. */
class ClamdWorkspaceScannerProtocolTest extends TestCase
{
    public function test_real_adapter_uses_instream_framing_and_times_out_without_a_verdict(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Protocol harness requires pcntl in test container.');
        }

        foreach (["stream: OK\0" => 'clean', "stream: Eicar-Test FOUND\0" => 'infected', "stream: failed ERROR\0" => 'unavailable', '' => 'unavailable'] as $reply => $expected) {
            $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
            $this->assertIsResource($server);
            $address = stream_socket_get_name($server, false);
            config([
                'workspace.scanner.host' => '127.0.0.1',
                'workspace.scanner.unix_socket' => '',
                'workspace.scanner.port' => (int) substr(strrchr($address, ':'), 1),
                'workspace.scanner.timeout_seconds' => 1,
            ]);
            $pid = pcntl_fork();
            $this->assertGreaterThanOrEqual(0, $pid);

            if ($pid === 0) {
                $client = stream_socket_accept($server, 3);
                if (! $client) {
                    exit(2);
                }
                stream_set_timeout($client, 3);
                $read = static function (int $length) use ($client): string {
                    $bytes = '';
                    while (strlen($bytes) < $length) {
                        $part = fread($client, $length - strlen($bytes));
                        if ($part === false || $part === '') {
                            exit(3);
                        }
                        $bytes .= $part;
                    }

                    return $bytes;
                };
                if ($read(10) !== "zINSTREAM\0") {
                    exit(4);
                }
                $bytes = '';
                while (($length = unpack('Nlength', $read(4))['length']) !== 0) {
                    $bytes .= $read($length);
                }
                if ($bytes !== 'actual bytes') {
                    exit(5);
                }
                if ($reply === '') {
                    sleep(2);
                } else {
                    fwrite($client, $reply);
                }
                fclose($client);
                fclose($server);
                exit(0);
            }

            fclose($server);
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, 'actual bytes');
            rewind($stream);
            $started = microtime(true);
            try {
                $result = (new ClamdWorkspaceScanner)->scan($stream, 12);
            } finally {
                fclose($stream);
            }
            $elapsed = microtime(true) - $started;
            pcntl_waitpid($pid, $childStatus);
            $this->assertSame(0, pcntl_wexitstatus($childStatus));
            $this->assertSame($expected, $result->status);
            if ($reply === '') {
                $this->assertSame('timeout', $result->reasonCode);
                $this->assertLessThan(1.8, $elapsed);
            }
        }
    }
}

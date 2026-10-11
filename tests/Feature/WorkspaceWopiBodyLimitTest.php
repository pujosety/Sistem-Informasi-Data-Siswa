<?php

namespace Tests\Feature;

use App\Services\WorkspaceWopiBodyReader;
use PHPUnit\Framework\TestCase;

class WorkspaceWopiBodyLimitTest extends TestCase
{
    public function test_reader_stops_after_one_byte_beyond_limit_when_length_is_unavailable(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, str_repeat('x', 100));
        rewind($stream);

        try {
            $result = app(WorkspaceWopiBodyReader::class)->read($stream, 10);

            self::assertNull($result);
            self::assertSame(11, ftell($stream), 'The reader must not consume the whole oversized body.');
        } finally {
            fclose($stream);
        }
    }
}

<?php

namespace App\Services;

class WorkspaceWopiBodyReader
{
    /** Return null when the stream exceeds the byte limit. */
    public function read(mixed $stream, int $maxBytes): ?string
    {
        if (! is_resource($stream) || $maxBytes < 0) {
            return null;
        }

        $contents = '';
        $remaining = $maxBytes + 1;

        while ($remaining > 0 && ! feof($stream)) {
            $chunk = fread($stream, min(8192, $remaining));
            if ($chunk === false || $chunk === '') {
                break;
            }

            $contents .= $chunk;
            $remaining -= strlen($chunk);
        }

        return strlen($contents) > $maxBytes ? null : $contents;
    }
}

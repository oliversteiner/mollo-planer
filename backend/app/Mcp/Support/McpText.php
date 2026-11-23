<?php

namespace App\Mcp\Support;

final class McpText
{
    /**
     * @return array{text: string, offset: int, total_chars: int, truncated: bool, next_offset: ?int}
     */
    public static function chunk(string $text, int $offset, int $maxChars): array
    {
        $total = mb_strlen($text);
        $offset = min($offset, $total);
        $chunk = mb_substr($text, $offset, $maxChars);
        $nextOffset = $offset + mb_strlen($chunk);

        return [
            'text' => $chunk,
            'offset' => $offset,
            'total_chars' => $total,
            'truncated' => $nextOffset < $total,
            'next_offset' => $nextOffset < $total ? $nextOffset : null,
        ];
    }
}

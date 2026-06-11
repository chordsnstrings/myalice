<?php

namespace App\Support;

/**
 * Helpers for turning fetched/pasted text into prompt-sized knowledge snippets.
 */
class Knowledge
{
    /**
     * Split text into ~$size-char chunks on word boundaries.
     *
     * @return list<string>
     */
    public static function chunk(string $text, int $size): array
    {
        $size = max(200, $size);
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $chunks = [];
        $current = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            if (mb_strlen($current) + mb_strlen($word) + 1 > $size && $current !== '') {
                $chunks[] = trim($current);
                $current = '';
            }
            $current .= ' '.$word;
        }
        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }
}

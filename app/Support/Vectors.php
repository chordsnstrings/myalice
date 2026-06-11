<?php

namespace App\Support;

/**
 * Tiny vector helpers for in-PHP semantic ranking over the per-agent snippet set.
 * No extension/vector-DB needed — the candidate set is already small (<= 200).
 */
class Vectors
{
    /**
     * Cosine similarity of two equal-length float vectors. Returns 0.0 when either
     * vector is empty, lengths differ, or a magnitude is zero (safe to rank on).
     *
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        $n = count($a);
        if ($n === 0 || $n !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $magA = 0.0;
        $magB = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $x = (float) $a[$i];
            $y = (float) $b[$i];
            $dot += $x * $y;
            $magA += $x * $x;
            $magB += $y * $y;
        }

        if ($magA <= 0.0 || $magB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($magA) * sqrt($magB));
    }
}

<?php

namespace App\Support\News;

class TitleSimilarity
{
    public function score(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $similarPercent);

        $tokensA = array_values(array_filter(explode(' ', $a)));
        $tokensB = array_values(array_filter(explode(' ', $b)));

        if ($tokensA === [] || $tokensB === []) {
            return (float) $similarPercent;
        }

        $intersection = count(array_intersect($tokensA, $tokensB));
        $union = count(array_unique(array_merge($tokensA, $tokensB)));
        $jaccardPercent = $union > 0 ? ($intersection / $union) * 100 : 0.0;

        return (float) (($similarPercent * 0.7) + ($jaccardPercent * 0.3));
    }

    public function isNearDuplicate(string $a, string $b, float $threshold = 92.0): bool
    {
        return $this->score($a, $b) >= $threshold;
    }
}

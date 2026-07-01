<?php

namespace App\Services;

final class TestCaseGenerationCountResolver
{
    /**
     * @var array<string, int>
     */
    private const NUMBER_WORDS = [
        'one' => 1,
        'two' => 2,
        'three' => 3,
        'four' => 4,
        'five' => 5,
        'six' => 6,
        'seven' => 7,
        'eight' => 8,
        'nine' => 9,
        'ten' => 10,
        'eleven' => 11,
        'twelve' => 12,
        'thirteen' => 13,
        'fourteen' => 14,
        'fifteen' => 15,
        'sixteen' => 16,
        'seventeen' => 17,
        'eighteen' => 18,
        'nineteen' => 19,
        'twenty' => 20,
    ];

    public function maximum(): int
    {
        return max(1, (int) config('ai.generation.test_cases.maximum_count', 20));
    }

    /**
     * @return array{requested: int|null, effective: int|null, was_limited: bool}
     */
    public function resolve(string $prompt): array
    {
        $requested = $this->extractRequestedCount($prompt);

        if ($requested === null) {
            return [
                'requested' => null,
                'effective' => null,
                'was_limited' => false,
            ];
        }

        $effective = min(max($requested, 1), $this->maximum());

        return [
            'requested' => $requested,
            'effective' => $effective,
            'was_limited' => $effective !== $requested,
        ];
    }

    private function extractRequestedCount(string $prompt): ?int
    {
        $wordPattern = implode('|', array_map(
            static fn (string $word): string => preg_quote($word, '/'),
            array_keys(self::NUMBER_WORDS),
        ));
        $countPattern = '(?<count>\d+|'.$wordPattern.')';
        $patterns = [
            '/\b(?:generate|create|produce|write|draft|provide)\s+(?:exactly\s+|only\s+)?'.$countPattern.'\s+test\s+cases?\b/i',
            '/\b'.$countPattern.'\s+test\s+cases?\s+(?:in\s+total|total)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $prompt, $matches) !== 1) {
                continue;
            }

            $count = strtolower((string) $matches['count']);

            return ctype_digit($count)
                ? (int) $count
                : self::NUMBER_WORDS[$count];
        }

        return null;
    }
}

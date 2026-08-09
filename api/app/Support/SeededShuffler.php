<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Deterministic Fisher-Yates shuffle.
 *
 * Uses its own PRNG rather than mt_srand/shuffle for two reasons:
 *
 *  1. No global state. mt_srand seeds a process-wide generator, so an unrelated
 *     rand() call between seeding and shuffling would change the result — a
 *     spectacularly hard bug to find in a queue worker handling many students.
 *  2. Version stability. The engine's internal PRNG may change between PHP
 *     releases; a permutation that silently changes would alter what a student was
 *     shown. Question ASSIGNMENT is persisted for exactly this reason (ADR 001), but
 *     option order is derived on the fly (ADR 003) and so must be reproducible from
 *     the seed forever.
 *
 * splitmix32: small, fast, well-distributed, and trivially portable.
 */
final class SeededShuffler
{
    private int $state;

    public function __construct(int $seed)
    {
        // Fold to 32 bits so behaviour is identical on any platform.
        $this->state = $seed & 0xFFFFFFFF;
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return array<int, T>
     */
    public static function shuffle(array $items, int $seed): array
    {
        $values = array_values($items);
        $rng = new self($seed);

        for ($i = count($values) - 1; $i > 0; $i--) {
            $j = $rng->nextInt($i + 1);
            [$values[$i], $values[$j]] = [$values[$j], $values[$i]];
        }

        return $values;
    }

    /** Seed from any number of parts, so callers never hand-roll string concatenation. */
    public static function seedFrom(int|string ...$parts): int
    {
        return (int) sprintf('%u', crc32(implode(':', $parts)));
    }

    /** Uniform integer in [0, $bound). */
    public function nextInt(int $bound): int
    {
        if ($bound <= 1) {
            return 0;
        }

        // Rejection sampling keeps the distribution uniform rather than modulo-biased.
        $limit = intdiv(0xFFFFFFFF, $bound) * $bound;

        do {
            $value = $this->next();
        } while ($value >= $limit);

        return $value % $bound;
    }

    private function next(): int
    {
        $this->state = ($this->state + 0x9E3779B9) & 0xFFFFFFFF;
        $z = $this->state;
        $z = (($z ^ ($z >> 16)) * 0x21F0AAAD) & 0xFFFFFFFF;
        $z = (($z ^ ($z >> 15)) * 0x735A2D97) & 0xFFFFFFFF;

        return ($z ^ ($z >> 15)) & 0xFFFFFFFF;
    }
}

<?php

declare(strict_types=1);

use App\Support\SeededShuffler;

it('is deterministic for a given seed', function (): void {
    $items = range(1, 50);

    expect(SeededShuffler::shuffle($items, 12345))
        ->toBe(SeededShuffler::shuffle($items, 12345));
});

it('produces different orders for different seeds', function (): void {
    $items = range(1, 50);

    expect(SeededShuffler::shuffle($items, 1))
        ->not->toBe(SeededShuffler::shuffle($items, 2));
});

it('is a permutation — nothing lost, nothing duplicated', function (): void {
    $items = range(1, 300);
    $shuffled = SeededShuffler::shuffle($items, 987654);

    expect($shuffled)->toHaveCount(300)
        ->and(array_unique($shuffled))->toHaveCount(300)
        ->and(collect($shuffled)->sort()->values()->all())->toBe($items);
});

it('is unaffected by unrelated random calls between shuffles', function (): void {
    // The reason this class does not use mt_srand: global PRNG state would make the
    // result depend on whatever else the process happened to do.
    $items = range(1, 20);
    $first = SeededShuffler::shuffle($items, 42);

    random_int(1, 1000);
    mt_rand();

    expect(SeededShuffler::shuffle($items, 42))->toBe($first);
});

it('handles trivial inputs', function (): void {
    expect(SeededShuffler::shuffle([], 1))->toBe([])
        ->and(SeededShuffler::shuffle(['only'], 1))->toBe(['only']);
});

it('builds a stable seed from parts', function (): void {
    expect(SeededShuffler::seedFrom('d7', 42, 0))
        ->toBe(SeededShuffler::seedFrom('d7', 42, 0))
        ->and(SeededShuffler::seedFrom('d7', 42, 0))
        ->not->toBe(SeededShuffler::seedFrom('d7', 42, 1));
});

it('distributes positions roughly evenly across seeds', function (): void {
    // Guards against a biased generator parking one element in the same slot.
    $positions = [];
    foreach (range(1, 400) as $seed) {
        $order = SeededShuffler::shuffle(['a', 'b', 'c', 'd'], $seed);
        $positions[array_search('a', $order, true)] = ($positions[array_search('a', $order, true)] ?? 0) + 1;
    }

    expect($positions)->toHaveCount(4);
    foreach ($positions as $count) {
        expect($count)->toBeGreaterThan(50)->toBeLessThan(150);  // even split is 100
    }
});

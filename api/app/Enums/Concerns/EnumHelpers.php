<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * Shared helpers for backed string enums.
 *
 * Kept as a trait rather than an interface default so that `values()` can be used
 * directly inside validation rules: Rule::in(UserRole::values()).
 */
trait EnumHelpers
{
    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /** @return array<int, string> */
    public static function names(): array
    {
        return array_map(static fn (self $case): string => $case->name, self::cases());
    }

    /** Human readable label; override per enum where the default is not good enough. */
    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    /** @return array<string, string> value => label, for building select inputs */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }

    public function is(self $other): bool
    {
        return $this === $other;
    }

    /** @param  array<int, self>  $others */
    public function in(array $others): bool
    {
        return in_array($this, $others, true);
    }
}

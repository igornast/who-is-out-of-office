<?php

declare(strict_types=1);

namespace App\Tests\_fixtures;

interface FixtureInterface
{
    /**
     * @param array<string, mixed> $attributes
     */
    public static function create(array $attributes = []): array|object;

    /**
     * @return array<string, mixed>
     */
    public static function definitions(): array;
}

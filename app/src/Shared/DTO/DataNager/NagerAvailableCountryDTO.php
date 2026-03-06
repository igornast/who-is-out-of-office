<?php

declare(strict_types=1);

namespace App\Shared\DTO\DataNager;

readonly class NagerAvailableCountryDTO
{
    public function __construct(
        public string $countryCode,
        public string $name,
    ) {
    }

    /**
     * @param array{countryCode: string, name: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['countryCode'],
            $data['name'],
        );
    }
}

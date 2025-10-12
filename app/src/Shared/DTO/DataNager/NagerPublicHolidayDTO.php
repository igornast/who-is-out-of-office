<?php

declare(strict_types=1);

namespace App\Shared\DTO\DataNager;

readonly class NagerPublicHolidayDTO
{
    public function __construct(
        public \DateTimeImmutable $date,
        public string $localName,
        public string $name,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            new \DateTimeImmutable($data['date']),
            $data['localName'],
            $data['name']
        );
    }
}

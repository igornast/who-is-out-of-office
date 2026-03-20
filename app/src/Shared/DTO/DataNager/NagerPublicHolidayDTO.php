<?php

declare(strict_types=1);

namespace App\Shared\DTO\DataNager;

readonly class NagerPublicHolidayDTO
{
    /**
     * @param string[]|null $counties
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public string $localName,
        public string $name,
        public bool $global = true,
        public ?array $counties = null,
    ) {
    }

    /**
     * @param array{date: string, localName: string, name: string, global?: bool, counties?: string[]|null} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            new \DateTimeImmutable($data['date']),
            $data['localName'],
            $data['name'],
            $data['global'] ?? true,
            $data['counties'] ?? null,
        );
    }
}

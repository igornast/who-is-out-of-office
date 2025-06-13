<?php

declare(strict_types=1);

namespace App\Shared\DTO\Holiday;

use App\Infrastructure\Doctrine\Entity\Holiday;
use App\Shared\DTO\DataNager\NagerPublicHolidayDto;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

readonly class PublicHolidayDTO
{
    public function __construct(
        public UuidInterface $id,
        public string $description,
        public \DateTimeImmutable $date,
    ) {
    }

    public static function fromEntity(Holiday $holiday): self
    {
        return new self(
            id: $holiday->id,
            description: $holiday->description,
            date: $holiday->date,
        );
    }

    public static function fromNager(NagerPublicHolidayDto $holiday)
    {
        return new self(
            id: Uuid::uuid4(),
            description: $holiday->localName,
            date: $holiday->date,
        );
    }
}

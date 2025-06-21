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
        public string $countryCode,
        public \DateTimeImmutable $date,
    ) {
    }

    public static function fromEntity(Holiday $holiday): self
    {
        return new self(
            id: $holiday->id,
            description: $holiday->description,
            countryCode: $holiday->holidayCalendar->countryCode,
            date: $holiday->date,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: Uuid::fromString($data['id']),
            description: $data['description'],
            countryCode: $data['country_code'],
            date: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['date']),
        );
    }

    public static function fromNager(NagerPublicHolidayDto $holiday, string $countryCode): self
    {
        return new self(
            id: Uuid::uuid4(),
            description: $holiday->localName,
            countryCode: $countryCode,
            date: $holiday->date
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\DTO\Holiday;

use App\Infrastructure\Doctrine\Entity\Holiday;
use App\Shared\DTO\DataNager\NagerPublicHolidayDTO;
use Ramsey\Uuid\Uuid;

readonly class PublicHolidayDTO
{
    /**
     * @param string[]|null $counties
     */
    public function __construct(
        public string $id,
        public string $description,
        public string $countryCode,
        public \DateTimeImmutable $date,
        public bool $isGlobal = true,
        public ?array $counties = null,
    ) {
    }

    public static function fromEntity(Holiday $holiday): self
    {
        return new self(
            id: $holiday->id->toString(),
            description: $holiday->description,
            countryCode: $holiday->holidayCalendar->countryCode,
            date: $holiday->date,
            isGlobal: $holiday->isGlobal,
            counties: $holiday->counties,
        );
    }

    public static function fromArray(array $data): self
    {
        $counties = isset($data['counties']) && is_string($data['counties'])
            ? json_decode($data['counties'], true, flags: JSON_THROW_ON_ERROR)
            : ($data['counties'] ?? null);

        return new self(
            id: $data['id'],
            description: $data['description'],
            countryCode: $data['country_code'],
            date: \DateTimeImmutable::createFromFormat('Y-m-d', $data['date']),
            isGlobal: (bool) ($data['is_global'] ?? true),
            counties: $counties,
        );
    }

    public static function fromNager(NagerPublicHolidayDTO $holiday, string $countryCode): self
    {
        return new self(
            id: Uuid::uuid4()->toString(),
            description: $holiday->localName,
            countryCode: $countryCode,
            date: $holiday->date,
            isGlobal: $holiday->global,
            counties: $holiday->counties,
        );
    }
}

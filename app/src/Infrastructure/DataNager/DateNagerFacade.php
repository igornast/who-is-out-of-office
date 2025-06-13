<?php

declare(strict_types=1);

namespace App\Infrastructure\DataNager;

use App\Infrastructure\DataNager\Http\DateNagerClient;
use App\Shared\DTO\DataNager\NagerPublicHolidayDto;
use App\Shared\Facade\DateNagerInterface;

final class DateNagerFacade implements DateNagerInterface
{
    public function __construct(
        private readonly DateNagerClient $client,
    ) {
    }

    /**
     * @return NagerPublicHolidayDto[]
     */
    public function getHolidaysForCountry(string $country, int $year): array
    {
        $data = $this->client->fetchHolidays($country, $year);

        return array_map(fn (array $item) => NagerPublicHolidayDto::fromArray($item), $data);
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\DataNager;

use App\Infrastructure\DataNager\Http\DateNagerClient;
use App\Shared\DTO\DataNager\NagerAvailableCountryDTO;
use App\Shared\DTO\DataNager\NagerPublicHolidayDTO;
use App\Shared\Facade\DateNagerInterface;

final class DateNagerFacade implements DateNagerInterface
{
    public function __construct(
        private readonly DateNagerClient $client,
    ) {
    }

    /**
     * @return NagerPublicHolidayDTO[]
     */
    public function getHolidaysForCountry(string $country, int $year): array
    {
        $data = $this->client->fetchHolidays($country, $year);

        return array_map(fn (array $item) => NagerPublicHolidayDTO::fromArray($item), $data);
    }

    /**
     * @return NagerAvailableCountryDTO[]
     */
    public function getAvailableCountries(): array
    {
        $data = $this->client->fetchAvailableCountries();

        return array_map(fn (array $item) => NagerAvailableCountryDTO::fromArray($item), $data);
    }
}

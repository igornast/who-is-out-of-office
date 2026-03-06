<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\DataNager\NagerAvailableCountryDTO;
use App\Shared\DTO\DataNager\NagerPublicHolidayDTO;

interface DateNagerInterface
{
    /**
     * @return NagerPublicHolidayDTO[]
     */
    public function getHolidaysForCountry(string $country, int $year): array;

    /**
     * @return NagerAvailableCountryDTO[]
     */
    public function getAvailableCountries(): array;
}

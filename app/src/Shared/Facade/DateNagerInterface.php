<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\DataNager\NagerPublicHolidayDto;

interface DateNagerInterface
{
    /**
     * @return NagerPublicHolidayDto[]
     */
    public function getHolidaysForCountry(string $country, int $year): array;
}

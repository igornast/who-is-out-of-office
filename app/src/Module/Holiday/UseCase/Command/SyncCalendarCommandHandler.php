<?php

declare(strict_types=1);

namespace App\Module\Holiday\UseCase\Command;

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Module\Holiday\Service\HolidayImportReconciler;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\Facade\DateNagerInterface;
use Psr\Clock\ClockInterface;

class SyncCalendarCommandHandler
{
    public function __construct(
        private readonly DateNagerInterface $dateNager,
        private readonly PublicHolidayCalendarRepositoryInterface $calendarRepository,
        private readonly HolidayImportReconciler $reconciler,
        private readonly ClockInterface $clock,
    ) {
    }

    public function handle(string $countryCode, string $countryName, int $year): void
    {
        $holidays = $this->dateNager->getHolidaysForCountry($countryCode, $year);
        $calendarDTO = PublicHolidayCalendarDTO::createFromNager($countryCode, $countryName, $holidays);
        $existing = $this->calendarRepository->findHolidaysForYear($countryCode, $year);
        $reconciliation = $this->reconciler->reconcile($existing, $calendarDTO->holidays, $this->clock->now());
        $this->calendarRepository->applyHolidayReconciliation($calendarDTO, $year, $reconciliation);
    }
}

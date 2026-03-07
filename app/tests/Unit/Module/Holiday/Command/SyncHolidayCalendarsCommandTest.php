<?php

declare(strict_types=1);

use App\Module\Holiday\Command\SyncHolidayCalendarsCommand;
use App\Shared\Facade\HolidayFacadeInterface;
use Symfony\Component\Console\Command\Command;

beforeEach(function (): void {
    $this->holidayFacade = mock(HolidayFacadeInterface::class);

    $this->command = new SyncHolidayCalendarsCommand(holidayFacade: $this->holidayFacade);
});

it('syncs all active calendars for current year', function () {
    $currentYear = (int) (new DateTimeImmutable())->format('Y');

    $this->holidayFacade
        ->expects('syncAllActiveCalendars')
        ->once()
        ->with($currentYear);

    $result = ($this->command)();

    expect($result)->toBe(Command::SUCCESS);
});

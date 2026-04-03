<?php

declare(strict_types=1);

use App\Module\Holiday\Command\ImportPublicHolidaysCommand;
use App\Shared\Facade\HolidayFacadeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function (): void {
    $this->holidayFacade = mock(HolidayFacadeInterface::class);

    $this->command = new ImportPublicHolidaysCommand(holidayFacade: $this->holidayFacade);
    $this->tester = new CommandTester($this->command);
});

it('imports holidays with uppercased country code and capitalized name', function () {
    $this->holidayFacade
        ->expects('syncCalendar')
        ->once()
        ->with('DE', 'Germany', 2025);

    $this->tester->execute([
        'country-code' => 'de',
        'country-name' => 'germany',
        'year' => '2025',
    ]);

    expect($this->tester->getStatusCode())->toBe(Command::SUCCESS);
});

it('passes year as integer', function () {
    $this->holidayFacade
        ->expects('syncCalendar')
        ->once()
        ->with('PL', 'Poland', 2026);

    $this->tester->execute([
        'country-code' => 'PL',
        'country-name' => 'Poland',
        'year' => '2026',
    ]);

    expect($this->tester->getStatusCode())->toBe(Command::SUCCESS);
});

<?php

declare(strict_types=1);

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Module\Holiday\UseCase\Command\SyncAllActiveCalendarsCommandHandler;
use App\Module\Holiday\UseCase\Command\SyncCalendarCommandHandler;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayCalendarDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(PublicHolidayCalendarRepositoryInterface::class);
    $this->syncHandler = mock(SyncCalendarCommandHandler::class);

    $this->handler = new SyncAllActiveCalendarsCommandHandler(
        calendarRepository: $this->repository,
        syncCalendarHandler: $this->syncHandler
    );
});

it('syncs only active calendars', function () {
    $activeCalendar = PublicHolidayCalendarDTOFixture::create([
        'countryCode' => 'US',
        'countryName' => 'United States',
        'isActive' => true,
    ]);
    $inactiveCalendar = PublicHolidayCalendarDTOFixture::create([
        'countryCode' => 'GB',
        'countryName' => 'United Kingdom',
        'isActive' => false,
    ]);

    $this->repository
        ->expects('findAll')
        ->once()
        ->andReturn([$activeCalendar, $inactiveCalendar]);

    $this->syncHandler
        ->expects('handle')
        ->once()
        ->with('US', 'United States', 2026);

    $this->handler->handle(2026);
});

it('syncs all active calendars', function () {
    $calendar1 = PublicHolidayCalendarDTOFixture::create([
        'countryCode' => 'US',
        'countryName' => 'United States',
        'isActive' => true,
    ]);
    $calendar2 = PublicHolidayCalendarDTOFixture::create([
        'countryCode' => 'NG',
        'countryName' => 'Nigeria',
        'isActive' => true,
    ]);

    $this->repository
        ->expects('findAll')
        ->once()
        ->andReturn([$calendar1, $calendar2]);

    $this->syncHandler
        ->expects('handle')
        ->twice();

    $this->handler->handle(2026);
});

it('does nothing when no calendars exist', function () {
    $this->repository
        ->expects('findAll')
        ->once()
        ->andReturn([]);

    $this->syncHandler
        ->shouldNotReceive('handle');

    $this->handler->handle(2026);
});

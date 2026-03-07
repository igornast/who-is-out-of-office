<?php

declare(strict_types=1);

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Module\Holiday\UseCase\Command\ToggleCalendarActiveCommandHandler;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->repository = mock(PublicHolidayCalendarRepositoryInterface::class);

    $this->handler = new ToggleCalendarActiveCommandHandler(
        calendarRepository: $this->repository
    );
});

it('activates a calendar', function () {
    $calendarId = Uuid::uuid4()->toString();

    $this->repository
        ->expects('updateActive')
        ->once()
        ->with($calendarId, true);

    $this->handler->handle($calendarId, true);
});

it('deactivates a calendar', function () {
    $calendarId = Uuid::uuid4()->toString();

    $this->repository
        ->expects('updateActive')
        ->once()
        ->with($calendarId, false);

    $this->handler->handle($calendarId, false);
});

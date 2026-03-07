<?php

declare(strict_types=1);

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Module\Holiday\UseCase\Command\DeleteCalendarCommandHandler;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->repository = mock(PublicHolidayCalendarRepositoryInterface::class);

    $this->handler = new DeleteCalendarCommandHandler(
        calendarRepository: $this->repository
    );
});

it('deletes calendar when no users are assigned', function () {
    $calendarId = Uuid::uuid4()->toString();

    $this->repository
        ->expects('hasAssignedUsers')
        ->once()
        ->with($calendarId)
        ->andReturn(false);

    $this->repository
        ->expects('delete')
        ->once()
        ->with($calendarId);

    $this->handler->handle($calendarId);
});

it('throws exception when users are assigned to calendar', function () {
    $calendarId = Uuid::uuid4()->toString();

    $this->repository
        ->expects('hasAssignedUsers')
        ->once()
        ->with($calendarId)
        ->andReturn(true);

    $this->repository
        ->shouldNotReceive('delete');

    $this->handler->handle($calendarId);
})->throws(RuntimeException::class);

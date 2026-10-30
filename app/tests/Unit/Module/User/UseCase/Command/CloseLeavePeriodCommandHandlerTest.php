<?php

declare(strict_types=1);

use App\Module\User\Repository\LeavePeriodRepositoryInterface;
use App\Module\User\UseCase\Command\CloseLeavePeriodCommandHandler;
use App\Tests\_fixtures\Shared\DTO\LeavePeriodDTOFixture;

beforeEach(function (): void {
    $this->leavePeriodRepository = mock(LeavePeriodRepositoryInterface::class);
    $this->handler = new CloseLeavePeriodCommandHandler(
        leavePeriodRepository: $this->leavePeriodRepository,
    );
});

it('closes the open period with the given closing balance', function () {
    $period = LeavePeriodDTOFixture::create(['userId' => 'user-1']);

    $this->leavePeriodRepository->expects('findOpenForUser')->once()->with('user-1')->andReturn($period);
    $this->leavePeriodRepository
        ->expects('close')
        ->once()
        ->withArgs(fn (string $id, int $closingBalance, DateTimeImmutable $closedAt): bool => $id === $period->id
            && 12 === $closingBalance
            && $closedAt <= new DateTimeImmutable());

    $this->handler->handle('user-1', 12);
});

it('does nothing when the user has no open period', function () {
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->with('user-1')->andReturn(null);
    $this->leavePeriodRepository->expects('close')->never();

    $this->handler->handle('user-1', 12);
});

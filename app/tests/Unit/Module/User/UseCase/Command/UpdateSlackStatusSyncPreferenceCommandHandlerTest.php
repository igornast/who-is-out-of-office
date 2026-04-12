<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\UpdateSlackStatusSyncPreferenceCommandHandler;

beforeEach(function (): void {
    $this->repository = mock(UserRepositoryInterface::class);
    $this->handler = new UpdateSlackStatusSyncPreferenceCommandHandler($this->repository);
});

it('returns true when the repository updates the row', function () {
    $this->repository
        ->expects('updateSlackStatusSyncEnabled')
        ->once()
        ->with('user-123', false)
        ->andReturn(true);

    expect($this->handler->handle('user-123', false))->toBeTrue();
});

it('returns false when the repository reports no row was updated', function () {
    $this->repository
        ->expects('updateSlackStatusSyncEnabled')
        ->once()
        ->with('user-123', true)
        ->andReturn(false);

    expect($this->handler->handle('user-123', true))->toBeFalse();
});

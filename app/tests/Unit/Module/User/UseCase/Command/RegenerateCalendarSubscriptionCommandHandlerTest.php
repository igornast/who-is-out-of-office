<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\RegenerateCalendarSubscriptionCommandHandler;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);

    $this->handler = new RegenerateCalendarSubscriptionCommandHandler(
        userRepository: $this->userRepository,
    );
});

it('persists a new ical hash salt to the repository', function () {
    $this->userRepository
        ->expects('updateIcalHashSalt')
        ->once()
        ->withArgs(fn (string $userId, string $salt): bool => 'user-1' === $userId
                && 32 === strlen($salt)
                && ctype_xdigit($salt));

    $this->handler->handle('user-1');
});

it('generates a different salt on each invocation', function () {
    $salts = [];

    $this->userRepository
        ->expects('updateIcalHashSalt')
        ->twice()
        ->withArgs(function (string $userId, string $salt) use (&$salts): bool {
            $salts[] = $salt;

            return true;
        });

    $this->handler->handle('user-1');
    $this->handler->handle('user-1');

    expect($salts[0])->not()->toBe($salts[1]);
});

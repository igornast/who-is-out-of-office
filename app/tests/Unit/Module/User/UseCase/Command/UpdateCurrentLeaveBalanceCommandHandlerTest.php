<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\UpdateCurrentLeaveBalanceCommandHandler;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);

    $this->handler = new UpdateCurrentLeaveBalanceCommandHandler(userRepository: $this->userRepository);
});

it('increases leave balance by given number', function () {
    $userDTO = UserDTOFixture::create([
        'id' => 'user-1',
        'currentLeaveBalance' => 20,
    ]);

    $this->userRepository
        ->expects('findOneById')
        ->once()
        ->with('user-1')
        ->andReturn($userDTO);

    $this->userRepository
        ->expects('update')
        ->once()
        ->with($userDTO);

    $this->handler->handle('user-1', 5);

    expect($userDTO->currentLeaveBalance)->toBe(25);
});

it('decreases leave balance with negative number', function () {
    $userDTO = UserDTOFixture::create([
        'id' => 'user-2',
        'currentLeaveBalance' => 20,
    ]);

    $this->userRepository
        ->expects('findOneById')
        ->once()
        ->with('user-2')
        ->andReturn($userDTO);

    $this->userRepository
        ->expects('update')
        ->once()
        ->with($userDTO);

    $this->handler->handle('user-2', -3);

    expect($userDTO->currentLeaveBalance)->toBe(17);
});

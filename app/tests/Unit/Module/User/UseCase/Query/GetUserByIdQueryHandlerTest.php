<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\Module\User\UseCase\Query\GetUserByIdQueryHandler;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepository::class);

    $this->handler = new GetUserByIdQueryHandler(userRepository: $this->userRepository);
});

it('finds user by id', function () {
    $userId = '123e4567-e89b-12d3-a456-426614174000';
    $expectedUser = UserDTOFixture::create([
        'id' => $userId,
        'firstName' => 'John',
        'lastName' => 'Doe',
    ]);

    $this->userRepository
        ->expects('findOneById')
        ->once()
        ->with($userId)
        ->andReturn($expectedUser);

    $result = $this->handler->handle($userId);

    expect($result)->toBe($expectedUser)
        ->and($result->id)->toBe($userId);
});

it('returns null when user not found', function () {
    $userId = '999e4567-e89b-12d3-a456-426614174000';

    $this->userRepository
        ->expects('findOneById')
        ->once()
        ->with($userId)
        ->andReturn(null);

    $result = $this->handler->handle($userId);

    expect($result)->toBeNull();
});

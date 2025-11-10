<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Query\GetMyTeamUsersQueryHandler;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);

    $this->handler = new GetMyTeamUsersQueryHandler(userRepository: $this->userRepository);
});

it('returns all users except the requesting user', function () {
    $currentUserId = '123';
    $user1 = UserDTOFixture::create(['id' => $currentUserId, 'firstName' => 'Current']);
    $user2 = UserDTOFixture::create(['id' => '456', 'firstName' => 'John']);
    $user3 = UserDTOFixture::create(['id' => '789', 'firstName' => 'Jane']);

    $this->userRepository
        ->expects('findAll')
        ->once()
        ->andReturn([$user1, $user2, $user3]);

    $result = $this->handler->handle($currentUserId);

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result)->toContain($user2)
        ->and($result)->toContain($user3)
        ->and($result)->not->toContain($user1);
});

it('returns empty array when only requesting user exists', function () {
    $currentUserId = '123';
    $currentUser = UserDTOFixture::create(['id' => $currentUserId]);

    $this->userRepository
        ->expects('findAll')
        ->once()
        ->andReturn([$currentUser]);

    $result = $this->handler->handle($currentUserId);

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

it('returns all users when requesting user does not exist in list', function () {
    $currentUserId = 'non-existent-id';
    $user1 = UserDTOFixture::create(['id' => '123', 'firstName' => 'John']);
    $user2 = UserDTOFixture::create(['id' => '456', 'firstName' => 'Jane']);

    $this->userRepository
        ->expects('findAll')
        ->once()
        ->andReturn([$user1, $user2]);

    $result = $this->handler->handle($currentUserId);

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result)->toContain($user1)
        ->and($result)->toContain($user2);
});

it('returns empty array when no users exist', function () {
    $this->userRepository
        ->expects('findAll')
        ->once()
        ->andReturn([]);

    $result = $this->handler->handle('any-user-id');

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

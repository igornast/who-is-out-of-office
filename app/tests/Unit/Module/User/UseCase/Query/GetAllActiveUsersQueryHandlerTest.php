<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Query\GetAllActiveUsersQueryHandler;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

it('returns all active users from repository', function () {
    $user1 = UserDTOFixture::create(['firstName' => 'Alice', 'lastName' => 'Smith']);
    $user2 = UserDTOFixture::create(['firstName' => 'Bob', 'lastName' => 'Jones']);

    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findAllActive')
        ->once()
        ->andReturn([$user1, $user2]);

    $handler = new GetAllActiveUsersQueryHandler($repository);
    $result = $handler->handle();

    expect($result)->toHaveCount(2)
        ->and($result[0]->firstName)->toBe('Alice')
        ->and($result[1]->firstName)->toBe('Bob');
});

it('returns empty array when no active users exist', function () {
    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findAllActive')
        ->once()
        ->andReturn([]);

    $handler = new GetAllActiveUsersQueryHandler($repository);
    $result = $handler->handle();

    expect($result)->toBeEmpty();
});

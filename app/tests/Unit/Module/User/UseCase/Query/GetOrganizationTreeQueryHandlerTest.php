<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Query\GetOrganizationTreeQueryHandler;
use App\Shared\DTO\OrganizationNodeDTO;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);

    $this->handler = new GetOrganizationTreeQueryHandler(userRepository: $this->userRepository);
});

it('returns an empty tree when there are no active users', function () {
    $this->userRepository
        ->expects('findAllActive')
        ->once()
        ->andReturn([]);

    $result = $this->handler->handle();

    expect($result)->toBeArray()->toBeEmpty();
});

it('returns every user as a root node when none have a manager', function () {
    $user1 = UserDTOFixture::create(['id' => 'user-1', 'firstName' => 'Bob', 'lastName' => 'Stone', 'managerId' => null]);
    $user2 = UserDTOFixture::create(['id' => 'user-2', 'firstName' => 'Alice', 'lastName' => 'Stone', 'managerId' => null]);

    $this->userRepository
        ->expects('findAllActive')
        ->once()
        ->andReturn([$user1, $user2]);

    $result = $this->handler->handle();

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(OrganizationNodeDTO::class)
        ->and($result[0]->user)->toBe($user2)
        ->and($result[0]->children)->toBeEmpty()
        ->and($result[1]->user)->toBe($user1)
        ->and($result[1]->children)->toBeEmpty();
});

it('nests users under their manager', function () {
    $manager = UserDTOFixture::create(['id' => 'manager-1', 'firstName' => 'Manager', 'managerId' => null]);
    $report1 = UserDTOFixture::create(['id' => 'user-1', 'firstName' => 'Zoe', 'managerId' => 'manager-1']);
    $report2 = UserDTOFixture::create(['id' => 'user-2', 'firstName' => 'Amy', 'managerId' => 'manager-1']);

    $this->userRepository
        ->expects('findAllActive')
        ->once()
        ->andReturn([$manager, $report1, $report2]);

    $result = $this->handler->handle();

    expect($result)->toHaveCount(1)
        ->and($result[0]->user)->toBe($manager)
        ->and($result[0]->children)->toHaveCount(2)
        ->and($result[0]->children[0]->user)->toBe($report2)
        ->and($result[0]->children[1]->user)->toBe($report1);
});

it('builds a multi-level hierarchy', function () {
    $ceo = UserDTOFixture::create(['id' => 'ceo', 'firstName' => 'Ceo', 'managerId' => null]);
    $manager = UserDTOFixture::create(['id' => 'manager', 'firstName' => 'Manager', 'managerId' => 'ceo']);
    $report = UserDTOFixture::create(['id' => 'report', 'firstName' => 'Report', 'managerId' => 'manager']);

    $this->userRepository
        ->expects('findAllActive')
        ->once()
        ->andReturn([$ceo, $manager, $report]);

    $result = $this->handler->handle();

    expect($result)->toHaveCount(1)
        ->and($result[0]->user)->toBe($ceo)
        ->and($result[0]->children)->toHaveCount(1)
        ->and($result[0]->children[0]->user)->toBe($manager)
        ->and($result[0]->children[0]->children)->toHaveCount(1)
        ->and($result[0]->children[0]->children[0]->user)->toBe($report);
});

it('treats a user as a root when their manager is not among active users', function () {
    $orphan = UserDTOFixture::create(['id' => 'user-1', 'firstName' => 'Orphan', 'managerId' => 'missing-manager']);

    $this->userRepository
        ->expects('findAllActive')
        ->once()
        ->andReturn([$orphan]);

    $result = $this->handler->handle();

    expect($result)->toHaveCount(1)
        ->and($result[0]->user)->toBe($orphan)
        ->and($result[0]->children)->toBeEmpty();
});

it('sorts root nodes by first name then last name, case-insensitively', function () {
    $a = UserDTOFixture::create(['id' => 'a', 'firstName' => 'beta', 'lastName' => 'Young', 'managerId' => null]);
    $b = UserDTOFixture::create(['id' => 'b', 'firstName' => 'Alpha', 'lastName' => 'Zimmer', 'managerId' => null]);
    $c = UserDTOFixture::create(['id' => 'c', 'firstName' => 'alpha', 'lastName' => 'Adams', 'managerId' => null]);

    $this->userRepository
        ->expects('findAllActive')
        ->once()
        ->andReturn([$a, $b, $c]);

    $result = $this->handler->handle();

    expect($result)->toHaveCount(3)
        ->and($result[0]->user)->toBe($c)
        ->and($result[1]->user)->toBe($b)
        ->and($result[2]->user)->toBe($a);
});

it('sorts children within a node', function () {
    $manager = UserDTOFixture::create(['id' => 'manager', 'firstName' => 'Manager', 'managerId' => null]);
    $charlie = UserDTOFixture::create(['id' => 'c', 'firstName' => 'Charlie', 'lastName' => 'Smith', 'managerId' => 'manager']);
    $anna = UserDTOFixture::create(['id' => 'a', 'firstName' => 'Anna', 'lastName' => 'Brown', 'managerId' => 'manager']);
    $annaCarter = UserDTOFixture::create(['id' => 'a2', 'firstName' => 'Anna', 'lastName' => 'Carter', 'managerId' => 'manager']);

    $this->userRepository
        ->expects('findAllActive')
        ->once()
        ->andReturn([$manager, $charlie, $anna, $annaCarter]);

    $result = $this->handler->handle();

    expect($result[0]->children)->toHaveCount(3)
        ->and($result[0]->children[0]->user)->toBe($anna)
        ->and($result[0]->children[1]->user)->toBe($annaCarter)
        ->and($result[0]->children[2]->user)->toBe($charlie);
});

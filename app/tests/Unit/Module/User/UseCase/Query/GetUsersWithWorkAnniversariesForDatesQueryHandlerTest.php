<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Query\GetUsersWithWorkAnniversariesForDatesQueryHandler;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);

    $this->handler = new GetUsersWithWorkAnniversariesForDatesQueryHandler(userRepository: $this->userRepository);
});

it('finds users with work anniversaries between given dates', function () {
    $startDate = new DateTimeImmutable('2025-01-01');
    $endDate = new DateTimeImmutable('2025-01-31');
    $expectedUsers = [
        UserDTOFixture::create(['firstName' => 'John', 'lastName' => 'Doe']),
        UserDTOFixture::create(['firstName' => 'Jane', 'lastName' => 'Smith']),
    ];

    $this->userRepository
        ->expects('findUsersWithIncomingWorkAnniversaries')
        ->once()
        ->with($startDate, $endDate)
        ->andReturn($expectedUsers);

    $result = $this->handler->handle($startDate, $endDate);

    expect($result)->toBe($expectedUsers)
        ->and($result)->toHaveCount(2);
});

it('returns empty array when no users have work anniversaries in date range', function () {
    $startDate = new DateTimeImmutable('2025-01-01');
    $endDate = new DateTimeImmutable('2025-01-31');

    $this->userRepository
        ->expects('findUsersWithIncomingWorkAnniversaries')
        ->once()
        ->with($startDate, $endDate)
        ->andReturn([]);

    $result = $this->handler->handle($startDate, $endDate);

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

it('handles single day date range', function () {
    $date = new DateTimeImmutable('2025-01-15');
    $expectedUsers = [
        UserDTOFixture::create(['firstName' => 'John', 'lastName' => 'Doe']),
    ];

    $this->userRepository
        ->expects('findUsersWithIncomingWorkAnniversaries')
        ->once()
        ->with($date, $date)
        ->andReturn($expectedUsers);

    $result = $this->handler->handle($date, $date);

    expect($result)->toBe($expectedUsers)
        ->and($result)->toHaveCount(1);
});

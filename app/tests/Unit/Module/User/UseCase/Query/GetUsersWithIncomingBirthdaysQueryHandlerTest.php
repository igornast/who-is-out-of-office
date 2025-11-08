<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Query\GetUsersWithIncomingBirthdaysQueryHandler;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);

    $this->handler = new GetUsersWithIncomingBirthdaysQueryHandler(userRepository: $this->userRepository);
});

it('finds users with incoming birthdays using default 20 days period', function () {
    $expectedUsers = [
        UserDTOFixture::create(['firstName' => 'John', 'lastName' => 'Doe']),
        UserDTOFixture::create(['firstName' => 'Jane', 'lastName' => 'Smith']),
    ];

    $this->userRepository
        ->expects('findUsersWithIncomingBirthdays')
        ->once()
        ->andReturn($expectedUsers);

    $result = $this->handler->handle();

    expect($result)->toBe($expectedUsers)
        ->and($result)->toHaveCount(2);
});

it('finds users with incoming birthdays using custom end date', function () {
    $customEnd = new DateTimeImmutable('+ 30 days');
    $expectedUsers = [
        UserDTOFixture::create(['firstName' => 'John', 'lastName' => 'Doe']),
    ];

    $this->userRepository
        ->expects('findUsersWithIncomingBirthdays')
        ->once()
        ->andReturn($expectedUsers);

    $result = $this->handler->handle($customEnd);

    expect($result)->toBe($expectedUsers)
        ->and($result)->toHaveCount(1);
});

it('returns empty array when no users have upcoming birthdays', function () {
    $this->userRepository
        ->expects('findUsersWithIncomingBirthdays')
        ->once()
        ->andReturn([]);

    $result = $this->handler->handle();

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

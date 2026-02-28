<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Query\GetDirectReportsQueryHandler;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);

    $this->handler = new GetDirectReportsQueryHandler(userRepository: $this->userRepository);
});

it('returns direct reports for a manager', function () {
    $managerId = 'manager-1';
    $report1 = UserDTOFixture::create(['id' => 'user-1', 'firstName' => 'John', 'managerId' => $managerId]);
    $report2 = UserDTOFixture::create(['id' => 'user-2', 'firstName' => 'Jane', 'managerId' => $managerId]);

    $this->userRepository
        ->expects('findByManagerId')
        ->once()
        ->with($managerId)
        ->andReturn([$report1, $report2]);

    $result = $this->handler->handle($managerId);

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result)->toContain($report1)
        ->and($result)->toContain($report2);
});

it('returns empty array when manager has no direct reports', function () {
    $managerId = 'manager-no-reports';

    $this->userRepository
        ->expects('findByManagerId')
        ->once()
        ->with($managerId)
        ->andReturn([]);

    $result = $this->handler->handle($managerId);

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

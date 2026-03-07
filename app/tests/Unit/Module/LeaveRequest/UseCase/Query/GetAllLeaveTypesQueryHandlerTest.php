<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestTypeRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\GetAllLeaveTypesQueryHandler;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestTypeDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestTypeRepositoryInterface::class);

    $this->handler = new GetAllLeaveTypesQueryHandler(repository: $this->repository);
});

it('returns all active leave types', function () {
    $expected = [
        LeaveRequestTypeDTOFixture::create(['name' => 'Annual Leave']),
        LeaveRequestTypeDTOFixture::create(['name' => 'Sick Leave']),
    ];

    $this->repository
        ->expects('findAllActive')
        ->once()
        ->andReturn($expected);

    $result = $this->handler->handle();

    expect($result)->toBe($expected)
        ->and($result)->toHaveCount(2);
});

it('returns empty array when no active leave types', function () {
    $this->repository
        ->expects('findAllActive')
        ->once()
        ->andReturn([]);

    $result = $this->handler->handle();

    expect($result)->toBe([]);
});

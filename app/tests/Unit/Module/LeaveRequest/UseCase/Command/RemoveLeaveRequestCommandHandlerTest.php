<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Command\RemoveLeaveRequestCommandHandler;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);

    $this->handler = new RemoveLeaveRequestCommandHandler(leaveRequestRepository: $this->repository);
});

it('removes leave request', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('123e4567-e89b-12d3-a456-426614174000'),
    ]);

    $this->repository
        ->expects('delete')
        ->once()
        ->with($leaveRequestDTO);

    $this->handler->handle($leaveRequestDTO);
});

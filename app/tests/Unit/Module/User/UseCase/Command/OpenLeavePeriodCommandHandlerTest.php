<?php

declare(strict_types=1);

use App\Module\User\Repository\LeavePeriodRepositoryInterface;
use App\Module\User\UseCase\Command\OpenLeavePeriodCommandHandler;
use App\Shared\DTO\Balance\LeavePeriodDTO;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->leavePeriodRepository = mock(LeavePeriodRepositoryInterface::class);
    $this->handler = new OpenLeavePeriodCommandHandler(
        leavePeriodRepository: $this->leavePeriodRepository,
    );
});

it('opens a period spanning one year from the given start date', function () {
    $start = new DateTimeImmutable('-1 month');
    $end = $start->modify('+1 year');
    $userDTO = UserDTOFixture::create(['annualLeaveAllowance' => 24]);

    $this->leavePeriodRepository
        ->expects('add')
        ->once()
        ->withArgs(fn (LeavePeriodDTO $dto): bool => $dto->userId === $userDTO->id
                && 24 === $dto->allowanceGranted
                && 7 === $dto->openingBalance
                && $dto->startDate->format('Y-m-d') === $start->format('Y-m-d')
                && $dto->endDate->format('Y-m-d') === $end->format('Y-m-d')
                && null === $dto->closedAt);

    $result = $this->handler->handle($userDTO, $start, $end, 7);

    expect($result->openingBalance)->toBe(7)
        ->and($result->allowanceGranted)->toBe(24)
        ->and($result->isOpen())->toBeTrue();
});

it('preserves an explicit leap-day period end', function (): void {
    $start = new DateTimeImmutable('2027-03-01');
    $end = new DateTimeImmutable('2028-02-29');
    $userDTO = UserDTOFixture::create();

    $this->leavePeriodRepository->expects('add')->once()->withArgs(
        fn (LeavePeriodDTO $dto): bool => '2027-03-01' === $dto->startDate->format('Y-m-d')
            && '2028-02-29' === $dto->endDate->format('Y-m-d'),
    );

    $this->handler->handle($userDTO, $start, $end, 7);
});

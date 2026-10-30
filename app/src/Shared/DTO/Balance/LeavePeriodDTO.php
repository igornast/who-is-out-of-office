<?php

declare(strict_types=1);

namespace App\Shared\DTO\Balance;

use App\Infrastructure\Doctrine\Entity\LeavePeriod;

class LeavePeriodDTO
{
    public function __construct(
        public string $id,
        public string $userId,
        public int $allowanceGranted,
        public int $openingBalance,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
        public ?int $closingBalance = null,
        public ?\DateTimeImmutable $closedAt = null,
    ) {
    }

    public static function fromEntity(LeavePeriod $leavePeriod): self
    {
        return new self(
            id: $leavePeriod->id->toString(),
            userId: $leavePeriod->user->id->toString(),
            allowanceGranted: $leavePeriod->allowanceGranted,
            openingBalance: $leavePeriod->openingBalance,
            startDate: $leavePeriod->startDate,
            endDate: $leavePeriod->endDate,
            closingBalance: $leavePeriod->closingBalance,
            closedAt: $leavePeriod->closedAt,
        );
    }

    public function isOpen(): bool
    {
        return null === $this->closedAt;
    }
}

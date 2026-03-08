<?php

declare(strict_types=1);

namespace App\Module\Admin\DTO;

use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use Symfony\Component\Validator\Constraints as Assert;

class NewLeaveRequestDTO
{
    public function __construct(
        #[Assert\NotBlank]
        public ?\DateTimeImmutable $startDate = null,
        #[Assert\NotBlank]
        public ?\DateTimeImmutable $endDate = null,
        #[Assert\NotBlank]
        public ?LeaveRequestType $leaveType = null,
    ) {
    }

    /**
     * A virtual field for the form transformer.
     * NewLeaveRequestFormType::dateRange.
     *
     * @param array{}|array{'start': \DateTimeImmutable, 'end': \DateTimeImmutable} $dates
     */
    public function setDateRange(array $dates): void
    {
        if (empty($dates)) {
            return;
        }

        $this->startDate = $dates['start'];
        $this->endDate = $dates['end'];
    }

    /**
     * @return array{'start': \DateTimeImmutable|null, 'end': \DateTimeImmutable|null}
     */
    public function getDateRange(): array
    {
        return ['start' => $this->startDate, 'end' => $this->endDate];
    }
}

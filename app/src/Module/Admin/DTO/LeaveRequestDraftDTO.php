<?php

declare(strict_types=1);

namespace App\Module\Admin\DTO;

use App\Shared\Enum\LeaveRequestTypeEnum;
use Symfony\Component\Validator\Constraints as Assert;

class LeaveRequestDraftDTO
{
    public function __construct(
        #[Assert\NotBlank]
        public ?LeaveRequestTypeEnum $leaveType = null,
        #[Assert\NotBlank]
        public ?\DateTimeImmutable $startDate = null,
        #[Assert\NotBlank]
        public ?\DateTimeImmutable $endDate = null,
    ) {
    }

    /**
     * A virtual field for the form transformer.
     *
     * @param array{'start': \DateTimeImmutable, 'end': \DateTimeImmutable} $dates
     */
    public function setDateRange(array $dates): void
    {
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

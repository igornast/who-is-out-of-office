<?php

declare(strict_types=1);

namespace App\Shared\DTO\LeaveRequest;

use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use Ramsey\Uuid\UuidInterface;

class LeaveRequestTypeDTO
{
    public const DEFAULT_SLACK_STATUS_EMOJI = ':calendar:';

    public function __construct(
        public UuidInterface $id,
        public bool $isAffectingBalance,
        public string $name,
        public string $backgroundColor,
        public string $borderColor,
        public string $textColor,
        public string $icon,
        public ?int $sort = null,
        public ?string $slackStatusEmoji = null,
    ) {
    }

    public static function fromEntity(LeaveRequestType $leaveRequestType): self
    {
        return new self(
            id: $leaveRequestType->id,
            isAffectingBalance: $leaveRequestType->isAffectingBalance,
            name: $leaveRequestType->name,
            backgroundColor: $leaveRequestType->backgroundColor,
            borderColor: $leaveRequestType->borderColor,
            textColor: $leaveRequestType->textColor,
            icon: $leaveRequestType->icon,
            sort: $leaveRequestType->sort,
            slackStatusEmoji: $leaveRequestType->slackStatusEmoji,
        );
    }
}

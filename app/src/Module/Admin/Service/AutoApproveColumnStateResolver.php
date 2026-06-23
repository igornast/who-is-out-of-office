<?php

declare(strict_types=1);

namespace App\Module\Admin\Service;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\AppSettingsFacadeInterface;

class AutoApproveColumnStateResolver
{
    public function __construct(
        private readonly AppSettingsFacadeInterface $appSettingsFacade,
    ) {
    }

    public function resolve(LeaveRequest $leaveRequest): AutoApproveColumnState
    {
        if (true === $leaveRequest->isAutoApproved) {
            return new AutoApproveColumnState(AutoApproveColumnState::KIND_AUTO_APPROVED);
        }

        if (LeaveRequestStatusEnum::Pending === $leaveRequest->status) {
            $target = $leaveRequest->getCreatedAt()->modify(sprintf('+%d minutes', $this->appSettingsFacade->autoApproveDelay()));

            return new AutoApproveColumnState(AutoApproveColumnState::KIND_COUNTDOWN, $target);
        }

        if (LeaveRequestStatusEnum::Approved === $leaveRequest->status) {
            return new AutoApproveColumnState(AutoApproveColumnState::KIND_MANUALLY_APPROVED);
        }

        return new AutoApproveColumnState(AutoApproveColumnState::KIND_NONE);
    }
}

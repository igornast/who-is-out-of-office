<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\Command;

use App\Module\LeaveRequest\Message\LeaveRequestAutoApprovedMessage;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\AppSettingsFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[
    AsCommand(name: 'leave-request:auto-approve'),
    AsPeriodicTask(frequency: '1 minute')
]
class LeaveRequestAutoApproveCommand
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AppSettingsFacadeInterface $appSettingsFacade,
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(): int
    {
        if (false === $this->appSettingsFacade->isAutoApprove()) {
            return Command::SUCCESS;
        }

        $this->logger->debug('[LEAVE-REQUEST][AUTO]: Leave request auto approve run.');

        $createdBefore = new \DateTimeImmutable()->modify(sprintf('- %s seconds', $this->appSettingsFacade->autoApproveDelay()));

        $leaveRequestDTOs = $this->leaveRequestFacade->getPendingLeaveRequests($createdBefore);

        foreach ($leaveRequestDTOs as $leaveRequestDTO) {
            $leaveRequestDTO->status = LeaveRequestStatusEnum::Approved;
            $leaveRequestDTO->isAutoApproved = true;

            $this->leaveRequestFacade->update($leaveRequestDTO);

            $this->messageBus->dispatch(
                message: new LeaveRequestAutoApprovedMessage($leaveRequestDTO->id->toString()),
                stamps: [new DelayStamp(delay: 15000)],
            );
        }

        $this->logger->debug(sprintf('[LEAVE-REQUEST][AUTO]: Auto approve done. Approved %s requests.', count($leaveRequestDTOs)));

        return Command::SUCCESS;
    }
}

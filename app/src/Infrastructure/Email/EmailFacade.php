<?php

declare(strict_types=1);

namespace App\Infrastructure\Email;

use App\Infrastructure\Email\Message\LeaveRequestEmailType;
use App\Infrastructure\Email\Message\SendInvitationEmailMessage;
use App\Infrastructure\Email\Message\SendLeaveRequestEmailMessage;
use App\Infrastructure\Email\Message\SendPasswordResetEmailMessage;
use App\Shared\DTO\InvitationDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Facade\EmailFacadeInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class EmailFacade implements EmailFacadeInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function sendInvitationEmail(InvitationDTO $invitationDTO): void
    {
        $this->messageBus->dispatch(new SendInvitationEmailMessage($invitationDTO));
    }

    public function sendLeaveRequestPendingApprovalEmail(LeaveRequestDTO $leaveRequestDTO): void
    {
        $this->messageBus->dispatch(new SendLeaveRequestEmailMessage($leaveRequestDTO, LeaveRequestEmailType::PendingApproval));
    }

    public function sendLeaveRequestApprovedEmail(LeaveRequestDTO $leaveRequestDTO): void
    {
        $this->messageBus->dispatch(new SendLeaveRequestEmailMessage($leaveRequestDTO, LeaveRequestEmailType::Approved));
    }

    public function sendLeaveRequestRejectedEmail(LeaveRequestDTO $leaveRequestDTO): void
    {
        $this->messageBus->dispatch(new SendLeaveRequestEmailMessage($leaveRequestDTO, LeaveRequestEmailType::Rejected));
    }

    public function sendLeaveRequestWithdrawnEmail(LeaveRequestDTO $leaveRequestDTO): void
    {
        $this->messageBus->dispatch(new SendLeaveRequestEmailMessage($leaveRequestDTO, LeaveRequestEmailType::Withdrawn));
    }

    public function sendPasswordResetEmail(string $email, string $token): void
    {
        $this->messageBus->dispatch(new SendPasswordResetEmailMessage($email, $token));
    }
}

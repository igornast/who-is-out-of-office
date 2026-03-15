<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\InvitationDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;

interface EmailFacadeInterface
{
    public function sendInvitationEmail(InvitationDTO $invitationDTO): void;

    public function sendLeaveRequestPendingApprovalEmail(LeaveRequestDTO $leaveRequestDTO): void;

    public function sendLeaveRequestApprovedEmail(LeaveRequestDTO $leaveRequestDTO): void;

    public function sendLeaveRequestRejectedEmail(LeaveRequestDTO $leaveRequestDTO): void;

    public function sendLeaveRequestWithdrawnEmail(LeaveRequestDTO $leaveRequestDTO): void;

    public function sendPasswordResetEmail(string $email, string $token): void;
}

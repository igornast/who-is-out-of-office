<?php

declare(strict_types=1);

use App\Infrastructure\Email\EmailFacade;
use App\Infrastructure\Email\Message\LeaveRequestEmailType;
use App\Infrastructure\Email\Message\SendInvitationEmailMessage;
use App\Infrastructure\Email\Message\SendLeaveRequestEmailMessage;
use App\Infrastructure\Email\Message\SendPasswordResetEmailMessage;
use App\Tests\_fixtures\Shared\DTO\InvitationDTOFixture;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

beforeEach(function (): void {
    $this->messageBus = mock(MessageBusInterface::class);
    $this->facade = new EmailFacade($this->messageBus);
});

it('dispatches invitation email message', function (): void {
    $invitation = InvitationDTOFixture::create();

    $this->messageBus
        ->expects('dispatch')
        ->once()
        ->withArgs(fn (SendInvitationEmailMessage $msg) => $msg->invitationDTO === $invitation)
        ->andReturn(new Envelope(new stdClass()));

    $this->facade->sendInvitationEmail($invitation);
});

it('dispatches pending approval email message', function (): void {
    $leaveRequest = LeaveRequestDTOFixture::create();

    $this->messageBus
        ->expects('dispatch')
        ->once()
        ->withArgs(fn (SendLeaveRequestEmailMessage $msg) => $msg->leaveRequestDTO === $leaveRequest
            && LeaveRequestEmailType::PendingApproval === $msg->type)
        ->andReturn(new Envelope(new stdClass()));

    $this->facade->sendLeaveRequestPendingApprovalEmail($leaveRequest);
});

it('dispatches approved email message', function (): void {
    $leaveRequest = LeaveRequestDTOFixture::create();

    $this->messageBus
        ->expects('dispatch')
        ->once()
        ->withArgs(fn (SendLeaveRequestEmailMessage $msg) => $msg->leaveRequestDTO === $leaveRequest
            && LeaveRequestEmailType::Approved === $msg->type)
        ->andReturn(new Envelope(new stdClass()));

    $this->facade->sendLeaveRequestApprovedEmail($leaveRequest);
});

it('dispatches rejected email message', function (): void {
    $leaveRequest = LeaveRequestDTOFixture::create();

    $this->messageBus
        ->expects('dispatch')
        ->once()
        ->withArgs(fn (SendLeaveRequestEmailMessage $msg) => $msg->leaveRequestDTO === $leaveRequest
            && LeaveRequestEmailType::Rejected === $msg->type)
        ->andReturn(new Envelope(new stdClass()));

    $this->facade->sendLeaveRequestRejectedEmail($leaveRequest);
});

it('dispatches withdrawn email message', function (): void {
    $leaveRequest = LeaveRequestDTOFixture::create();

    $this->messageBus
        ->expects('dispatch')
        ->once()
        ->withArgs(fn (SendLeaveRequestEmailMessage $msg) => $msg->leaveRequestDTO === $leaveRequest
            && LeaveRequestEmailType::Withdrawn === $msg->type)
        ->andReturn(new Envelope(new stdClass()));

    $this->facade->sendLeaveRequestWithdrawnEmail($leaveRequest);
});

it('dispatches password reset email message', function (): void {
    $this->messageBus
        ->expects('dispatch')
        ->once()
        ->withArgs(fn (SendPasswordResetEmailMessage $msg) => 'user@example.com' === $msg->email
            && 'reset-token-123' === $msg->token)
        ->andReturn(new Envelope(new stdClass()));

    $this->facade->sendPasswordResetEmail('user@example.com', 'reset-token-123');
});

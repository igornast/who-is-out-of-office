<?php

declare(strict_types=1);

use App\Infrastructure\Email\Message\LeaveRequestEmailType;
use App\Infrastructure\Email\Message\SendLeaveRequestEmailMessage;
use App\Infrastructure\Email\MessageHandler\SendLeaveRequestEmailMessageHandler;
use App\Infrastructure\Email\UseCase\Command\SendLeaveRequestApprovedEmailCommandHandler;
use App\Infrastructure\Email\UseCase\Command\SendLeaveRequestPendingApprovalEmailCommandHandler;
use App\Infrastructure\Email\UseCase\Command\SendLeaveRequestRejectedEmailCommandHandler;
use App\Infrastructure\Email\UseCase\Command\SendLeaveRequestWithdrawnEmailCommandHandler;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->pendingApprovalHandler = mock(SendLeaveRequestPendingApprovalEmailCommandHandler::class);
    $this->approvedHandler = mock(SendLeaveRequestApprovedEmailCommandHandler::class);
    $this->rejectedHandler = mock(SendLeaveRequestRejectedEmailCommandHandler::class);
    $this->withdrawnHandler = mock(SendLeaveRequestWithdrawnEmailCommandHandler::class);

    $this->handler = new SendLeaveRequestEmailMessageHandler(
        pendingApprovalHandler: $this->pendingApprovalHandler,
        approvedHandler: $this->approvedHandler,
        rejectedHandler: $this->rejectedHandler,
        withdrawnHandler: $this->withdrawnHandler,
    );
});

it('delegates to pending approval handler', function () {
    $dto = LeaveRequestDTOFixture::create();
    $message = new SendLeaveRequestEmailMessage($dto, LeaveRequestEmailType::PendingApproval);

    $this->pendingApprovalHandler->expects('handle')->once()->with($dto);
    $this->approvedHandler->expects('handle')->never();
    $this->rejectedHandler->expects('handle')->never();
    $this->withdrawnHandler->expects('handle')->never();

    ($this->handler)($message);
});

it('delegates to approved handler', function () {
    $dto = LeaveRequestDTOFixture::create();
    $message = new SendLeaveRequestEmailMessage($dto, LeaveRequestEmailType::Approved);

    $this->pendingApprovalHandler->expects('handle')->never();
    $this->approvedHandler->expects('handle')->once()->with($dto);
    $this->rejectedHandler->expects('handle')->never();
    $this->withdrawnHandler->expects('handle')->never();

    ($this->handler)($message);
});

it('delegates to rejected handler', function () {
    $dto = LeaveRequestDTOFixture::create();
    $message = new SendLeaveRequestEmailMessage($dto, LeaveRequestEmailType::Rejected);

    $this->pendingApprovalHandler->expects('handle')->never();
    $this->approvedHandler->expects('handle')->never();
    $this->rejectedHandler->expects('handle')->once()->with($dto);
    $this->withdrawnHandler->expects('handle')->never();

    ($this->handler)($message);
});

it('delegates to withdrawn handler', function () {
    $dto = LeaveRequestDTOFixture::create();
    $message = new SendLeaveRequestEmailMessage($dto, LeaveRequestEmailType::Withdrawn);

    $this->pendingApprovalHandler->expects('handle')->never();
    $this->approvedHandler->expects('handle')->never();
    $this->rejectedHandler->expects('handle')->never();
    $this->withdrawnHandler->expects('handle')->once()->with($dto);

    ($this->handler)($message);
});

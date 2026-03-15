<?php

declare(strict_types=1);

use App\Infrastructure\Email\UseCase\Command\SendLeaveRequestApprovedEmailCommandHandler;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

beforeEach(function (): void {
    $this->mailer = mock(MailerInterface::class);
    $this->urlGenerator = mock(UrlGeneratorInterface::class);
    $this->translator = mock(TranslatorInterface::class);
    $this->translator->allows('trans')->andReturnUsing(fn (string $id) => $id);
    $this->logger = mock(LoggerInterface::class);

    $this->handler = new SendLeaveRequestApprovedEmailCommandHandler(
        emailFromAddress: 'noreply@whosooo.com',
        emailFromName: "Who's OOO",
        mailer: $this->mailer,
        urlGenerator: $this->urlGenerator,
        translator: $this->translator,
        logger: $this->logger,
    );
});

it('sends email to the requester', function () {
    $user = UserDTOFixture::create(['email' => 'employee@example.com']);
    $leaveRequestDTO = LeaveRequestDTOFixture::create(['user' => $user]);

    $this->urlGenerator->expects('generate')->andReturn('https://example.com/calendar');

    $this->mailer
        ->expects('send')
        ->once()
        ->withArgs(function (TemplatedEmail $email) {
            expect($email->getTo()[0]->getAddress())->toBe('employee@example.com');

            return true;
        });

    $this->handler->handle($leaveRequestDTO);
});

it('uses correct template', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create();

    $this->urlGenerator->expects('generate')->andReturn('https://example.com/calendar');

    $this->mailer
        ->expects('send')
        ->once()
        ->withArgs(function (TemplatedEmail $email) {
            expect($email->getHtmlTemplate())->toBe('@AppEmail/leave_request_approved.html.twig');

            return true;
        });

    $this->handler->handle($leaveRequestDTO);
});

it('includes approver name in context', function () {
    $approver = UserDTOFixture::create(['firstName' => 'Jane', 'lastName' => 'Manager']);
    $leaveRequestDTO = LeaveRequestDTOFixture::create(['approvedBy' => $approver]);

    $this->urlGenerator->expects('generate')->andReturn('https://example.com/calendar');

    $this->mailer
        ->expects('send')
        ->once()
        ->withArgs(function (TemplatedEmail $email) {
            expect($email->getContext()['approved_by'])->toBe('Jane Manager');

            return true;
        });

    $this->handler->handle($leaveRequestDTO);
});

it('shows auto-approved when no approver', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create(['approvedBy' => null]);

    $this->urlGenerator->expects('generate')->andReturn('https://example.com/calendar');

    $this->mailer
        ->expects('send')
        ->once()
        ->withArgs(function (TemplatedEmail $email) {
            expect($email->getContext()['approved_by'])->toBe('email.leave_request.approved.auto_approved');

            return true;
        });

    $this->handler->handle($leaveRequestDTO);
});

it('skips when user has email notifications disabled', function () {
    $user = UserDTOFixture::create(['isEmailNotificationsEnabled' => false]);
    $leaveRequestDTO = LeaveRequestDTOFixture::create(['user' => $user]);

    $this->mailer->expects('send')->never();

    $this->handler->handle($leaveRequestDTO);
});

it('logs error when mailer transport fails', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create();

    $this->urlGenerator->expects('generate')->andReturn('https://example.com/calendar');

    $this->mailer
        ->expects('send')
        ->andThrow(new TransportException('SMTP connection refused'));

    $this->logger
        ->expects('error')
        ->once()
        ->withArgs(fn (string $message) => str_contains($message, 'SMTP connection refused'));

    $this->handler->handle($leaveRequestDTO);
});

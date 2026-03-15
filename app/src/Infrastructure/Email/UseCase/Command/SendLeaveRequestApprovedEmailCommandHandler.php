<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\UseCase\Command;

use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SendLeaveRequestApprovedEmailCommandHandler
{
    public function __construct(
        private readonly string $emailFromAddress,
        private readonly string $emailFromName,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(LeaveRequestDTO $leaveRequestDTO): void
    {
        if (!$leaveRequestDTO->user->isEmailNotificationsEnabled) {
            return;
        }

        $approvedBy = $this->translator->trans('email.leave_request.approved.auto_approved');
        if (null !== $leaveRequestDTO->approvedBy) {
            $approvedBy = $leaveRequestDTO->approvedBy->getFullName();
        }

        $calendarUrl = $this->urlGenerator->generate(
            'app_calendar_view',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = new TemplatedEmail()
            ->from(new Address($this->emailFromAddress, $this->emailFromName))
            ->to($leaveRequestDTO->user->email)
            ->subject($this->translator->trans('email.leave_request.approved.subject'))
            ->htmlTemplate('@AppEmail/leave_request_approved.html.twig')
            ->context([
                'employee_name' => $leaveRequestDTO->user->getFullName(),
                'approved_by' => $approvedBy,
                'leave_type' => $leaveRequestDTO->leaveType->name,
                'start_date' => $leaveRequestDTO->startDate,
                'end_date' => $leaveRequestDTO->endDate,
                'duration' => $leaveRequestDTO->workDays,
                'remaining_balance' => $leaveRequestDTO->user->currentLeaveBalance,
                'calendar_url' => $calendarUrl,
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error(sprintf('Failed to send approved email for leave request %s: %s', $leaveRequestDTO->id->toString(), $e->getMessage()));
        }
    }
}

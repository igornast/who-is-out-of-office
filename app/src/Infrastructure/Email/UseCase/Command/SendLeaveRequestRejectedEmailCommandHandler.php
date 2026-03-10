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

class SendLeaveRequestRejectedEmailCommandHandler
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

        $rejectedBy = $this->translator->trans('email.leave_request.rejected.default_rejected_by');
        if (null !== $leaveRequestDTO->approvedBy) {
            $rejectedBy = $leaveRequestDTO->approvedBy->getFullName();
        }

        $dashboardUrl = $this->urlGenerator->generate(
            'app_dashboard',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = new TemplatedEmail()
            ->from(new Address($this->emailFromAddress, $this->emailFromName))
            ->to($leaveRequestDTO->user->email)
            ->subject($this->translator->trans('email.leave_request.rejected.subject'))
            ->htmlTemplate('@AppEmail/leave_request_rejected.html.twig')
            ->context([
                'employee_name' => $leaveRequestDTO->user->getFullName(),
                'rejected_by' => $rejectedBy,
                'leave_type' => $leaveRequestDTO->leaveType->name,
                'start_date' => $leaveRequestDTO->startDate,
                'end_date' => $leaveRequestDTO->endDate,
                'duration' => $leaveRequestDTO->workDays,
                'dashboard_url' => $dashboardUrl,
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error(sprintf('Failed to send rejected email for leave request %s: %s', $leaveRequestDTO->id->toString(), $e->getMessage()));
        }
    }
}

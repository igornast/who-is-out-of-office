<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\UseCase\Command;

use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Facade\UserFacadeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SendLeaveRequestPendingApprovalEmailCommandHandler
{
    public function __construct(
        private readonly string $emailFromAddress,
        private readonly string $emailFromName,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly UserFacadeInterface $userFacade,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(LeaveRequestDTO $leaveRequestDTO): void
    {
        $managerId = $leaveRequestDTO->user->managerId;

        if (null === $managerId) {
            return;
        }

        $manager = $this->userFacade->getUser($managerId);

        if (null === $manager || !$manager->isEmailNotificationsEnabled) {
            return;
        }

        $employeeName = $leaveRequestDTO->user->getFullName();
        $employeeInitials = mb_strtoupper(mb_substr($leaveRequestDTO->user->firstName, 0, 1).mb_substr($leaveRequestDTO->user->lastName, 0, 1));

        $requestUrl = $this->urlGenerator->generate(
            'app_dashboard_app_leave_request_detail',
            ['entityId' => $leaveRequestDTO->id->toString()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = new TemplatedEmail()
            ->from(new Address($this->emailFromAddress, $this->emailFromName))
            ->to($manager->email)
            ->subject($this->translator->trans('email.leave_request.pending_approval.subject', ['%name%' => $employeeName]))
            ->htmlTemplate('@AppEmail/leave_request_pending_approval.html.twig')
            ->context([
                'employee_name' => $employeeName,
                'employee_initials' => $employeeInitials,
                'leave_type' => $leaveRequestDTO->leaveType->name,
                'start_date' => $leaveRequestDTO->startDate,
                'end_date' => $leaveRequestDTO->endDate,
                'duration' => $leaveRequestDTO->workDays,
                'reason' => $leaveRequestDTO->comment,
                'request_url' => $requestUrl,
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error(sprintf('Failed to send pending approval email for leave request %s: %s', $leaveRequestDTO->id->toString(), $e->getMessage()));
        }
    }
}

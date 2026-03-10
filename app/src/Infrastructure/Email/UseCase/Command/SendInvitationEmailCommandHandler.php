<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\UseCase\Command;

use App\Shared\DTO\InvitationDTO;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SendInvitationEmailCommandHandler
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

    public function handle(InvitationDTO $invitationDTO): void
    {
        $invitationUrl = $this->urlGenerator->generate(
            'app_user_invitation',
            ['token' => $invitationDTO->token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = new TemplatedEmail()
            ->from(new Address($this->emailFromAddress, $this->emailFromName))
            ->to($invitationDTO->user->email)
            ->subject($this->translator->trans('email.invitation.subject'))
            ->htmlTemplate('@AppEmail/invitation.html.twig')
            ->context([
                'invitation_url' => $invitationUrl,
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error(sprintf('Failed to send invitation email to %s: %s', $invitationDTO->user->email, $e->getMessage()));
        }
    }
}

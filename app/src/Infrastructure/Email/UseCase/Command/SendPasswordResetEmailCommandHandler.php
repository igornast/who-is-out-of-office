<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\UseCase\Command;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SendPasswordResetEmailCommandHandler
{
    public function __construct(
        private readonly string $emailFromAddress,
        private readonly string $emailFromName,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function handle(string $email, string $token): void
    {
        $resetUrl = $this->urlGenerator->generate(
            'app_password_reset',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $emailMessage = new TemplatedEmail()
            ->from(new Address($this->emailFromAddress, $this->emailFromName))
            ->to($email)
            ->subject($this->translator->trans('email.password_reset.subject'))
            ->htmlTemplate('@AppEmail/password_reset.html.twig')
            ->context([
                'reset_url' => $resetUrl,
                'recipient_email' => $email,
            ]);

        $this->mailer->send($emailMessage);
    }
}

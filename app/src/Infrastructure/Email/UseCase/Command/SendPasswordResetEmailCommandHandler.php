<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\UseCase\Command;

use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Translation\TranslatorInterface;

class SendPasswordResetEmailCommandHandler
{
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1'];

    public function __construct(
        #[Autowire(env: 'EMAIL_FROM_ADDRESS')]
        private readonly string $emailFromAddress,
        #[Autowire(env: 'EMAIL_FROM_NAME')]
        private readonly string $emailFromName,
        #[Autowire(env: 'APP_BASE_URL')]
        private readonly string $appBaseUrl,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(string $email, string $token): void
    {
        $emailMessage = new TemplatedEmail()
            ->from(new Address($this->emailFromAddress, $this->emailFromName))
            ->to($email)
            ->subject($this->translator->trans('email.password_reset.subject'))
            ->htmlTemplate('@AppEmail/password_reset.html.twig')
            ->context([
                'reset_url' => $this->generateResetUrl($token),
                'recipient_email' => $email,
            ]);

        $this->mailer->send($emailMessage);
    }

    private function generateResetUrl(string $token): string
    {
        if ($this->shouldFallBackToRequestHost()) {
            $this->logUnparsableProdBaseUrl();

            return $this->urlGenerator->generate(
                'app_password_reset',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        $requestContext = $this->urlGenerator->getContext();
        $this->urlGenerator->setContext(RequestContext::fromUri($this->appBaseUrl));

        try {
            return $this->urlGenerator->generate(
                'app_password_reset',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        } finally {
            $this->urlGenerator->setContext($requestContext);
        }
    }

    private function shouldFallBackToRequestHost(): bool
    {
        if ('prod' !== $this->environment) {
            return false;
        }

        return null === $this->resolveBaseUrlHost();
    }

    private function logUnparsableProdBaseUrl(): void
    {
        $this->logger->error(sprintf(
            '[EMAIL][PASSWORD-RESET]: APP_BASE_URL is "%s" in prod, so the password reset link was built from the request host instead. Set APP_BASE_URL to your public URL to protect reset links against Host header poisoning.',
            $this->appBaseUrl,
        ));
    }

    private function resolveBaseUrlHost(): ?string
    {
        $host = parse_url($this->appBaseUrl, PHP_URL_HOST);

        if (!is_string($host) || '' === $host) {
            return null;
        }

        $host = trim(strtolower($host), '[]');

        if (in_array($host, self::LOCAL_HOSTS, true)) {
            return null;
        }

        return $host;
    }
}

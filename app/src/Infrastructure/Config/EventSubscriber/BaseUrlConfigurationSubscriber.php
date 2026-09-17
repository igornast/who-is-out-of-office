<?php

declare(strict_types=1);

namespace App\Infrastructure\Config\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BaseUrlConfigurationSubscriber implements EventSubscriberInterface
{
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1'];

    public function __construct(
        #[Autowire(env: 'APP_BASE_URL')]
        private readonly string $baseUrl,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [ConsoleEvents::COMMAND => 'warnAboutLocalBaseUrl'];
    }

    public function warnAboutLocalBaseUrl(): void
    {
        if ('prod' !== $this->environment) {
            return;
        }

        $host = $this->resolveHost();

        if (null === $host) {
            $this->logger->error(sprintf(
                '[CONFIG][BASE-URL]: APP_BASE_URL is "%s", which has no parsable host. It must be an absolute URL including the scheme, e.g. "https://leave.example.com". Every link in outgoing email depends on it.',
                $this->baseUrl,
            ));

            return;
        }

        if (!in_array($host, self::LOCAL_HOSTS, true)) {
            return;
        }

        $this->logger->error(sprintf(
            '[CONFIG][BASE-URL]: APP_BASE_URL is still "%s" in prod. Every link in outgoing email will point at this host. Set it to your public URL.',
            $this->baseUrl,
        ));
    }

    private function resolveHost(): ?string
    {
        $host = parse_url($this->baseUrl, PHP_URL_HOST);

        if (!is_string($host) || '' === $host) {
            return null;
        }

        return trim(strtolower($host), '[]');
    }
}

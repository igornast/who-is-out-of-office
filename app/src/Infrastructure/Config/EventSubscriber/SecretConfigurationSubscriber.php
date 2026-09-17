<?php

declare(strict_types=1);

namespace App\Infrastructure\Config\EventSubscriber;

use App\Shared\Enum\AppEnvironmentEnum;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SecretConfigurationSubscriber implements EventSubscriberInterface
{
    public const int MIN_ICAL_SECRET_LENGTH = 32;

    public function __construct(
        #[Autowire(env: 'ICAL_SECRET')]
        private readonly string $icalSecret,
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
        return [ConsoleEvents::COMMAND => 'warnAboutWeakSecrets'];
    }

    public function warnAboutWeakSecrets(): void
    {
        if (AppEnvironmentEnum::PROD->value !== $this->environment) {
            return;
        }

        if (strlen($this->icalSecret) >= self::MIN_ICAL_SECRET_LENGTH) {
            return;
        }

        $this->logger->warning(sprintf(
            '[CONFIG][ICAL-SECRET]: ICAL_SECRET is shorter than %d characters in prod, so calendar feed URLs may be guessable. Generate a new one with "openssl rand -hex 16". Changing it invalidates every existing calendar subscription URL.',
            self::MIN_ICAL_SECRET_LENGTH,
        ));
    }
}

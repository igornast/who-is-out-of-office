<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\EventListener;

use App\Shared\Facade\AppSettingsFacadeInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\MessageEvent;

#[AsEventListener(MessageEvent::class)]
class EmailContextListener
{
    public function __construct(
        private readonly AppSettingsFacadeInterface $appSettingsFacade,
    ) {
    }

    public function __invoke(MessageEvent $event): void
    {
        $message = $event->getMessage();

        if (!$message instanceof TemplatedEmail) {
            return;
        }

        $context = $message->getContext();

        if (array_key_exists('company_name', $context)) {
            return;
        }

        $organizationName = $this->appSettingsFacade->organizationName();

        if ('' === $organizationName) {
            return;
        }

        $context['company_name'] = $organizationName;
        $message->context($context);
    }
}

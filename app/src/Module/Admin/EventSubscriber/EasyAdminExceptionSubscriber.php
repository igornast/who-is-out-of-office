<?php

declare(strict_types=1);

namespace App\Module\Admin\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\InsufficientEntityPermissionException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class EasyAdminExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/app')) {
            return;
        }

        if ($exception instanceof ForbiddenActionException
            || $exception instanceof InsufficientEntityPermissionException
        ) {
            $html = $this->twig->render('@Twig/Exception/error403.html.twig');
            $response = new Response($html, Response::HTTP_FORBIDDEN);
            $event->setResponse($response);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Admin\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HideDeleteActionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeCrudActionEvent::class => 'onBeforeCrudActionEvent',
        ];
    }

    public function onBeforeCrudActionEvent(BeforeCrudActionEvent $event): void
    {
        $crud = $event->getAdminContext()?->getCrud();

        if (null === $crud) {
            return;
        }

        $crud->getActionsConfig()->disableActions([Action::DELETE, Action::BATCH_DELETE]);
    }
}

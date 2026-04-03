<?php

declare(strict_types=1);

use App\Module\Admin\EventSubscriber\HideDeleteActionSubscriber;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionConfigDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;

beforeEach(function (): void {
    $this->subscriber = new HideDeleteActionSubscriber();
});

it('subscribes to BeforeCrudActionEvent', function () {
    $events = HideDeleteActionSubscriber::getSubscribedEvents();

    expect($events)->toHaveKey(BeforeCrudActionEvent::class)
        ->and($events[BeforeCrudActionEvent::class])->toBe('onBeforeCrudActionEvent');
});

it('disables delete and batch delete actions', function () {
    $crud = new CrudDto();
    $crud->setActionsConfig(new ActionConfigDto());
    $ctx = AdminContext::forTesting(crudContext: CrudContext::forTesting(crudDto: $crud));
    $event = new BeforeCrudActionEvent($ctx);

    $this->subscriber->onBeforeCrudActionEvent($event);

    $disabled = $event->getAdminContext()->getCrud()->getActionsConfig()->getDisabledActions();

    expect($disabled)->toContain(Action::DELETE)
        ->and($disabled)->toContain(Action::BATCH_DELETE);
});

it('does nothing when admin context is null', function () {
    $event = new BeforeCrudActionEvent(null);

    $this->subscriber->onBeforeCrudActionEvent($event);

    expect($event->getAdminContext())->toBeNull();
});

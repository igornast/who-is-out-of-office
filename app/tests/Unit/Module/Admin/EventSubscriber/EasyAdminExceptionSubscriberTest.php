<?php

declare(strict_types=1);

use App\Module\Admin\EventSubscriber\EasyAdminExceptionSubscriber;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\InsufficientEntityPermissionException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

function createEaForbiddenActionException(): ForbiddenActionException
{
    $crud = new CrudDto();
    $crud->setCurrentAction('index');
    $crud->setControllerFqcn('SomeController');
    $ctx = AdminContext::forTesting(crudContext: CrudContext::forTesting(crudDto: $crud));

    return new ForbiddenActionException($ctx);
}

function createEaInsufficientEntityPermissionException(): InsufficientEntityPermissionException
{
    $ref = new ReflectionClass(InsufficientEntityPermissionException::class);

    return $ref->newInstanceWithoutConstructor();
}

beforeEach(function (): void {
    $this->twig = mock(Environment::class);
    $this->kernel = mock(HttpKernelInterface::class);

    $this->subscriber = new EasyAdminExceptionSubscriber(twig: $this->twig);
});

it('subscribes to kernel exception event with priority 10', function () {
    $events = EasyAdminExceptionSubscriber::getSubscribedEvents();

    expect($events)->toHaveKey(KernelEvents::EXCEPTION)
        ->and($events[KernelEvents::EXCEPTION])->toBe(['onKernelException', 10]);
});

it('renders 403 page for ForbiddenActionException on /app routes', function () {
    $this->twig->expects('render')
        ->with('@Twig/Exception/error403.html.twig')
        ->andReturn('<html>403</html>');

    $request = Request::create('/app/dashboard');
    $event = new ExceptionEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, createEaForbiddenActionException());

    $this->subscriber->onKernelException($event);

    expect($event->getResponse())->not->toBeNull()
        ->and($event->getResponse()->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
});

it('renders 403 page for InsufficientEntityPermissionException on /app routes', function () {
    $this->twig->expects('render')
        ->with('@Twig/Exception/error403.html.twig')
        ->andReturn('<html>403</html>');

    $request = Request::create('/app/users');
    $event = new ExceptionEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, createEaInsufficientEntityPermissionException());

    $this->subscriber->onKernelException($event);

    expect($event->getResponse())->not->toBeNull()
        ->and($event->getResponse()->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
});

it('ignores exceptions on non /app routes', function () {
    $this->twig->expects('render')->never();

    $request = Request::create('/api/something');
    $event = new ExceptionEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, createEaForbiddenActionException());

    $this->subscriber->onKernelException($event);

    expect($event->getResponse())->toBeNull();
});

it('ignores non-EasyAdmin exceptions on /app routes', function () {
    $this->twig->expects('render')->never();

    $request = Request::create('/app/dashboard');
    $event = new ExceptionEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, new RuntimeException('something'));

    $this->subscriber->onKernelException($event);

    expect($event->getResponse())->toBeNull();
});

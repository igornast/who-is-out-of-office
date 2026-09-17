<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Security\EventListener\InactiveUserSessionListener;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

beforeEach(function (): void {
    $this->security = mock(Security::class);
    $this->urlGenerator = mock(UrlGeneratorInterface::class);
    $this->urlGenerator->allows('generate')->with('app_login')->andReturn('/login');

    $this->user = new User(
        id: Uuid::uuid4(),
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@whoisooo.app',
        password: 'hashed',
        isActive: false,
    );

    $this->mainRequestEvent = fn (): RequestEvent => new RequestEvent(
        mock(HttpKernelInterface::class),
        Request::create('/app/dashboard'),
        HttpKernelInterface::MAIN_REQUEST,
    );
});

it('logs out an inactive user and redirects to the login page', function (): void {
    $this->security->allows('getUser')->andReturn($this->user);
    $this->security->expects('logout')->with(false)->andReturn(null);
    $event = ($this->mainRequestEvent)();

    new InactiveUserSessionListener($this->security, $this->urlGenerator)($event);

    expect($event->getResponse())->toBeInstanceOf(RedirectResponse::class)
        ->and($event->getResponse()->getTargetUrl())->toBe('/login');
});

it('does nothing for an active user', function (): void {
    $this->user->isActive = true;
    $this->security->allows('getUser')->andReturn($this->user);
    $this->security->expects('logout')->never();
    $event = ($this->mainRequestEvent)();

    new InactiveUserSessionListener($this->security, $this->urlGenerator)($event);

    expect($event->hasResponse())->toBeFalse();
});

it('does nothing for anonymous requests', function (): void {
    $this->security->allows('getUser')->andReturn(null);
    $this->security->expects('logout')->never();
    $event = ($this->mainRequestEvent)();

    new InactiveUserSessionListener($this->security, $this->urlGenerator)($event);

    expect($event->hasResponse())->toBeFalse();
});

it('does nothing for sub-requests', function (): void {
    $this->security->expects('getUser')->never();
    $event = new RequestEvent(mock(HttpKernelInterface::class), Request::create('/app/dashboard'), HttpKernelInterface::SUB_REQUEST);

    new InactiveUserSessionListener($this->security, $this->urlGenerator)($event);

    expect($event->hasResponse())->toBeFalse();
});

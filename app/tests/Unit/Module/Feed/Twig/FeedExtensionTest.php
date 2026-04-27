<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Feed\Twig\FeedExtension;
use App\Shared\Facade\FeedFacadeInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;

beforeEach(function (): void {
    $this->facade = mock(FeedFacadeInterface::class);
    $this->security = mock(Security::class);
    $this->extension = new FeedExtension($this->facade, $this->security);
});

it('returns 0 when no user is logged in', function (): void {
    $this->security->expects('getUser')->once()->andReturn(null);
    $this->facade->shouldNotReceive('getUnreadCountForUser');

    expect($this->extension->getUnreadCount())->toBe(0);
});

it('delegates to the facade with the current user id', function (): void {
    $user = mock(User::class);
    $user->id = Uuid::fromString('11111111-1111-1111-1111-111111111111');

    $this->security->allows('getUser')->andReturn($user);
    $this->facade->expects('getUnreadCountForUser')
        ->once()
        ->with('11111111-1111-1111-1111-111111111111')
        ->andReturn(7);

    expect($this->extension->getUnreadCount())->toBe(7);
});

it('memoizes the count within the same instance', function (): void {
    $user = mock(User::class);
    $user->id = Uuid::fromString('11111111-1111-1111-1111-111111111111');
    $this->security->allows('getUser')->andReturn($user);

    $this->facade->expects('getUnreadCountForUser')->once()->andReturn(3);

    expect($this->extension->getUnreadCount())->toBe(3);
    expect($this->extension->getUnreadCount())->toBe(3);
});

it('exposes a feed_unread_count Twig function', function (): void {
    $functions = $this->extension->getFunctions();

    expect($functions)->toHaveCount(1)
        ->and($functions[0]->getName())->toBe('feed_unread_count');
});

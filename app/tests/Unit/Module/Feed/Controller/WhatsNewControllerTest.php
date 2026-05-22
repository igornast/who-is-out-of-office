<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Feed\Controller\WhatsNewController;
use App\Shared\Facade\FeedFacadeInterface;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

beforeEach(function (): void {
    $this->capturedView = null;
    $this->capturedParams = null;

    $twig = mock(Environment::class);
    $twig->allows('render')->andReturnUsing(function (string $view, array $params = []) {
        $this->capturedView = $view;
        $this->capturedParams = $params;

        return '<html></html>';
    });

    $csrfManager = mock(CsrfTokenManagerInterface::class);
    $csrfManager->allows('isTokenValid')->andReturn(true);

    $container = mock(ContainerInterface::class);
    $container->allows('has')->with('serializer')->andReturn(false);
    $container->allows('has')->andReturn(true);
    $container->allows('get')->with('twig')->andReturn($twig);
    $container->allows('get')->with('security.csrf.token_manager')->andReturn($csrfManager);

    $this->container = $container;
    $this->feedFacade = mock(FeedFacadeInterface::class);
});

function buildUser(?DateTimeImmutable $feedLastSeenAt, ?DateTimeImmutable $createdAt = null): User
{
    $user = new User(
        id: Uuid::uuid4(),
        firstName: 'Ada',
        lastName: 'Lovelace',
        email: 'ada@example.com',
        password: 'hashed',
        feedLastSeenAt: $feedLastSeenAt,
    );

    if (null !== $createdAt) {
        $user->setCreatedAt($createdAt);
    }

    return $user;
}

it('renders the whats_new template with grouped items from the facade', function (): void {
    $grouped = [
        'blog' => [(object) ['title' => 'A blog post']],
        'changelog' => [(object) ['title' => 'A changelog entry']],
        'announcement' => [],
    ];
    $this->feedFacade->expects('getRecentItemsGrouped')->with(50)->andReturn($grouped);

    $controller = new WhatsNewController($this->feedFacade);
    $controller->setContainer($this->container);

    $user = buildUser(feedLastSeenAt: new DateTimeImmutable('2026-01-01 00:00:00'));

    ($controller)($user);

    expect($this->capturedView)->toBe('@AppFeed/whats_new.html.twig')
        ->and($this->capturedParams['grouped'])->toBe($grouped);
});

it('passes user.feedLastSeenAt as previous_seen_at when the user has visited before', function (): void {
    $this->feedFacade->allows('getRecentItemsGrouped')->andReturn(['blog' => [], 'changelog' => [], 'announcement' => []]);

    $controller = new WhatsNewController($this->feedFacade);
    $controller->setContainer($this->container);

    $lastSeen = new DateTimeImmutable('2026-03-15 12:00:00');
    $user = buildUser(
        feedLastSeenAt: $lastSeen,
        createdAt: new DateTimeImmutable('2025-01-01 00:00:00'),
    );

    ($controller)($user);

    expect($this->capturedParams['previous_seen_at'])->toBe($lastSeen);
});

it('falls back to user.createdAt as previous_seen_at when the user has never visited the feed', function (): void {
    $this->feedFacade->allows('getRecentItemsGrouped')->andReturn(['blog' => [], 'changelog' => [], 'announcement' => []]);

    $controller = new WhatsNewController($this->feedFacade);
    $controller->setContainer($this->container);

    $createdAt = new DateTimeImmutable('2025-06-01 09:00:00');
    $user = buildUser(feedLastSeenAt: null, createdAt: $createdAt);

    ($controller)($user);

    expect($this->capturedParams['previous_seen_at'])->toBe($createdAt);
});

it('requests up to 50 items from the facade', function (): void {
    $this->feedFacade->expects('getRecentItemsGrouped')
        ->with(50)
        ->once()
        ->andReturn(['blog' => [], 'changelog' => [], 'announcement' => []]);

    $controller = new WhatsNewController($this->feedFacade);
    $controller->setContainer($this->container);

    ($controller)(buildUser(feedLastSeenAt: new DateTimeImmutable()));
});

it('markAsRead returns 204 and calls facade with the current user id when CSRF token is valid', function (): void {
    $user = buildUser(feedLastSeenAt: null);
    $userIdString = $user->id->toString();
    $this->feedFacade->expects('markAsReadForUser')->with($userIdString)->once();

    $controller = new WhatsNewController($this->feedFacade);
    $controller->setContainer($this->container);

    $request = Request::create('/app/whats-new/mark-as-read', 'POST', ['_csrf_token' => 'valid-token']);

    $response = $controller->markAsRead($user, $request);

    expect($response->getStatusCode())->toBe(204);
});

it('markAsRead returns 403 and does not touch the facade when CSRF token is invalid', function (): void {
    $csrfManager = mock(CsrfTokenManagerInterface::class);
    $csrfManager->allows('isTokenValid')->andReturn(false);

    $container = mock(ContainerInterface::class);
    $container->allows('has')->with('serializer')->andReturn(false);
    $container->allows('has')->andReturn(true);
    $container->allows('get')->with('security.csrf.token_manager')->andReturn($csrfManager);

    $this->feedFacade->expects('markAsReadForUser')->never();

    $controller = new WhatsNewController($this->feedFacade);
    $controller->setContainer($container);

    $request = Request::create('/app/whats-new/mark-as-read', 'POST', ['_csrf_token' => 'bad-token']);

    $response = $controller->markAsRead(buildUser(feedLastSeenAt: null), $request);

    expect($response->getStatusCode())->toBe(403);
});

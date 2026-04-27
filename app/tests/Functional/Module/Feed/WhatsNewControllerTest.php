<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\FeedItem;
use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Enum\FeedItemTypeEnum;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    createPantherClient()->request('GET', '/logout');

    $kernel = static::bootKernel();
    $this->entityManager = $kernel->getContainer()->get('doctrine')->getManager();

    foreach ($this->entityManager->getRepository(FeedItem::class)->findAll() as $existing) {
        $this->entityManager->remove($existing);
    }
    $this->entityManager->flush();

    $blog = new FeedItem(
        id: Uuid::uuid4(),
        externalId: 'blog-test-1',
        title: 'How we ship',
        url: 'https://whoisooo.app/blog/ship',
        contentType: FeedItemTypeEnum::Blog,
        publishedAt: new DateTimeImmutable('-1 day'),
        fetchedAt: new DateTimeImmutable(),
        summary: 'A short summary',
    );
    $changelog = new FeedItem(
        id: Uuid::uuid4(),
        externalId: 'changelog-test-1',
        title: 'v1.42 ships',
        url: 'https://whoisooo.app/changelog#v1.42',
        contentType: FeedItemTypeEnum::Changelog,
        publishedAt: new DateTimeImmutable('-2 days'),
        fetchedAt: new DateTimeImmutable(),
    );
    $this->entityManager->persist($blog);
    $this->entityManager->persist($changelog);
    $this->entityManager->flush();
});

it('redirects unauthenticated users to login', function (): void {
    $client = createPantherClient();
    $client->request('GET', '/app/whats-new');

    expect($client->getCrawler()->text())->toContain('Sign in');
});

it('renders the page with grouped sections for an authenticated user', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@whoisooo.app', '123');

    $client->request('GET', '/app/whats-new');
    $client->waitForVisibility('.whats-new-page');

    $content = $client->getCrawler()->text();

    expect($content)
        ->toContain('From the blog')
        ->toContain('Product updates')
        ->toContain('How we ship')
        ->toContain('v1.42 ships');
});

it('GET no longer mutates feedLastSeenAt directly — JS POST marks feed as read on visit', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@whoisooo.app', '123');

    // Reset feedLastSeenAt to null so we can detect the JS-driven POST
    $this->entityManager->clear();
    $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
    $admin->feedLastSeenAt = null;
    $this->entityManager->flush();

    $client->request('GET', '/app/whats-new');
    $client->waitForVisibility('.whats-new-page');

    // Poll until the JS-driven POST updates feedLastSeenAt (up to ~5 seconds)
    $deadline = time() + 5;
    $updated = null;
    while (time() < $deadline) {
        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
        if (null !== $updated->feedLastSeenAt) {
            break;
        }
        usleep(300_000);
    }

    expect($updated->feedLastSeenAt)->not->toBeNull()
        ->and($updated->feedLastSeenAt->getTimestamp())->toBeGreaterThan(time() - 60);
});

it('shows empty state when no feed items exist', function (): void {
    foreach ($this->entityManager->getRepository(FeedItem::class)->findAll() as $existing) {
        $this->entityManager->remove($existing);
    }
    $this->entityManager->flush();

    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@whoisooo.app', '123');

    $client->request('GET', '/app/whats-new');
    $client->waitForVisibility('.whats-new-empty');

    expect($client->getCrawler()->text())->toContain('Nothing new yet');
});

it('POST to mark-as-read with valid same-origin header returns 204 and updates feedLastSeenAt', function (): void {
    // Reset feedLastSeenAt so we can assert it gets set
    $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
    $admin->feedLastSeenAt = null;
    $this->entityManager->flush();

    $client = static::createClient();
    $client->loginUser($admin);

    // The stateless CSRF manager accepts the request when the Origin header matches the host.
    // In the test environment (HTTP), Origin: http://localhost satisfies isValidOrigin().
    // The token value is the cookie-name placeholder ('csrf-token') because
    // getToken('mark_feed_as_read') returns new CsrfToken($id, $this->cookieName).
    $client->request(
        'POST',
        '/app/whats-new/mark-as-read',
        ['_csrf_token' => 'csrf-token'],
        [],
        ['HTTP_ORIGIN' => 'http://localhost'],
    );

    expect($client->getResponse()->getStatusCode())->toBe(204);

    $this->entityManager->clear();
    $updated = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
    expect($updated->feedLastSeenAt)->not->toBeNull()
        ->and($updated->feedLastSeenAt->getTimestamp())->toBeGreaterThan(time() - 60);
});

it('POST to mark-as-read with no CSRF token returns 403', function (): void {
    $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);

    $client = static::createClient();
    $client->loginUser($admin);

    // No Origin/Referer/Sec-Fetch-Site header, no cookie, no token — both checks null → 403
    $client->request('POST', '/app/whats-new/mark-as-read');

    expect($client->getResponse()->getStatusCode())->toBe(403);
});

it('POST to mark-as-read with invalid CSRF token returns 403', function (): void {
    $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);

    $client = static::createClient();
    $client->loginUser($admin);

    // Short token fails the TOKEN_MIN_LENGTH check (< 24 chars) and no origin headers → 403
    $client->request('POST', '/app/whats-new/mark-as-read', ['_csrf_token' => 'bad-token']);

    expect($client->getResponse()->getStatusCode())->toBe(403);
});

<?php

declare(strict_types=1);

use App\Shared\Facade\EmailFacadeInterface;
use Symfony\Component\Routing\RequestContext;

it('builds password reset links from APP_BASE_URL even when the request host is forged', function (): void {
    static::bootKernel();
    $container = static::getContainer();
    $router = $container->get('router');
    $router->setContext(new RequestContext(host: 'evil.tld'));

    $token = str_repeat('a', 64);
    $container->get(EmailFacadeInterface::class)->sendPasswordResetEmail('user@whoisooo.app', $token);

    static::assertEmailCount(1);
    $message = static::getMailerMessage(0);

    expect($message->getHtmlBody())->toContain(sprintf('https://whoisooo.test/password-reset/%s', $token))
        ->and($message->getHtmlBody())->not->toContain('evil.tld')
        ->and($router->getContext()->getHost())->toBe('evil.tld');
});

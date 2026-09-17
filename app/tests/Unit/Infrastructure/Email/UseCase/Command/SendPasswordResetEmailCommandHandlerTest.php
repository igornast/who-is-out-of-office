<?php

declare(strict_types=1);

use App\Infrastructure\Email\UseCase\Command\SendPasswordResetEmailCommandHandler;
use App\Shared\Enum\AppEnvironmentEnum;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Translation\TranslatorInterface;

beforeEach(function (): void {
    $this->mailer = mock(MailerInterface::class);
    $this->urlGenerator = mock(UrlGeneratorInterface::class);
    $this->translator = mock(TranslatorInterface::class);
    $this->translator->allows('trans')->andReturnUsing(fn (string $id) => $id);
    $this->logger = mock(LoggerInterface::class);

    $this->requestContext = new RequestContext(host: 'evil.tld');
    $this->urlGenerator->allows('getContext')->andReturn($this->requestContext);

    $this->handler = new SendPasswordResetEmailCommandHandler(
        emailFromAddress: 'noreply@whoisooo.app',
        emailFromName: "Who's OOO",
        appBaseUrl: 'https://leave.example.com',
        environment: 'test',
        mailer: $this->mailer,
        urlGenerator: $this->urlGenerator,
        translator: $this->translator,
        logger: $this->logger,
    );
});

describe('SendPasswordResetEmailCommandHandler', function (): void {
    it('sends email with correct recipient', function (): void {
        $this->urlGenerator->allows('setContext');
        $this->urlGenerator->expects('generate')
            ->with('app_password_reset', ['token' => 'abc123'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->andReturn('https://ooo.com/password-reset/abc123');

        $this->mailer->expects('send')
            ->withArgs(fn (TemplatedEmail $email): bool => 'user@whoisooo.app' === $email->getTo()[0]->getAddress())
            ->once();

        $this->handler->handle('user@whoisooo.app', 'abc123');
    });

    it('generates correct reset URL', function (): void {
        $this->urlGenerator->allows('setContext');
        $this->urlGenerator->expects('generate')
            ->with('app_password_reset', ['token' => 'token-xyz'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->andReturn('https://ooo.com/password-reset/token-xyz')
            ->once();

        $this->mailer->expects('send')->once();

        $this->handler->handle('user@whoisooo.app', 'token-xyz');
    });

    it('uses correct from address', function (): void {
        $this->urlGenerator->allows('setContext');
        $this->urlGenerator->allows('generate')->andReturn('https://ooo.com/reset');

        $this->mailer->expects('send')
            ->withArgs(function (TemplatedEmail $email): bool {
                $from = $email->getFrom()[0];

                return 'noreply@whoisooo.app' === $from->getAddress()
                    && "Who's OOO" === $from->getName();
            })
            ->once();

        $this->handler->handle('user@whoisooo.app', 'token');
    });

    it('uses correct email template', function (): void {
        $this->urlGenerator->allows('setContext');
        $this->urlGenerator->allows('generate')->andReturn('https://ooo.com/reset');

        $this->mailer->expects('send')
            ->withArgs(fn (TemplatedEmail $email): bool => '@AppEmail/password_reset.html.twig' === $email->getHtmlTemplate())
            ->once();

        $this->handler->handle('user@whoisooo.app', 'token');
    });
});

it('generates the reset link against APP_BASE_URL instead of the request host', function (): void {
    $appliedContexts = [];
    $this->urlGenerator->allows('setContext')->andReturnUsing(function (RequestContext $context) use (&$appliedContexts): void {
        $appliedContexts[] = $context;
    });
    $this->urlGenerator->expects('generate')
        ->with('app_password_reset', ['token' => 'raw-token'], UrlGeneratorInterface::ABSOLUTE_URL)
        ->andReturnUsing(function () use (&$appliedContexts): string {
            $context = end($appliedContexts);

            return sprintf('%s://%s/password-reset/raw-token', $context->getScheme(), $context->getHost());
        });
    $this->mailer->expects('send')->withArgs(function (TemplatedEmail $email): bool {
        expect($email->getContext()['reset_url'])->toBe('https://leave.example.com/password-reset/raw-token')
            ->and($email->getTo()[0]->getAddress())->toBe('user@whoisooo.app');

        return true;
    });

    $this->handler->handle('user@whoisooo.app', 'raw-token');
});

it('restores the previous router context after generating the link', function (): void {
    $appliedContexts = [];
    $this->urlGenerator->allows('setContext')->andReturnUsing(function (RequestContext $context) use (&$appliedContexts): void {
        $appliedContexts[] = $context;
    });
    $this->urlGenerator->allows('generate')->andReturn('https://leave.example.com/password-reset/raw-token');
    $this->mailer->allows('send');

    $this->handler->handle('user@whoisooo.app', 'raw-token');

    expect($appliedContexts)->toHaveCount(2)
        ->and(end($appliedContexts))->toBe($this->requestContext);
});

it('restores the previous router context when link generation fails', function (): void {
    $appliedContexts = [];
    $this->urlGenerator->allows('setContext')->andReturnUsing(function (RequestContext $context) use (&$appliedContexts): void {
        $appliedContexts[] = $context;
    });
    $this->urlGenerator->allows('generate')->andThrow(new RuntimeException('route missing'));
    $this->mailer->expects('send')->never();

    try {
        $this->handler->handle('user@whoisooo.app', 'raw-token');
    } catch (RuntimeException) {
    }

    expect(end($appliedContexts))->toBe($this->requestContext);
});

it('falls back to the request host in prod when APP_BASE_URL is localhost, and logs an error', function (): void {
    $handler = new SendPasswordResetEmailCommandHandler(
        emailFromAddress: 'noreply@whoisooo.app',
        emailFromName: "Who's OOO",
        appBaseUrl: 'http://localhost',
        environment: AppEnvironmentEnum::PROD->value,
        mailer: $this->mailer,
        urlGenerator: $this->urlGenerator,
        translator: $this->translator,
        logger: $this->logger,
    );

    $this->urlGenerator->expects('setContext')->never();
    $this->urlGenerator->expects('generate')
        ->with('app_password_reset', ['token' => 'raw-token'], UrlGeneratorInterface::ABSOLUTE_URL)
        ->andReturn('https://evil.tld/password-reset/raw-token');
    $this->logger->expects('error')
        ->withArgs(fn (string $message): bool => str_contains($message, '[EMAIL][PASSWORD-RESET]'))
        ->once();
    $this->mailer->expects('send')->once();

    $handler->handle('user@whoisooo.app', 'raw-token');
});

it('falls back to the request host in prod when APP_BASE_URL has no parsable host, and logs an error', function (string $appBaseUrl): void {
    $handler = new SendPasswordResetEmailCommandHandler(
        emailFromAddress: 'noreply@whoisooo.app',
        emailFromName: "Who's OOO",
        appBaseUrl: $appBaseUrl,
        environment: AppEnvironmentEnum::PROD->value,
        mailer: $this->mailer,
        urlGenerator: $this->urlGenerator,
        translator: $this->translator,
        logger: $this->logger,
    );

    $this->urlGenerator->expects('setContext')->never();
    $this->urlGenerator->expects('generate')
        ->with('app_password_reset', ['token' => 'raw-token'], UrlGeneratorInterface::ABSOLUTE_URL)
        ->andReturn('https://evil.tld/password-reset/raw-token');
    $this->logger->expects('error')
        ->withArgs(fn (string $message): bool => str_contains($message, '[EMAIL][PASSWORD-RESET]')
            && str_contains($message, $appBaseUrl))
        ->once();
    $this->mailer->expects('send')
        ->withArgs(function (TemplatedEmail $email): bool {
            expect($email->getContext()['reset_url'])->toBe('https://evil.tld/password-reset/raw-token');

            return true;
        })
        ->once();

    $handler->handle('user@whoisooo.app', 'raw-token');
})->with([
    'empty value' => [''],
    'schemeless host' => ['leave.example.com'],
    'path only' => ['/app/dashboard'],
]);

it('swaps the router context in prod when APP_BASE_URL is a real public host, and does not log', function (): void {
    $handler = new SendPasswordResetEmailCommandHandler(
        emailFromAddress: 'noreply@whoisooo.app',
        emailFromName: "Who's OOO",
        appBaseUrl: 'https://leave.example.com',
        environment: AppEnvironmentEnum::PROD->value,
        mailer: $this->mailer,
        urlGenerator: $this->urlGenerator,
        translator: $this->translator,
        logger: $this->logger,
    );

    $appliedContexts = [];
    $this->urlGenerator->expects('setContext')->twice()->andReturnUsing(function (RequestContext $context) use (&$appliedContexts): void {
        $appliedContexts[] = $context;
    });
    $this->urlGenerator->expects('generate')
        ->with('app_password_reset', ['token' => 'raw-token'], UrlGeneratorInterface::ABSOLUTE_URL)
        ->andReturnUsing(function () use (&$appliedContexts): string {
            $context = end($appliedContexts);

            return sprintf('%s://%s/password-reset/raw-token', $context->getScheme(), $context->getHost());
        });
    $this->logger->expects('error')->never();
    $this->mailer->expects('send')->withArgs(function (TemplatedEmail $email): bool {
        expect($email->getContext()['reset_url'])->toBe('https://leave.example.com/password-reset/raw-token');

        return true;
    })->once();

    $handler->handle('user@whoisooo.app', 'raw-token');

    expect($appliedContexts[0]->getHost())->toBe('leave.example.com')
        ->and(end($appliedContexts))->toBe($this->requestContext);
});

it('swaps the router context in dev when APP_BASE_URL is localhost, and does not log', function (): void {
    $handler = new SendPasswordResetEmailCommandHandler(
        emailFromAddress: 'noreply@whoisooo.app',
        emailFromName: "Who's OOO",
        appBaseUrl: 'http://localhost',
        environment: 'dev',
        mailer: $this->mailer,
        urlGenerator: $this->urlGenerator,
        translator: $this->translator,
        logger: $this->logger,
    );

    $appliedContexts = [];
    $this->urlGenerator->expects('setContext')->twice()->andReturnUsing(function (RequestContext $context) use (&$appliedContexts): void {
        $appliedContexts[] = $context;
    });
    $this->urlGenerator->expects('generate')
        ->with('app_password_reset', ['token' => 'raw-token'], UrlGeneratorInterface::ABSOLUTE_URL)
        ->andReturnUsing(function () use (&$appliedContexts): string {
            $context = end($appliedContexts);

            return sprintf('%s://%s/password-reset/raw-token', $context->getScheme(), $context->getHost());
        });
    $this->logger->expects('error')->never();
    $this->mailer->expects('send')->once();

    $handler->handle('user@whoisooo.app', 'raw-token');

    expect($appliedContexts[0]->getHost())->toBe('localhost')
        ->and(end($appliedContexts))->toBe($this->requestContext);
});

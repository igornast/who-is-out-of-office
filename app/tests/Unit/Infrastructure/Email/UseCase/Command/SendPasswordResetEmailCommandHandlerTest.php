<?php

declare(strict_types=1);

use App\Infrastructure\Email\UseCase\Command\SendPasswordResetEmailCommandHandler;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

beforeEach(function (): void {
    $this->mailer = mock(MailerInterface::class);
    $this->urlGenerator = mock(UrlGeneratorInterface::class);
    $this->translator = mock(TranslatorInterface::class);
    $this->translator->allows('trans')->andReturnUsing(fn (string $id) => $id);

    $this->handler = new SendPasswordResetEmailCommandHandler(
        'noreply@whoisooo.app',
        "Who's OOO",
        $this->mailer,
        $this->urlGenerator,
        $this->translator,
    );
});

describe('SendPasswordResetEmailCommandHandler', function (): void {
    it('sends email with correct recipient', function (): void {
        $this->urlGenerator->expects('generate')
            ->with('app_password_reset', ['token' => 'abc123'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->andReturn('https://ooo.com/password-reset/abc123');

        $this->mailer->expects('send')
            ->withArgs(fn (TemplatedEmail $email): bool => 'user@whoisooo.app' === $email->getTo()[0]->getAddress())
            ->once();

        $this->handler->handle('user@whoisooo.app', 'abc123');
    });

    it('generates correct reset URL', function (): void {
        $this->urlGenerator->expects('generate')
            ->with('app_password_reset', ['token' => 'token-xyz'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->andReturn('https://ooo.com/password-reset/token-xyz')
            ->once();

        $this->mailer->expects('send')->once();

        $this->handler->handle('user@whoisooo.app', 'token-xyz');
    });

    it('uses correct from address', function (): void {
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
        $this->urlGenerator->allows('generate')->andReturn('https://ooo.com/reset');

        $this->mailer->expects('send')
            ->withArgs(fn (TemplatedEmail $email): bool => '@AppEmail/password_reset.html.twig' === $email->getHtmlTemplate())
            ->once();

        $this->handler->handle('user@whoisooo.app', 'token');
    });
});

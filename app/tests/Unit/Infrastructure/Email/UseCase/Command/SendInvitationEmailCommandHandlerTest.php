<?php

declare(strict_types=1);

use App\Infrastructure\Email\UseCase\Command\SendInvitationEmailCommandHandler;
use App\Tests\_fixtures\Shared\DTO\InvitationDTOFixture;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

beforeEach(function (): void {
    $this->mailer = mock(MailerInterface::class);
    $this->urlGenerator = mock(UrlGeneratorInterface::class);
    $this->translator = mock(TranslatorInterface::class);
    $this->translator->allows('trans')->andReturnUsing(fn (string $id) => $id);
    $this->logger = mock(LoggerInterface::class);

    $this->handler = new SendInvitationEmailCommandHandler(
        emailFromAddress: 'noreply@whosooo.com',
        emailFromName: "Who's OOO",
        mailer: $this->mailer,
        urlGenerator: $this->urlGenerator,
        translator: $this->translator,
        logger: $this->logger,
    );
});

it('sends email with correct recipient', function () {
    $invitationDTO = InvitationDTOFixture::create();

    $this->urlGenerator
        ->expects('generate')
        ->once()
        ->andReturn('https://example.com/invitation/abc123');

    $this->mailer
        ->expects('send')
        ->once()
        ->withArgs(function (TemplatedEmail $email) use ($invitationDTO) {
            expect($email->getTo()[0]->getAddress())->toBe($invitationDTO->user->email);

            return true;
        });

    $this->handler->handle($invitationDTO);
});

it('sends email with correct invitation URL', function () {
    $invitationDTO = InvitationDTOFixture::create();
    $expectedUrl = 'https://example.com/invitation/token-123';

    $this->urlGenerator
        ->expects('generate')
        ->once()
        ->with(
            'app_user_invitation',
            ['token' => $invitationDTO->token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        )
        ->andReturn($expectedUrl);

    $this->mailer
        ->expects('send')
        ->once()
        ->withArgs(function (TemplatedEmail $email) use ($expectedUrl) {
            expect($email->getContext()['invitation_url'])->toBe($expectedUrl);

            return true;
        });

    $this->handler->handle($invitationDTO);
});

it('uses correct from address', function () {
    $invitationDTO = InvitationDTOFixture::create();

    $this->urlGenerator
        ->expects('generate')
        ->andReturn('https://example.com/invitation/abc');

    $this->mailer
        ->expects('send')
        ->once()
        ->withArgs(function (TemplatedEmail $email) {
            $from = $email->getFrom()[0];
            expect($from->getAddress())->toBe('noreply@whosooo.com')
                ->and($from->getName())->toBe("Who's OOO");

            return true;
        });

    $this->handler->handle($invitationDTO);
});

it('uses correct template', function () {
    $invitationDTO = InvitationDTOFixture::create();

    $this->urlGenerator
        ->expects('generate')
        ->andReturn('https://example.com/invitation/abc');

    $this->mailer
        ->expects('send')
        ->once()
        ->withArgs(function (TemplatedEmail $email) {
            expect($email->getHtmlTemplate())->toBe('@AppEmail/invitation.html.twig');

            return true;
        });

    $this->handler->handle($invitationDTO);
});

it('logs error when mailer transport fails', function () {
    $invitationDTO = InvitationDTOFixture::create();

    $this->urlGenerator
        ->expects('generate')
        ->andReturn('https://example.com/invitation/abc');

    $this->mailer
        ->expects('send')
        ->andThrow(new TransportException('SMTP connection refused'));

    $this->logger
        ->expects('error')
        ->once()
        ->withArgs(fn (string $message) => str_contains($message, 'SMTP connection refused')
            && str_contains($message, $invitationDTO->user->email));

    $this->handler->handle($invitationDTO);
});

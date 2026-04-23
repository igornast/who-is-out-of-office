<?php

declare(strict_types=1);

use App\Infrastructure\Email\EventListener\EmailContextListener;
use App\Shared\Facade\AppSettingsFacadeInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

beforeEach(function (): void {
    $this->settingsFacade = mock(AppSettingsFacadeInterface::class);
    $this->listener = new EmailContextListener($this->settingsFacade);
});

function buildMessageEvent(Symfony\Component\Mime\RawMessage $message): MessageEvent
{
    return new MessageEvent(
        $message,
        new Envelope(new Address('from@example.com'), [new Address('to@example.com')]),
        'smtp://null',
    );
}

it('injects company_name into TemplatedEmail context from settings facade', function () {
    $this->settingsFacade
        ->expects('organizationName')
        ->once()
        ->andReturn('Acme Inc.');

    $templated = new TemplatedEmail()
        ->from('from@example.com')
        ->to('to@example.com')
        ->htmlTemplate('@AppEmail/invitation.html.twig')
        ->context(['invitation_url' => 'https://example.com/i/1']);

    $event = buildMessageEvent($templated);

    $this->listener->__invoke($event);

    $context = $templated->getContext();
    expect($context['company_name'])->toBe('Acme Inc.')
        ->and($context['invitation_url'])->toBe('https://example.com/i/1');
});

it('preserves an existing company_name in context without overwriting it', function () {
    $this->settingsFacade->shouldNotReceive('organizationName');

    $templated = new TemplatedEmail()
        ->from('from@example.com')
        ->to('to@example.com')
        ->htmlTemplate('@AppEmail/invitation.html.twig')
        ->context(['company_name' => 'Override Inc.']);

    $event = buildMessageEvent($templated);

    $this->listener->__invoke($event);

    expect($templated->getContext()['company_name'])->toBe('Override Inc.');
});

it('ignores non-TemplatedEmail messages', function () {
    $this->settingsFacade->shouldNotReceive('organizationName');

    $plain = new Email()
        ->from('from@example.com')
        ->to('to@example.com')
        ->text('hello');

    $event = buildMessageEvent($plain);

    $this->listener->__invoke($event);

    expect(true)->toBeTrue();
});

it('skips injection when organization name is an empty string', function () {
    $this->settingsFacade
        ->expects('organizationName')
        ->once()
        ->andReturn('');

    $templated = new TemplatedEmail()
        ->from('from@example.com')
        ->to('to@example.com')
        ->htmlTemplate('@AppEmail/invitation.html.twig')
        ->context(['invitation_url' => 'https://example.com/i/1']);

    $event = buildMessageEvent($templated);

    $this->listener->__invoke($event);

    expect($templated->getContext())->not->toHaveKey('company_name');
});

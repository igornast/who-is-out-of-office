<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Security\EventListener\TwoFactorAuthenticationListener;
use App\Infrastructure\Security\TotpSecretEncryptor;
use Ramsey\Uuid\Uuid;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\UserPassportInterface;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;

beforeEach(function (): void {
    $this->encryptor = mock(TotpSecretEncryptor::class);
    $this->listener = new TwoFactorAuthenticationListener($this->encryptor);
});

function makeUser(bool $twoFactorEnabled = true, ?string $totpSecret = 'encrypted-data'): User
{
    return new User(
        id: Uuid::uuid4(),
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@example.com',
        password: 'hashed',
        totpSecret: $totpSecret,
        isTwoFactorEnabled: $twoFactorEnabled,
    );
}

function makeTokenCreatedEvent(?Symfony\Component\Security\Core\User\UserInterface $user): AuthenticationTokenCreatedEvent
{
    $token = mock(TokenInterface::class);
    $token->allows('getUser')->andReturn($user);

    $passport = mock(Passport::class, UserPassportInterface::class);

    return new AuthenticationTokenCreatedEvent($token, $passport);
}

function makeSuccessEvent(?Symfony\Component\Security\Core\User\UserInterface $user): TwoFactorAuthenticationEvent
{
    $token = mock(TokenInterface::class);
    $token->allows('getUser')->andReturn($user);

    return new TwoFactorAuthenticationEvent(new Request(), $token);
}

it('decrypts and sets the TOTP secret when an authentication token is created', function (): void {
    $user = makeUser();

    $this->encryptor
        ->expects('decrypt')
        ->once()
        ->with('encrypted-data')
        ->andReturn('JBSWY3DPEHPK3PXP');

    $this->listener->onAuthenticationTokenCreated(makeTokenCreatedEvent($user));

    expect($user->getTotpAuthenticationConfiguration())->not->toBeNull();
});

it('skips decryption when 2FA is not enabled on the user', function (): void {
    $user = makeUser(twoFactorEnabled: false, totpSecret: null);

    $this->encryptor->shouldNotReceive('decrypt');

    $this->listener->onAuthenticationTokenCreated(makeTokenCreatedEvent($user));

    expect($user->getTotpAuthenticationConfiguration())->toBeNull();
});

it('skips decryption when the token user is not a User entity', function (): void {
    $this->encryptor->shouldNotReceive('decrypt');

    $this->listener->onAuthenticationTokenCreated(makeTokenCreatedEvent(null));
})->throwsNoExceptions();

it('clears the decrypted TOTP secret after successful 2FA', function (): void {
    $user = makeUser();
    $user->setDecryptedTotpSecret('JBSWY3DPEHPK3PXP');

    $this->encryptor->shouldNotReceive('decrypt');

    $this->listener->onSuccess(makeSuccessEvent($user));

    expect($user->getTotpAuthenticationConfiguration())->toBeNull();
});

it('does nothing on success when the token user is not a User entity', function (): void {
    $this->encryptor->shouldNotReceive('decrypt');

    $this->listener->onSuccess(makeSuccessEvent(null));
})->throwsNoExceptions();

it('registers onAuthenticationTokenCreated on the firewall dispatcher with priority above scheb', function (): void {
    $reflection = new ReflectionMethod(TwoFactorAuthenticationListener::class, 'onAuthenticationTokenCreated');
    $attributes = $reflection->getAttributes(Symfony\Component\EventDispatcher\Attribute\AsEventListener::class);

    expect($attributes)->toHaveCount(1);

    $instance = $attributes[0]->newInstance();

    expect($instance->event)->toBe(AuthenticationTokenCreatedEvent::class)
        ->and($instance->dispatcher)->toBe('security.event_dispatcher.main')
        ->and($instance->priority)->toBeGreaterThan(0);
});

it('registers onSuccess on the scheb SUCCESS event', function (): void {
    $reflection = new ReflectionMethod(TwoFactorAuthenticationListener::class, 'onSuccess');
    $attributes = $reflection->getAttributes(Symfony\Component\EventDispatcher\Attribute\AsEventListener::class);

    expect($attributes)->toHaveCount(1);

    $instance = $attributes[0]->newInstance();

    expect($instance->event)->toBe(TwoFactorAuthenticationEvents::SUCCESS);
});

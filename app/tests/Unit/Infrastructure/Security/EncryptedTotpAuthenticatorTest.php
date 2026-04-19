<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Security\EncryptedTotpAuthenticator;
use App\Infrastructure\Security\TotpSecretEncryptor;
use Ramsey\Uuid\Uuid;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

beforeEach(function (): void {
    $this->inner = mock(TotpAuthenticatorInterface::class);
    $this->encryptor = mock(TotpSecretEncryptor::class);
    $this->authenticator = new EncryptedTotpAuthenticator($this->inner, $this->encryptor);
});

it('decrypts the secret before delegating checkCode to inner authenticator', function (): void {
    $user = new User(
        id: Uuid::uuid4(),
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@example.com',
        password: 'hashed',
        totpSecret: 'encrypted-data',
        isTwoFactorEnabled: true,
    );

    $this->encryptor
        ->expects('decrypt')
        ->once()
        ->with('encrypted-data')
        ->andReturn('JBSWY3DPEHPK3PXP');

    $this->inner
        ->expects('checkCode')
        ->once()
        ->andReturn(true);

    expect($this->authenticator->checkCode($user, '123456'))->toBeTrue();
});

it('skips decryption for users without 2FA', function (): void {
    $user = new User(
        id: Uuid::uuid4(),
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@example.com',
        password: 'hashed',
    );

    $this->encryptor->shouldNotReceive('decrypt');

    $this->inner
        ->expects('checkCode')
        ->once()
        ->andReturn(false);

    expect($this->authenticator->checkCode($user, '123456'))->toBeFalse();
});

it('decrypts the secret before delegating getQRContent to inner authenticator', function (): void {
    $user = new User(
        id: Uuid::uuid4(),
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@example.com',
        password: 'hashed',
        totpSecret: 'encrypted-data',
        isTwoFactorEnabled: true,
    );

    $this->encryptor
        ->expects('decrypt')
        ->once()
        ->with('encrypted-data')
        ->andReturn('JBSWY3DPEHPK3PXP');

    $this->inner
        ->expects('getQRContent')
        ->once()
        ->andReturn('otpauth://totp/issuer:john@example.com?secret=JBSWY3DPEHPK3PXP');

    expect($this->authenticator->getQRContent($user))
        ->toBe('otpauth://totp/issuer:john@example.com?secret=JBSWY3DPEHPK3PXP');
});

it('skips decryption in getQRContent for users without 2FA', function (): void {
    $user = new User(
        id: Uuid::uuid4(),
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@example.com',
        password: 'hashed',
    );

    $this->encryptor->shouldNotReceive('decrypt');

    $this->inner
        ->expects('getQRContent')
        ->once()
        ->andReturn('otpauth://totp/issuer:john@example.com');

    expect($this->authenticator->getQRContent($user))->toBe('otpauth://totp/issuer:john@example.com');
});

it('delegates generateSecret to the inner authenticator', function (): void {
    $this->encryptor->shouldNotReceive('decrypt');

    $this->inner
        ->expects('generateSecret')
        ->once()
        ->andReturn('GENERATED-SECRET');

    expect($this->authenticator->generateSecret())->toBe('GENERATED-SECRET');
});

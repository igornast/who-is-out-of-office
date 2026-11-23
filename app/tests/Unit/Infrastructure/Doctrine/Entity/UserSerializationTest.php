<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use Ramsey\Uuid\Uuid;

function makeUserWithSecrets(): User
{
    $user = new User(
        id: Uuid::uuid4(),
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@example.com',
        password: 'hashed-password',
        isTwoFactorEnabled: true,
        plainPassword: 'plain-secret-password',
        totpSecret: 'encrypted-totp-secret',
        roles: ['ROLE_MANAGER'],
    );
    $user->setDecryptedTotpSecret('JBSWY3DPEHPK3PXP');

    return $user;
}

it('leaves the plain password and decrypted TOTP secret out of the serialized payload', function (): void {
    $payload = serialize(makeUserWithSecrets());

    expect($payload)->not->toContain('plain-secret-password')
        ->and($payload)->not->toContain('JBSWY3DPEHPK3PXP')
        ->and($payload)->not->toContain('plainPassword')
        ->and($payload)->not->toContain('decryptedTotpSecret');
});

it('keeps the remaining fields when unserializing', function (): void {
    $original = makeUserWithSecrets();

    $restored = unserialize(serialize($original));

    expect($restored)->toBeInstanceOf(User::class)
        ->and($restored->id->toString())->toBe($original->id->toString())
        ->and($restored->email)->toBe('john@example.com')
        ->and($restored->password)->toBe('hashed-password')
        ->and($restored->totpSecret)->toBe('encrypted-totp-secret')
        ->and($restored->isTwoFactorEnabled)->toBeTrue()
        ->and($restored->getRoles())->toBe(['ROLE_MANAGER', 'ROLE_USER'])
        ->and($restored->plainPassword)->toBeNull()
        ->and($restored->getTotpAuthenticationConfiguration())->toBeNull();
});

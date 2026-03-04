<?php

declare(strict_types=1);

use App\Shared\Service\Ical\IcalHashGenerator;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

it('generates a hash for a user', function () {
    $userDTO = UserDTOFixture::create([
        'id' => '123',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:30:00+00:00'),
    ]);
    $secret = 'test-secret';

    $hash = IcalHashGenerator::generateForUser($userDTO, $secret);

    expect($hash)->toBeString()
        ->and($hash)->toHaveLength(64)
        ->and($hash)->toMatch('/^[a-f0-9]{64}$/');
});

it('generates consistent hash for same user and secret', function () {
    $userDTO = UserDTOFixture::create([
        'id' => '123',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:30:00+00:00'),
    ]);
    $secret = 'test-secret';

    $hash1 = IcalHashGenerator::generateForUser($userDTO, $secret);
    $hash2 = IcalHashGenerator::generateForUser($userDTO, $secret);

    expect($hash1)->toBe($hash2);
});

it('generates different hash for different users', function () {
    $user1 = UserDTOFixture::create([
        'id' => '123',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:30:00+00:00'),
    ]);
    $user2 = UserDTOFixture::create([
        'id' => '456',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:30:00+00:00'),
    ]);
    $secret = 'test-secret';

    $hash1 = IcalHashGenerator::generateForUser($user1, $secret);
    $hash2 = IcalHashGenerator::generateForUser($user2, $secret);

    expect($hash1)->not()->toBe($hash2);
});

it('generates different hash for different secrets', function () {
    $userDTO = UserDTOFixture::create([
        'id' => '123',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:30:00+00:00'),
    ]);

    $hash1 = IcalHashGenerator::generateForUser($userDTO, 'secret1');
    $hash2 = IcalHashGenerator::generateForUser($userDTO, 'secret2');

    expect($hash1)->not()->toBe($hash2);
});

it('generates different hash for different createdAt dates', function () {
    $user1 = UserDTOFixture::create([
        'id' => '123',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:30:00+00:00'),
    ]);
    $user2 = UserDTOFixture::create([
        'id' => '123',
        'createdAt' => new DateTimeImmutable('2025-01-16T10:30:00+00:00'),
    ]);
    $secret = 'test-secret';

    $hash1 = IcalHashGenerator::generateForUser($user1, $secret);
    $hash2 = IcalHashGenerator::generateForUser($user2, $secret);

    expect($hash1)->not()->toBe($hash2);
});

it('generates expected hash value for known input', function () {
    $userDTO = UserDTOFixture::create([
        'id' => '123',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:30:00+00:00'),
    ]);
    $secret = 'test-secret';

    $expectedHash = hash_hmac('sha256', '123|2025-01-15T10:30:00+00:00', 'test-secret');

    $hash = IcalHashGenerator::generateForUser($userDTO, $secret);

    expect($hash)->toBe($expectedHash);
});

it('uses icalHashSalt instead of createdAt when salt is set', function () {
    $userDTO = UserDTOFixture::create([
        'id' => '123',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:30:00+00:00'),
        'icalHashSalt' => 'my-random-salt',
    ]);
    $secret = 'test-secret';

    $expectedHash = hash_hmac('sha256', '123|my-random-salt', 'test-secret');

    $hash = IcalHashGenerator::generateForUser($userDTO, $secret);

    expect($hash)->toBe($expectedHash);
});

it('generates different hash after salt is set compared to createdAt-based hash', function () {
    $baseAttributes = [
        'id' => '123',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:30:00+00:00'),
    ];
    $secret = 'test-secret';

    $userWithoutSalt = UserDTOFixture::create($baseAttributes);
    $userWithSalt = UserDTOFixture::create(array_merge($baseAttributes, ['icalHashSalt' => 'some-salt']));

    $hashWithout = IcalHashGenerator::generateForUser($userWithoutSalt, $secret);
    $hashWith = IcalHashGenerator::generateForUser($userWithSalt, $secret);

    expect($hashWithout)->not()->toBe($hashWith);
});

it('generates different hash for different salts', function () {
    $user1 = UserDTOFixture::create([
        'id' => '123',
        'icalHashSalt' => 'salt-one',
    ]);
    $user2 = UserDTOFixture::create([
        'id' => '123',
        'icalHashSalt' => 'salt-two',
    ]);
    $secret = 'test-secret';

    $hash1 = IcalHashGenerator::generateForUser($user1, $secret);
    $hash2 = IcalHashGenerator::generateForUser($user2, $secret);

    expect($hash1)->not()->toBe($hash2);
});

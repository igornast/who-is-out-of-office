<?php

declare(strict_types=1);

use App\Shared\Enum\RoleEnum;
use App\Shared\Service\RoleTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;

beforeEach(function (): void {
    $this->translator = mock(TranslatorInterface::class);

    $this->roleTranslator = new RoleTranslator(translator: $this->translator);
});

it('translates a single role', function () {
    $this->translator
        ->expects('trans')
        ->once()
        ->withArgs(fn (string $id, array $params, ?string $domain) => 'role.admin' === $id && 'admin' === $domain)
        ->andReturn('Administrator');

    $result = $this->roleTranslator->translate([RoleEnum::Admin->value]);

    expect($result)->toBe('Administrator');
});

it('translates multiple roles separated by comma', function () {
    $this->translator
        ->expects('trans')
        ->withArgs(fn (string $id) => 'role.admin' === $id)
        ->andReturn('Administrator');

    $this->translator
        ->expects('trans')
        ->withArgs(fn (string $id) => 'role.manager' === $id)
        ->andReturn('Manager');

    $result = $this->roleTranslator->translate([RoleEnum::Admin->value, RoleEnum::Manager->value]);

    expect($result)->toBe('Administrator, Manager');
});

it('passes unknown role as translation key', function () {
    $this->translator
        ->expects('trans')
        ->withArgs(fn (string $id) => 'ROLE_UNKNOWN' === $id)
        ->andReturn('ROLE_UNKNOWN');

    $result = $this->roleTranslator->translate(['ROLE_UNKNOWN']);

    expect($result)->toBe('ROLE_UNKNOWN');
});

it('returns empty string for empty roles array', function () {
    $result = $this->roleTranslator->translate([]);

    expect($result)->toBe('');
});

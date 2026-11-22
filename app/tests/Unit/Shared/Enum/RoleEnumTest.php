<?php

declare(strict_types=1);

use App\Shared\Enum\RoleEnum;

it('maps every role to its translation key', function (RoleEnum $role, string $key): void {
    expect($role->translationKey())->toBe($key);
})->with([
    [RoleEnum::Admin, 'role.admin'],
    [RoleEnum::Manager, 'role.manager'],
    [RoleEnum::User, 'role.user'],
]);

it('offers administrator and manager as assignable choices, never user', function (): void {
    expect(RoleEnum::assignableChoices())->toBe([
        'role.admin' => 'ROLE_ADMIN',
        'role.manager' => 'ROLE_MANAGER',
    ]);
});

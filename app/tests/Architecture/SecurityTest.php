<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Security Architecture Tests
|--------------------------------------------------------------------------
|
| These tests ensure security best practices are followed.
|
*/

describe('Security Best Practices', function () {
    arch('should not use eval')
        ->expect('App')
        ->not->toUse('eval');

    arch('should not use exec')
        ->expect('App')
        ->not->toUse('exec');

    arch('should not use system')
        ->expect('App')
        ->not->toUse('system');

    arch('should not use shell_exec')
        ->expect('App')
        ->not->toUse('shell_exec');

    arch('controllers should use proper authentication')
        ->expect('App\Module\*\Controller')
        ->not->toUse('symfony\component\security\core\security');

    arch('password handling should use hasher')
        ->expect('App')
        ->not->toUse([
            'md5',
            'sha1',
            'password_hash', // Use Symfony's PasswordHasherInterface instead
        ]);
});

describe('SQL Injection Prevention', function () {
    arch('repositories should use query builder or DQL')
        ->expect('App\Module\*\Repository')
        ->not->toUse('mysqli_query');

    arch('should not use raw SQL concatenation')
        ->expect('App\Module\*\Repository')
        ->not->toUse([
            'sprintf',
            'concatenate',
        ]);
});

describe('Data Validation', function () {
    arch('DTOs should be validated')
        ->expect('App\Shared\DTO')
        ->toOnlyBeUsedIn([
            'App\Module',
            'App\Infrastructure',
            'App\Shared',
            'App\Tests',
            'App\DataFixtures',
        ]);

    arch('controllers should not trust user input directly')
        ->expect('App\Module\*\Controller')
        ->toUse([
            'App\Shared\DTO',
            'App\Module\*\Form',
        ]);
});

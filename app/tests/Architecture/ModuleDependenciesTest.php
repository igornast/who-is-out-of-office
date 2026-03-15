<?php

declare(strict_types=1);

describe('Module Independence', function () {
    arch('Admin module should not depend on other modules directly')
        ->expect('App\Module\Admin')
        ->not->toUse([
            'App\Module\Holiday',
            'App\Module\LeaveRequest',
            'App\Module\Settings',
        ]);

    arch('User module should not depend on other modules directly')
        ->expect('App\Module\User')
        ->not->toUse([
            'App\Module\Admin',
            'App\Module\Holiday',
            'App\Module\LeaveRequest',
            'App\Module\Settings',
        ]);

    arch('LeaveRequest module should not depend on other modules directly')
        ->expect('App\Module\LeaveRequest')
        ->not->toUse([
            'App\Module\Admin',
            'App\Module\User',
            'App\Module\Holiday',
            'App\Module\Settings',
        ]);

    arch('Holiday module should not depend on other modules directly')
        ->expect('App\Module\Holiday')
        ->not->toUse([
            'App\Module\Admin',
            'App\Module\User',
            'App\Module\LeaveRequest',
            'App\Module\Settings',
        ]);

    arch('Settings module should not depend on other modules directly')
        ->expect('App\Module\Settings')
        ->not->toUse([
            'App\Module\Admin',
            'App\Module\User',
            'App\Module\LeaveRequest',
            'App\Module\Holiday',
        ]);
});

arch('modules should not use Doctrine entities from Infrastructure in business logic', function (string $module) {
    expect(sprintf('App\Module\%s\UseCase', $module))
        ->not->toUse('App\Infrastructure\Doctrine\Entity');
})->with(modules());


describe('Facade Pattern Enforcement', function () {
    arch('LeaveRequest facade should implement interface and be final')
        ->expect('App\Module\LeaveRequest\LeaveRequestFacade')
        ->toImplement('App\Shared\Facade\LeaveRequestFacadeInterface')
        ->toBeFinal();

    arch('User facade should implement interface and be final')
        ->expect('App\Module\User\UserFacade')
        ->toImplement('App\Shared\Facade\UserFacadeInterface')
        ->toBeFinal();

    arch('Holiday facade should implement interface and be final')
        ->expect('App\Module\Holiday\HolidayFacade')
        ->toImplement('App\Shared\Facade\HolidayFacadeInterface')
        ->toBeFinal();

    arch('Settings facade should implement interface and be final')
        ->expect('App\Module\Settings\SettingsFacade')
        ->toImplement('App\Shared\Facade\AppSettingsFacadeInterface')
        ->toBeFinal();

    arch('Email facade should implement interface and be final')
        ->expect('App\Infrastructure\Email\EmailFacade')
        ->toImplement('App\Shared\Facade\EmailFacadeInterface')
        ->toBeFinal();

    arch('Slack facade should implement interface and be final')
        ->expect('App\Infrastructure\Slack\SlackFacade')
        ->toImplement('App\Shared\Facade\SlackFacadeInterface')
        ->toBeFinal();

    arch('DateNager facade should implement interface and be final')
        ->expect('App\Infrastructure\DataNager\DateNagerFacade')
        ->toImplement('App\Shared\Facade\DateNagerInterface')
        ->toBeFinal();
});

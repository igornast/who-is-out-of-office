<?php

declare(strict_types=1);

use App\Shared\DTO\UserDTO;

it('creates from array with subdivisionCode', function () {
    $data = [
        'id' => 'user-1',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'roles' => '["ROLE_USER"]',
        'working_days' => '[1,2,3,4,5]',
        'annual_leave_allowance' => 24,
        'current_leave_balance' => 20,
        'is_active' => 1,
        'created_at' => '2025-01-01 00:00:00',
        'profile_image_url' => null,
        'birth_date' => null,
        'absence_balance_reset_day' => '2025-01-01',
        'subdivision_code' => 'DE-BY',
    ];

    $dto = UserDTO::fromArray($data);

    expect($dto->subdivisionCode)->toBe('DE-BY');
});

it('defaults subdivisionCode to null when not present in array', function () {
    $data = [
        'id' => 'user-2',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'roles' => '["ROLE_USER"]',
        'working_days' => '[1,2,3,4,5]',
        'annual_leave_allowance' => 24,
        'current_leave_balance' => 20,
        'is_active' => 1,
        'created_at' => '2025-01-01 00:00:00',
        'profile_image_url' => null,
        'birth_date' => null,
        'absence_balance_reset_day' => '2025-01-01',
    ];

    $dto = UserDTO::fromArray($data);

    expect($dto->subdivisionCode)->toBeNull();
});

it('decodes calendar_subscription_team_member_ids and calendar_subscription_holiday_calendar_ids from JSON strings', function () {
    $data = [
        'id' => 'user-3',
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'email' => 'alice@example.com',
        'roles' => '["ROLE_USER"]',
        'working_days' => '[1,2,3,4,5]',
        'annual_leave_allowance' => 24,
        'current_leave_balance' => 20,
        'is_active' => 1,
        'created_at' => '2025-01-01 00:00:00',
        'profile_image_url' => null,
        'birth_date' => null,
        'absence_balance_reset_day' => '2025-01-01',
        'calendar_subscription_team_member_ids' => '["a","b"]',
        'calendar_subscription_holiday_calendar_ids' => '["cal-1","cal-2"]',
    ];

    $dto = UserDTO::fromArray($data);

    expect($dto->calendarSubscriptionTeamMemberIds)->toBe(['a', 'b'])
        ->and($dto->calendarSubscriptionHolidayCalendarIds)->toBe(['cal-1', 'cal-2']);
});

it('sets calendar subscription ids to null when keys are present but null', function () {
    $data = [
        'id' => 'user-4',
        'first_name' => 'Bob',
        'last_name' => 'Jones',
        'email' => 'bob@example.com',
        'roles' => '["ROLE_USER"]',
        'working_days' => '[1,2,3,4,5]',
        'annual_leave_allowance' => 24,
        'current_leave_balance' => 20,
        'is_active' => 1,
        'created_at' => '2025-01-01 00:00:00',
        'profile_image_url' => null,
        'birth_date' => null,
        'absence_balance_reset_day' => '2025-01-01',
        'calendar_subscription_team_member_ids' => null,
        'calendar_subscription_holiday_calendar_ids' => null,
    ];

    $dto = UserDTO::fromArray($data);

    expect($dto->calendarSubscriptionTeamMemberIds)->toBeNull()
        ->and($dto->calendarSubscriptionHolidayCalendarIds)->toBeNull();
});

it('defaults calendar subscription ids to null when keys are absent', function () {
    $data = [
        'id' => 'user-5',
        'first_name' => 'Carol',
        'last_name' => 'White',
        'email' => 'carol@example.com',
        'roles' => '["ROLE_USER"]',
        'working_days' => '[1,2,3,4,5]',
        'annual_leave_allowance' => 24,
        'current_leave_balance' => 20,
        'is_active' => 1,
        'created_at' => '2025-01-01 00:00:00',
        'profile_image_url' => null,
        'birth_date' => null,
        'absence_balance_reset_day' => '2025-01-01',
    ];

    $dto = UserDTO::fromArray($data);

    expect($dto->calendarSubscriptionTeamMemberIds)->toBeNull()
        ->and($dto->calendarSubscriptionHolidayCalendarIds)->toBeNull();
});

it('reads calendar_country_code from array when present', function () {
    $data = [
        'id' => 'user-6',
        'first_name' => 'Erik',
        'last_name' => 'Svensson',
        'email' => 'erik@example.com',
        'roles' => '["ROLE_USER"]',
        'working_days' => '[1,2,3,4,5]',
        'annual_leave_allowance' => 24,
        'current_leave_balance' => 20,
        'is_active' => 1,
        'created_at' => '2025-01-01 00:00:00',
        'profile_image_url' => null,
        'birth_date' => null,
        'absence_balance_reset_day' => '2025-01-01',
        'calendar_country_code' => 'SE',
    ];

    $dto = UserDTO::fromArray($data);

    expect($dto->calendarCountryCode)->toBe('SE');
});

it('defaults calendarCountryCode to null when calendar_country_code is absent from array', function () {
    $data = [
        'id' => 'user-7',
        'first_name' => 'Marie',
        'last_name' => 'Dupont',
        'email' => 'marie@example.com',
        'roles' => '["ROLE_USER"]',
        'working_days' => '[1,2,3,4,5]',
        'annual_leave_allowance' => 24,
        'current_leave_balance' => 20,
        'is_active' => 1,
        'created_at' => '2025-01-01 00:00:00',
        'profile_image_url' => null,
        'birth_date' => null,
        'absence_balance_reset_day' => '2025-01-01',
    ];

    $dto = UserDTO::fromArray($data);

    expect($dto->calendarCountryCode)->toBeNull();
});

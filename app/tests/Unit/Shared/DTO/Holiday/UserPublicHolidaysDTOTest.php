<?php

declare(strict_types=1);

use App\Shared\DTO\Holiday\UserPublicHolidaysDTO;

it('creates from grouped array with single holiday', function () {
    $data = [
        [
            'id' => 'holiday-1',
            'description' => 'New Year',
            'country_code' => 'US',
            'date' => '2025-01-01',
            'user_id' => 'user-123',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'roles' => json_encode(['ROLE_USER']),
            'working_days' => json_encode([1, 2, 3, 4, 5]),
            'annual_leave_allowance' => 24,
            'current_leave_balance' => 20,
            'is_active' => true,
            'created_at' => '2024-01-01 00:00:00',
            'profile_image_url' => null,
            'birth_date' => '1990-01-15',
            'slack_member_id' => null,
            'calendar_country_code' => 'US',
            'absence_balance_reset_day' => '2027-01-01'
        ],
    ];

    $result = UserPublicHolidaysDTO::createFromGroupedArray($data);

    expect($result->user->id)
        ->toBe('user-123')
        ->and($result->user->firstName)->toBe('John')
        ->and($result->user->lastName)->toBe('Doe')
        ->and($result->user->email)->toBe('john@example.com')
        ->and($result->holidays)->toHaveCount(1)
        ->and($result->holidays[0]->id)->toBe('holiday-1')
        ->and($result->holidays[0]->description)->toBe('New Year')
        ->and($result->holidays[0]->countryCode)->toBe('US')
        ->and($result->holidays[0]->date->format('Y-m-d'))->toBe('2025-01-01');
});

it('creates from grouped array with multiple holidays', function () {
    $data = [
        [
            'id' => 'holiday-1',
            'description' => 'Christmas Day',
            'country_code' => 'GB',
            'date' => '2025-12-25',
            'user_id' => 'user-456',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'roles' => json_encode(['ROLE_USER', 'ROLE_MANAGER']),
            'working_days' => json_encode([1, 2, 3, 4, 5]),
            'annual_leave_allowance' => 30,
            'current_leave_balance' => 25,
            'is_active' => true,
            'created_at' => '2023-05-10 10:30:00',
            'profile_image_url' => 'https://example.com/photo.jpg',
            'birth_date' => '1985-06-20',
            'slack_member_id' => 'U12345',
            'calendar_country_code' => 'GB',
            'absence_balance_reset_day' => '2027-01-01'
        ],
        [
            'id' => 'holiday-2',
            'description' => 'Boxing Day',
            'country_code' => 'GB',
            'date' => '2025-12-26',
            'user_id' => 'user-456',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'roles' => json_encode(['ROLE_USER', 'ROLE_MANAGER']),
            'working_days' => json_encode([1, 2, 3, 4, 5]),
            'annual_leave_allowance' => 30,
            'current_leave_balance' => 25,
            'is_active' => true,
            'created_at' => '2023-05-10 10:30:00',
            'profile_image_url' => 'https://example.com/photo.jpg',
            'birth_date' => '1985-06-20',
            'slack_member_id' => 'U12345',
            'calendar_country_code' => 'GB',
            'absence_balance_reset_day' => '2027-01-01'
        ],
    ];

    $result = UserPublicHolidaysDTO::createFromGroupedArray($data);

    expect($result->user->id)
        ->toBe('user-456')
        ->and($result->user->firstName)->toBe('Jane')
        ->and($result->user->lastName)->toBe('Smith')
        ->and($result->holidays)->toHaveCount(2)
        ->and($result->holidays[0]->description)->toBe('Christmas Day')
        ->and($result->holidays[0]->date->format('Y-m-d'))->toBe('2025-12-25')
        ->and($result->holidays[1]->description)->toBe('Boxing Day')
        ->and($result->holidays[1]->date->format('Y-m-d'))->toBe('2025-12-26');
});

it('extracts user id from user_id field in grouped array', function () {
    $data = [
        [
            'id' => 'holiday-1',
            'description' => 'Independence Day',
            'country_code' => 'US',
            'date' => '2025-07-04',
            'user_id' => 'extracted-user-id',
            'first_name' => 'Bob',
            'last_name' => 'Johnson',
            'email' => 'bob@example.com',
            'roles' => json_encode(['ROLE_USER']),
            'working_days' => json_encode([1, 2, 3, 4]),
            'annual_leave_allowance' => 20,
            'current_leave_balance' => 15,
            'is_active' => true,
            'created_at' => '2024-03-15 14:20:00',
            'profile_image_url' => null,
            'birth_date' => '1995-09-10',
            'slack_member_id' => null,
            'calendar_country_code' => 'US',
            'absence_balance_reset_day' => '2027-01-01'
        ],
    ];

    $result = UserPublicHolidaysDTO::createFromGroupedArray($data);

    expect($result->user->id)
        ->toBe('extracted-user-id');
});

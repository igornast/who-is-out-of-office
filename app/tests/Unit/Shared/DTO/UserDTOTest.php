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

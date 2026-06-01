<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Query\GetCalendarSubscriptionConfigQueryHandler;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\Facade\HolidayFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
    $this->holidayFacade = Mockery::mock(HolidayFacadeInterface::class);
    $this->handler = new GetCalendarSubscriptionConfigQueryHandler($this->userRepository, $this->holidayFacade);
});

it('builds candidates from teammates and own + teammate country codes', function (): void {
    $userId = Uuid::uuid4()->toString();
    $user = UserDTOFixture::create([
        'id' => $userId,
        'calendarCountryCode' => 'DE',
        'calendarSubscriptionTeamMemberIds' => null,
        'calendarSubscriptionHolidayCalendarIds' => null,
    ]);
    $teammate1 = UserDTOFixture::create(['calendarCountryCode' => 'SE']);
    $teammate2 = UserDTOFixture::create(['calendarCountryCode' => 'DE']);

    $this->userRepository->shouldReceive('findOneById')->with($userId)->andReturn($user);
    $this->userRepository->shouldReceive('findTeammatesOf')->with($userId)->andReturn([$teammate1, $teammate2]);
    $this->userRepository->shouldReceive('findManagementDescendants')->with($userId)->andReturn([]);
    $this->holidayFacade
        ->shouldReceive('getActiveCalendarsForCountryCodes')
        ->withArgs(function (array $codes): bool {
            sort($codes);

            return $codes === ['DE', 'SE'];
        })
        ->andReturn([
            new PublicHolidayCalendarDTO(id: Uuid::uuid4(), countryCode: 'DE', countryName: 'Germany', holidays: []),
            new PublicHolidayCalendarDTO(id: Uuid::uuid4(), countryCode: 'SE', countryName: 'Sweden', holidays: []),
        ]);

    $config = $this->handler->handle($userId);

    expect($config->candidateTeamMembers)->toHaveCount(2)
        ->and($config->candidateTeamMembers[0])->toBeInstanceOf(App\Shared\DTO\CalendarSubscription\CalendarSubscriptionCandidateDTO::class)
        ->and($config->topLevelTeamMemberIds)->toEqualCanonicalizing([$teammate1->id, $teammate2->id])
        ->and($config->candidateHolidayCalendars)->toHaveCount(2)
        ->and($config->selectedTeamMemberIds)->toBeNull()
        ->and($config->selectedHolidayCalendarIds)->toBeNull();
});

it('returns stored selections as-is when non-null', function (): void {
    $userId = Uuid::uuid4()->toString();
    $user = UserDTOFixture::create([
        'id' => $userId,
        'calendarCountryCode' => 'DE',
        'calendarSubscriptionTeamMemberIds' => ['abc', 'def'],
        'calendarSubscriptionHolidayCalendarIds' => [],
    ]);

    $this->userRepository->shouldReceive('findOneById')->with($userId)->andReturn($user);
    $this->userRepository->shouldReceive('findTeammatesOf')->with($userId)->andReturn([]);
    $this->userRepository->shouldReceive('findManagementDescendants')->with($userId)->andReturn([]);
    $this->holidayFacade->shouldReceive('getActiveCalendarsForCountryCodes')->andReturn([]);

    $config = $this->handler->handle($userId);

    expect($config->selectedTeamMemberIds)->toEqual(['abc', 'def'])
        ->and($config->selectedHolidayCalendarIds)->toEqual([])
        ->and($config->topLevelTeamMemberIds)->toBe([]);
});

it('filters null country codes before asking holiday facade', function (): void {
    $userId = Uuid::uuid4()->toString();
    $user = UserDTOFixture::create(['id' => $userId, 'calendarCountryCode' => null]);

    $this->userRepository->shouldReceive('findOneById')->with($userId)->andReturn($user);
    $this->userRepository->shouldReceive('findTeammatesOf')->with($userId)->andReturn([]);
    $this->userRepository->shouldReceive('findManagementDescendants')->with($userId)->andReturn([]);
    $this->holidayFacade
        ->shouldReceive('getActiveCalendarsForCountryCodes')
        ->with([])
        ->andReturn([]);

    $config = $this->handler->handle($userId);

    expect($config->candidateHolidayCalendars)->toBe([]);
});

it('throws when user is not found', function (): void {
    $userId = Uuid::uuid4()->toString();

    $this->userRepository->shouldReceive('findOneById')->with($userId)->andReturn(null);

    expect(fn () => $this->handler->handle($userId))
        ->toThrow(RuntimeException::class, sprintf('User %s not found', $userId));
});

it('populates avatar fields (initials, colorIndex, raw profileImageUrl) on candidates', function (): void {
    $userId = Uuid::uuid4()->toString();
    $user = UserDTOFixture::create(['id' => $userId, 'calendarCountryCode' => 'DE']);

    // 'Ada' . 'Lovelace' = 11 chars → 11 % 6 = 5 ; initials 'AL'
    $withImage = UserDTOFixture::create([
        'firstName' => 'Ada',
        'lastName' => 'Lovelace',
        'profileImageUrl' => 'https://cdn.example/ada.png',
    ]);
    // 'Bo' . 'Li' = 4 chars → 4 % 6 = 4 ; initials 'BL' ; no image
    $noImage = UserDTOFixture::create([
        'firstName' => 'Bo',
        'lastName' => 'Li',
        'profileImageUrl' => null,
    ]);

    $this->userRepository->shouldReceive('findOneById')->with($userId)->andReturn($user);
    $this->userRepository->shouldReceive('findTeammatesOf')->with($userId)->andReturn([$withImage, $noImage]);
    $this->userRepository->shouldReceive('findManagementDescendants')->with($userId)->andReturn([]);
    $this->holidayFacade->shouldReceive('getActiveCalendarsForCountryCodes')->andReturn([]);

    $config = $this->handler->handle($userId);

    $byId = [];
    foreach ($config->candidateTeamMembers as $c) {
        $byId[$c->id] = $c;
    }

    expect($byId[$withImage->id]->initials)->toBe('AL')
        ->and($byId[$withImage->id]->colorIndex)->toBe(5)
        ->and($byId[$withImage->id]->profileImageUrl)->toBe('https://cdn.example/ada.png')
        ->and($byId[$noImage->id]->initials)->toBe('BL')
        ->and($byId[$noImage->id]->colorIndex)->toBe(4)
        ->and($byId[$noImage->id]->profileImageUrl)->toBeNull();
});

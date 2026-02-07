<?php

declare(strict_types=1);

use App\Module\User\DTO\PasswordHashUserDTO;
use App\Module\User\DTO\UserInvitationRequestDTO;
use App\Module\User\Repository\InvitationRepositoryInterface;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\AcceptUserInvitationCommandHandler;
use App\Shared\DTO\InvitationDTO;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

beforeEach(function (): void {
    $this->invitationRepository = mock(InvitationRepositoryInterface::class);
    $this->userRepository = mock(UserRepositoryInterface::class);
    $this->passwordHasher = mock(UserPasswordHasherInterface::class);

    $this->handler = new AcceptUserInvitationCommandHandler(
        invitationRepository: $this->invitationRepository,
        userRepository: $this->userRepository,
        passwordHasher: $this->passwordHasher,
    );
});

it('accepts invitation and updates user', function () {
    $userDTO = UserDTOFixture::create([
        'email' => 'john@ooo.com',
        'roles' => ['ROLE_USER'],
        'isActive' => false,
    ]);

    $invitationDTO = new InvitationDTO(
        id: 'inv-1',
        token: 'token-123',
        user: $userDTO,
        createdAt: new DateTimeImmutable(),
    );

    $birthdate = new DateTimeImmutable('1990-05-15');
    $invitationRequestDTO = new UserInvitationRequestDTO(
        firstName: 'John',
        lastName: 'Doe',
        password: 'securePassword123',
        birthdate: $birthdate,
    );

    $this->passwordHasher
        ->expects('hashPassword')
        ->once()
        ->withArgs(fn (PasswordHashUserDTO $dto, string $password) => 'john@ooo.com' === $dto->getUserIdentifier()
            && 'securePassword123' === $password)
        ->andReturn('hashed-password');

    $this->userRepository
        ->expects('update')
        ->once()
        ->with($userDTO);

    $this->invitationRepository
        ->expects('remove')
        ->once()
        ->with($invitationDTO);

    $this->handler->handle($invitationRequestDTO, $invitationDTO);

    expect($userDTO->firstName)->toBe('John')
        ->and($userDTO->lastName)->toBe('Doe')
        ->and($userDTO->birthDate)->toBe($birthdate)
        ->and($userDTO->password)->toBe('hashed-password')
        ->and($userDTO->isActive)->toBeTrue();
});

it('accepts invitation without birthdate', function () {
    $userDTO = UserDTOFixture::create([
        'email' => 'jane@ooo.com',
        'roles' => ['ROLE_USER'],
        'isActive' => false,
    ]);

    $invitationDTO = new InvitationDTO(
        id: 'inv-2',
        token: 'token-456',
        user: $userDTO,
        createdAt: new DateTimeImmutable(),
    );

    $invitationRequestDTO = new UserInvitationRequestDTO(
        firstName: 'Jane',
        lastName: 'Smith',
        password: 'password123',
    );

    $this->passwordHasher
        ->expects('hashPassword')
        ->once()
        ->andReturn('hashed-password');

    $this->userRepository
        ->expects('update')
        ->once();

    $this->invitationRepository
        ->expects('remove')
        ->once();

    $this->handler->handle($invitationRequestDTO, $invitationDTO);

    expect($userDTO->firstName)->toBe('Jane')
        ->and($userDTO->lastName)->toBe('Smith')
        ->and($userDTO->birthDate)->toBeNull()
        ->and($userDTO->isActive)->toBeTrue();
});

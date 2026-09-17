<?php

declare(strict_types=1);

use App\Module\User\Repository\InvitationRepositoryInterface;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\IssueUserInvitationCommandHandler;
use App\Tests\_fixtures\Shared\DTO\InvitationDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);
    $this->invitationRepository = mock(InvitationRepositoryInterface::class);

    $this->handler = new IssueUserInvitationCommandHandler(
        $this->userRepository,
        $this->invitationRepository,
    );
});

it('issues a fresh 64-character token for a user with a pending invitation', function (): void {
    $userId = Uuid::uuid4()->toString();
    $userDTO = UserDTOFixture::create(['id' => $userId, 'isActive' => false]);
    $invitationDTO = InvitationDTOFixture::create();

    $this->userRepository->expects('findOneById')->with($userId)->andReturn($userDTO);
    $this->invitationRepository->expects('findOneByUserId')->with($userId)->andReturn(InvitationDTOFixture::create());
    $this->invitationRepository->expects('replaceForUser')
        ->withArgs(fn (string $id, string $token): bool => $id === $userId && 64 === strlen($token))
        ->andReturn($invitationDTO);

    expect($this->handler->handle($userId))->toBe($invitationDTO);
});

it('returns null for an inactive user without a pending invitation', function (): void {
    $userId = Uuid::uuid4()->toString();
    $userDTO = UserDTOFixture::create(['id' => $userId, 'isActive' => false]);

    $this->userRepository->expects('findOneById')->with($userId)->andReturn($userDTO);
    $this->invitationRepository->expects('findOneByUserId')->with($userId)->andReturn(null);
    $this->invitationRepository->expects('replaceForUser')->never();

    expect($this->handler->handle($userId))->toBeNull();
});

it('returns null for an active user without touching the invitation repository', function (): void {
    $userId = Uuid::uuid4()->toString();
    $userDTO = UserDTOFixture::create(['id' => $userId, 'isActive' => true]);

    $this->userRepository->expects('findOneById')->with($userId)->andReturn($userDTO);
    $this->invitationRepository->expects('findOneByUserId')->never();
    $this->invitationRepository->expects('replaceForUser')->never();

    expect($this->handler->handle($userId))->toBeNull();
});

it('returns null for an unknown user', function (): void {
    $userId = Uuid::uuid4()->toString();

    $this->userRepository->expects('findOneById')->with($userId)->andReturn(null);
    $this->invitationRepository->expects('replaceForUser')->never();

    expect($this->handler->handle($userId))->toBeNull();
});

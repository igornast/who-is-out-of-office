<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\LeaveRequest\Security\LeaveRequestVoter;
use App\Shared\Enum\LeaveRequestPermission;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Enum\RoleEnum;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

function createToken(User $user): TokenInterface
{
    $token = mock(TokenInterface::class);
    $token->allows('getUser')->andReturn($user);

    return $token;
}

function createUser(string $id, array $roles = [RoleEnum::User->value]): User
{
    return new User(
        id: Uuid::fromString($id),
        firstName: 'Test',
        lastName: 'User',
        email: 'test@ooo.com',
        password: 'password',
        roles: $roles,
        workingDays: [1, 2, 3, 4, 5],
    );
}

beforeEach(function (): void {
    $this->voter = new LeaveRequestVoter();
});

describe('supports', function (): void {
    it('abstains on unsupported attributes', function (): void {
        $userId = Uuid::uuid4()->toString();
        $user = createUser($userId);
        $leaveRequest = LeaveRequestDTOFixture::create([
            'user' => UserDTOFixture::create(['id' => $userId]),
        ]);

        $result = $this->voter->vote(createToken($user), $leaveRequest, ['UNSUPPORTED']);

        expect($result)->toBe(VoterInterface::ACCESS_ABSTAIN);
    });

    it('abstains on non-LeaveRequestDTO subjects', function (): void {
        $user = createUser(Uuid::uuid4()->toString());

        $result = $this->voter->vote(createToken($user), new stdClass(), [LeaveRequestPermission::Withdraw->value]);

        expect($result)->toBe(VoterInterface::ACCESS_ABSTAIN);
    });

    it('abstains when subject is null', function (): void {
        $user = createUser(Uuid::uuid4()->toString());

        $result = $this->voter->vote(createToken($user), null, [LeaveRequestPermission::Withdraw->value]);

        expect($result)->toBe(VoterInterface::ACCESS_ABSTAIN);
    });
});

describe('WITHDRAW', function (): void {
    it('grants access when current user is the owner', function (): void {
        $userId = Uuid::uuid4()->toString();
        $user = createUser($userId);
        $leaveRequest = LeaveRequestDTOFixture::create([
            'user' => UserDTOFixture::create(['id' => $userId]),
        ]);

        $result = $this->voter->vote(createToken($user), $leaveRequest, [LeaveRequestPermission::Withdraw->value]);

        expect($result)->toBe(VoterInterface::ACCESS_GRANTED);
    });

    it('denies access when current user is not the owner', function (): void {
        $user = createUser(Uuid::uuid4()->toString());
        $leaveRequest = LeaveRequestDTOFixture::create([
            'user' => UserDTOFixture::create(['id' => Uuid::uuid4()->toString()]),
        ]);

        $result = $this->voter->vote(createToken($user), $leaveRequest, [LeaveRequestPermission::Withdraw->value]);

        expect($result)->toBe(VoterInterface::ACCESS_DENIED);
    });

    it('denies access when token user is not a User entity', function (): void {
        $token = mock(TokenInterface::class);
        $token->allows('getUser')->andReturn(null);

        $leaveRequest = LeaveRequestDTOFixture::create();

        $result = $this->voter->vote($token, $leaveRequest, [LeaveRequestPermission::Withdraw->value]);

        expect($result)->toBe(VoterInterface::ACCESS_DENIED);
    });
});

describe('MANAGE', function (): void {
    it('grants access for admin managing another users request', function (): void {
        $adminId = Uuid::uuid4()->toString();
        $admin = createUser($adminId, [RoleEnum::Admin->value, RoleEnum::User->value]);
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create(['id' => Uuid::uuid4()->toString()]),
        ]);

        $result = $this->voter->vote(createToken($admin), $leaveRequest, [LeaveRequestPermission::Manage->value]);

        expect($result)->toBe(VoterInterface::ACCESS_GRANTED);
    });

    it('grants access for manager managing direct report request', function (): void {
        $managerId = Uuid::uuid4()->toString();
        $manager = createUser($managerId, [RoleEnum::Manager->value, RoleEnum::User->value]);
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create([
                'id' => Uuid::uuid4()->toString(),
                'managerId' => $managerId,
            ]),
        ]);

        $result = $this->voter->vote(createToken($manager), $leaveRequest, [LeaveRequestPermission::Manage->value]);

        expect($result)->toBe(VoterInterface::ACCESS_GRANTED);
    });

    it('denies access for manager managing non-direct-report request', function (): void {
        $managerId = Uuid::uuid4()->toString();
        $manager = createUser($managerId, [RoleEnum::Manager->value, RoleEnum::User->value]);
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create([
                'id' => Uuid::uuid4()->toString(),
                'managerId' => Uuid::uuid4()->toString(),
            ]),
        ]);

        $result = $this->voter->vote(createToken($manager), $leaveRequest, [LeaveRequestPermission::Manage->value]);

        expect($result)->toBe(VoterInterface::ACCESS_DENIED);
    });

    it('denies access when trying to manage own request as admin', function (): void {
        $userId = Uuid::uuid4()->toString();
        $admin = createUser($userId, [RoleEnum::Admin->value, RoleEnum::User->value]);
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create(['id' => $userId]),
        ]);

        $result = $this->voter->vote(createToken($admin), $leaveRequest, [LeaveRequestPermission::Manage->value]);

        expect($result)->toBe(VoterInterface::ACCESS_DENIED);
    });

    it('denies access when trying to manage own request as manager', function (): void {
        $userId = Uuid::uuid4()->toString();
        $manager = createUser($userId, [RoleEnum::Manager->value, RoleEnum::User->value]);
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create(['id' => $userId, 'managerId' => $userId]),
        ]);

        $result = $this->voter->vote(createToken($manager), $leaveRequest, [LeaveRequestPermission::Manage->value]);

        expect($result)->toBe(VoterInterface::ACCESS_DENIED);
    });

    it('denies access for regular user', function (): void {
        $user = createUser(Uuid::uuid4()->toString());
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create(['id' => Uuid::uuid4()->toString()]),
        ]);

        $result = $this->voter->vote(createToken($user), $leaveRequest, [LeaveRequestPermission::Manage->value]);

        expect($result)->toBe(VoterInterface::ACCESS_DENIED);
    });

    it('denies access when token user is not a User entity', function (): void {
        $token = mock(TokenInterface::class);
        $token->allows('getUser')->andReturn(null);

        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
        ]);

        $result = $this->voter->vote($token, $leaveRequest, [LeaveRequestPermission::Manage->value]);

        expect($result)->toBe(VoterInterface::ACCESS_DENIED);
    });

    it('grants access for user with both admin and manager roles', function (): void {
        $userId = Uuid::uuid4()->toString();
        $user = createUser($userId, [RoleEnum::Admin->value, RoleEnum::Manager->value, RoleEnum::User->value]);
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create([
                'id' => Uuid::uuid4()->toString(),
                'managerId' => Uuid::uuid4()->toString(),
            ]),
        ]);

        $result = $this->voter->vote(createToken($user), $leaveRequest, [LeaveRequestPermission::Manage->value]);

        expect($result)->toBe(VoterInterface::ACCESS_GRANTED);
    });
});

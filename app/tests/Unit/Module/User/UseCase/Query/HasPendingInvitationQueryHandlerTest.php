<?php

declare(strict_types=1);

use App\Module\User\Repository\InvitationRepositoryInterface;
use App\Module\User\UseCase\Query\HasPendingInvitationQueryHandler;
use App\Tests\_fixtures\Shared\DTO\InvitationDTOFixture;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->invitationRepository = mock(InvitationRepositoryInterface::class);
    $this->handler = new HasPendingInvitationQueryHandler($this->invitationRepository);
});

it('returns true when the user has an invitation', function (): void {
    $userId = Uuid::uuid4()->toString();
    $this->invitationRepository->expects('findOneByUserId')->with($userId)->andReturn(InvitationDTOFixture::create());

    expect($this->handler->handle($userId))->toBeTrue();
});

it('returns false when the user has no invitation', function (): void {
    $userId = Uuid::uuid4()->toString();
    $this->invitationRepository->expects('findOneByUserId')->with($userId)->andReturn(null);

    expect($this->handler->handle($userId))->toBeFalse();
});

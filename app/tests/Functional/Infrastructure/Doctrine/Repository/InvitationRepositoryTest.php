<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\Invitation;
use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Doctrine\Repository\InvitationRepository;
use App\Shared\DTO\InvitationDTO;

beforeEach(function (): void {
    static::bootKernel();
    $this->repository = static::getContainer()->get(InvitationRepository::class);
    $this->em = static::getContainer()->get('doctrine')->getManager();

    $this->user = $this->em->getRepository(User::class)->findOneBy(['email' => 'invited3@whoisooo.app']);
    $this->userId = $this->user->id->toString();

    foreach ($this->em->getRepository(Invitation::class)->findBy(['user' => $this->userId]) as $invitation) {
        $this->em->remove($invitation);
    }
    $this->em->flush();
    $this->em->clear();
});

it('creates an invitation for a user that has none', function (): void {
    $invitationDTO = $this->repository->replaceForUser($this->userId, 'token-one');

    expect($invitationDTO)->toBeInstanceOf(InvitationDTO::class)
        ->and($invitationDTO->token)->toBe('token-one')
        ->and($invitationDTO->user->email)->toBe('invited3@whoisooo.app');
});

it('replaces an existing invitation with the new token', function (): void {
    $this->repository->replaceForUser($this->userId, 'token-one');
    $this->em->clear();

    $second = $this->repository->replaceForUser($this->userId, 'token-two');

    expect($second)->toBeInstanceOf(InvitationDTO::class)
        ->and($second->token)->toBe('token-two')
        ->and($this->em->getRepository(Invitation::class)->count(['user' => $this->userId]))->toBe(1);
});

it('returns null for an unknown user id', function (): void {
    expect($this->repository->replaceForUser('00000000-0000-0000-0000-000000000000', 'token-three'))->toBeNull();
});

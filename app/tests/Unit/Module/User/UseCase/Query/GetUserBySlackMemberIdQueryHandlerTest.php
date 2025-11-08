<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\Module\User\UseCase\Query\GetUserBySlackMemberIdQueryHandler;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepository::class);

    $this->handler = new GetUserBySlackMemberIdQueryHandler(userRepository: $this->userRepository);
});

it('finds user by slack member id', function () {
    $slackMemberId = 'U12345678';
    $expectedUser = UserDTOFixture::create([
        'firstName' => 'John',
        'lastName' => 'Doe',
        'slackMemberId' => $slackMemberId,
    ]);

    $this->userRepository
        ->expects('findUserBySlackMemberId')
        ->once()
        ->with($slackMemberId)
        ->andReturn($expectedUser);

    $result = $this->handler->handle($slackMemberId);

    expect($result)->toBe($expectedUser)
        ->and($result->slackMemberId)->toBe($slackMemberId);
});

it('returns null when user not found', function () {
    $slackMemberId = 'U99999999';

    $this->userRepository
        ->expects('findUserBySlackMemberId')
        ->once()
        ->with($slackMemberId)
        ->andReturn(null);

    $result = $this->handler->handle($slackMemberId);

    expect($result)->toBeNull();
});

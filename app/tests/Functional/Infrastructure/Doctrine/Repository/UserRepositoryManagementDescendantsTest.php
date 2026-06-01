<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Repository\UserRepository;

beforeEach(function (): void {
    static::bootKernel();
    $this->repository = static::getContainer()->get(UserRepository::class);
});

it('returns transitive reports across multiple levels', function (): void {
    $admin = $this->repository->findOneByEmail('admin@whoisooo.app');
    $manager = $this->repository->findOneByEmail('manager@whoisooo.app');
    $user = $this->repository->findOneByEmail('user@whoisooo.app');

    expect($admin)->not->toBeNull()
        ->and($manager)->not->toBeNull()
        ->and($user)->not->toBeNull();

    $descendants = $this->repository->findManagementDescendants($admin->id);
    $ids = array_map(fn ($dto) => $dto->id, $descendants);

    expect($ids)->toContain($manager->id)
        ->and($ids)->toContain($user->id)
        ->and($ids)->not->toContain($admin->id);

    $invited = $this->repository->findOneByEmail('invited@whoisooo.app');
    $invited2 = $this->repository->findOneByEmail('invited2@whoisooo.app');

    if (null !== $invited) {
        expect($ids)->not->toContain($invited->id);
    }
    if (null !== $invited2) {
        expect($ids)->not->toContain($invited2->id);
    }
});

it('returns empty array for a user with no reports', function (): void {
    $user = $this->repository->findOneByEmail('user@whoisooo.app');

    expect($user)->not->toBeNull();

    $descendants = $this->repository->findManagementDescendants($user->id);

    expect($descendants)->toBe([]);
});

it('orders results by first_name then last_name', function (): void {
    $admin = $this->repository->findOneByEmail('admin@whoisooo.app');

    expect($admin)->not->toBeNull();

    $descendants = $this->repository->findManagementDescendants($admin->id);

    $firstNames = array_map(fn ($dto) => $dto->firstName, $descendants);
    $sorted = $firstNames;
    sort($sorted, SORT_STRING);

    expect($firstNames)->toBe($sorted);
});

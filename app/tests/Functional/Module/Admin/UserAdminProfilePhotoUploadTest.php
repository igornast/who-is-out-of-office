<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Tests\_fixtures\ProfilePhotoFixture;
use Doctrine\ORM\EntityManagerInterface;

beforeEach(function (): void {
    $this->client = static::createClient();
    $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    $this->admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
    $this->target = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($this->admin)->toBeInstanceOf(User::class)
        ->and($this->target)->toBeInstanceOf(User::class);

    $this->client->loginUser($this->admin);
});

afterEach(function (): void {
    ProfilePhotoFixture::cleanup();
});

it('rejects an oversized photo on the admin user edit form with the limit message', function (): void {
    $profileImageUrlBefore = $this->target->profileImageUrl;
    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $this->target->id->toString()));
    $form = $crawler->selectButton('saveAndReturn')->form();
    $oversizedProfilePhoto = ProfilePhotoFixture::jpegOfSize(16_000_000);
    $form['User[profileImageUrl][file]']->upload($oversizedProfilePhoto);
    $this->client->submit($form);

    $this->entityManager->clear();
    $reloadedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);
    $content = $this->client->getResponse()->getContent();

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and(substr_count($content, 'This photo is too large. The maximum size is 15 MB.'))->toBe(1)
        ->and($content)->not->toContain('Allowed maximum size')
        ->and($reloadedUser)->toBeInstanceOf(User::class)
        ->and($reloadedUser->profileImageUrl)->toBe($profileImageUrlBefore);
});

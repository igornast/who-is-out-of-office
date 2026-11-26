<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\DTO\UserProfileDTO;
use App\Tests\_fixtures\ProfilePhotoFixture;
use Doctrine\ORM\EntityManagerInterface;

beforeEach(function (): void {
    $this->client = static::createClient();
    $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    $this->user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($this->user)->toBeInstanceOf(User::class);

    $this->client->loginUser($this->user);
    $this->crawler = $this->client->request('GET', '/app/user/profile');
});

afterEach(function (): void {
    ProfilePhotoFixture::cleanup();
});

it('shows the real limit in the photo hint', function (): void {
    expect($this->crawler->filter('.profile-avatar-hint')->text())->toContain('Max 15 MB')
        ->and($this->crawler->filter('.profile-avatar-file-input')->attr('data-too-large-message'))
        ->toBe('This photo is too large. The maximum size is 15 MB.')
        ->and($this->crawler->filter('.profile-avatar-file-input')->attr('data-max-bytes'))
        ->toBe((string) UserProfileDTO::PROFILE_IMAGE_MAX_BYTES);
});

it('shows why an oversized photo was rejected next to the photo', function (): void {
    $profileImageUrlBefore = $this->user->profileImageUrl;
    $form = $this->crawler->selectButton('Save Changes')->form();
    $oversizedProfilePhoto = ProfilePhotoFixture::jpegOfSize(16_000_000);
    $form['user_profile[profileImageFile]']->upload($oversizedProfilePhoto);
    $this->client->submit($form);

    $this->entityManager->clear();
    $reloadedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($this->client->getCrawler()->filter('#profile-image-errors')->text())
        ->toBe('This photo is too large. The maximum size is 15 MB.')
        ->and($this->client->getCrawler()->filter('#profile-image-errors .invalid-feedback')->count())
        ->toBe(1)
        ->and(substr_count($this->client->getCrawler()->filter('body')->text(), 'This photo is too large. The maximum size is 15 MB.'))
        ->toBe(1)
        ->and($this->client->getCrawler()->filter('.settings-card-footer .invalid-feedback')->count())
        ->toBe(0)
        ->and($this->client->getResponse()->getContent())->not->toContain('Allowed maximum size')
        ->and($this->client->getResponse()->getContent())->not->toContain('Nothing was saved')
        ->and($reloadedUser)->toBeInstanceOf(User::class)
        ->and($reloadedUser->profileImageUrl)->toBe($profileImageUrlBefore);
});

it('saves the profile without a new photo and keeps the current one', function (): void {
    $profileImageUrlBefore = $this->user->profileImageUrl;
    $form = $this->crawler->selectButton('Save Changes')->form();
    $form['user_profile[firstName]'] = 'Johnny';
    $this->client->submit($form);

    $this->entityManager->clear();
    $reloadedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($this->client->getResponse()->getStatusCode())->toBe(302)
        ->and($reloadedUser)->toBeInstanceOf(User::class)
        ->and($reloadedUser->firstName)->toBe('Johnny')
        ->and($reloadedUser->profileImageUrl)->toBe($profileImageUrlBefore);
});

it('answers 422 and keeps the typed values when a field is invalid', function (): void {
    $lastNameBefore = $this->user->lastName;
    $form = $this->crawler->selectButton('Save Changes')->form();
    $form['user_profile[firstName]'] = '';
    $form['user_profile[lastName]'] = 'Typed but not saved';
    $this->client->submit($form);

    $this->entityManager->clear();
    $reloadedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($this->client->getCrawler()->filter('input[name="user_profile[lastName]"]')->attr('value'))->toBe('Typed but not saved')
        ->and($this->client->getCrawler()->filterXPath('//div[contains(concat(" ", normalize-space(@class), " "), " field-text ")][.//input[@name="user_profile[firstName]"]]//div[contains(concat(" ", normalize-space(@class), " "), " invalid-feedback ")]')->text())->toBe('This value should not be blank.')
        ->and($this->client->getResponse()->getContent())->not->toContain('Nothing was saved')
        ->and($reloadedUser)->toBeInstanceOf(User::class)
        ->and($reloadedUser->lastName)->toBe($lastNameBefore);
});

it('answers 422 when the last name is only whitespace', function (): void {
    $lastNameBefore = $this->user->lastName;
    $form = $this->crawler->selectButton('Save Changes')->form();
    $form['user_profile[lastName]'] = '   ';
    $this->client->submit($form);

    $this->entityManager->clear();
    $reloadedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($reloadedUser)->toBeInstanceOf(User::class)
        ->and($reloadedUser->lastName)->toBe($lastNameBefore);
});

it('answers 200 when the profile page is loaded without a submit', function (): void {
    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($this->client->getCrawler()->filter('.invalid-feedback')->count())->toBe(0)
        ->and($this->client->getResponse()->getContent())->not->toContain('Nothing was saved');
});

it('names the limit when the whole request is too large', function (): void {
    $firstNameBefore = $this->user->firstName;
    $form = $this->crawler->selectButton('Save Changes')->form();
    $form['user_profile[firstName]'] = 'This name is not saved';
    $this->client->submit($form, [], ['CONTENT_LENGTH' => 21_000_000]);

    $this->entityManager->clear();
    $reloadedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);
    $content = $this->client->getResponse()->getContent();

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and(substr_count($this->client->getCrawler()->filter('body')->text(), 'Nothing was saved because the photo is larger than 15 MB. Choose a smaller photo and make your changes again.'))->toBe(1)
        ->and($this->client->getCrawler()->filter('#profile-image-errors .invalid-feedback')->count())->toBe(0)
        ->and($this->client->getCrawler()->filter('.settings-card-footer')->text())
        ->toContain('Nothing was saved because the photo is larger than 15 MB. Choose a smaller photo and make your changes again.')
        ->and($this->client->getCrawler()->filter('body')->text())->not->toContain('This photo is too large.')
        ->and($this->client->getCrawler()->filter('input[name="user_profile[firstName]"]')->attr('value'))->toBe($firstNameBefore)
        ->and($content)->not->toContain('The uploaded file was too large')
        ->and($reloadedUser)->toBeInstanceOf(User::class)
        ->and($reloadedUser->firstName)->toBe($firstNameBefore);
});

it('keeps the saved photo and the remove flag when the whole request is too large', function (): void {
    $profileImageUrlBefore = $this->user->profileImageUrl;
    $form = $this->crawler->selectButton('Save Changes')->form();
    $oversizedProfilePhoto = ProfilePhotoFixture::jpegOfSize(16_000_000);
    $form['user_profile[profileImageFile]']->upload($oversizedProfilePhoto);
    $form['user_profile[removeProfileImage]'] = '1';
    $this->client->submit($form, [], ['CONTENT_LENGTH' => 21_000_000]);

    $this->entityManager->clear();
    $reloadedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($reloadedUser)->toBeInstanceOf(User::class)
        ->and($profileImageUrlBefore)->not->toBeNull()
        ->and($reloadedUser->profileImageUrl)->toBe($profileImageUrlBefore);
});

<?php

declare(strict_types=1);

use App\Tests\_fixtures\ProfilePhotoFixture;
use Facebook\WebDriver\WebDriverBy;

beforeEach(function (): void {
    static::bootKernel();
});

afterEach(function (): void {
    ProfilePhotoFixture::cleanup();
});

it('refuses an oversized photo before submitting', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'user@whoisooo.app', '123');
    $client->request('GET', '/app/user/profile');
    $client->waitFor('#profile-image-preview');

    $previewSrcBefore = $client->executeScript("return document.getElementById('profile-image-preview').src;");
    $oversizedBrowserProfilePhoto = ProfilePhotoFixture::jpegOfSize(16_000_000);
    $client->getWebDriver()->findElement(WebDriverBy::cssSelector('.profile-avatar-file-input'))
        ->sendKeys($oversizedBrowserProfilePhoto);
    $client->waitForElementToContain('#profile-image-errors', 'This photo is too large. The maximum size is 15 MB.');

    expect($client->getWebDriver()->findElement(WebDriverBy::id('profile-image-errors'))->isDisplayed())->toBeTrue()
        ->and($client->getCrawler()->filter('#profile-image-errors')->text())->toBe('This photo is too large. The maximum size is 15 MB.')
        ->and($client->executeScript('return document.querySelector(".profile-avatar-file-input").value'))->toBe('')
        ->and($client->executeScript('return document.getElementById("profile-image-preview").src'))->toBe($previewSrcBefore);
});

it('restores the original preview after an oversized replacement', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'user@whoisooo.app', '123');
    $client->request('GET', '/app/user/profile');
    $client->waitFor('#profile-image-preview');

    $previewSrcBefore = $client->executeScript("return document.getElementById('profile-image-preview').src;");
    $fileInput = $client->getWebDriver()->findElement(WebDriverBy::cssSelector('.profile-avatar-file-input'));
    $fixture = dirname(__DIR__, 3).'/_fixtures/files/profile-photo.jpg';
    $oversizedBrowserProfilePhoto = ProfilePhotoFixture::jpegOfSize(16_000_000);

    $fileInput->sendKeys($fixture);
    $client->wait(5)->until(static fn () => str_starts_with((string) $client->executeScript("return document.getElementById('profile-image-preview').src;"), 'data:'));
    $fileInput->sendKeys($oversizedBrowserProfilePhoto);
    $client->waitForElementToContain('#profile-image-errors', 'This photo is too large. The maximum size is 15 MB.');

    expect($client->executeScript('return document.querySelector(".profile-avatar-file-input").value'))->toBe('')
        ->and($client->executeScript('return document.getElementById("profile-image-preview").src'))->toBe($previewSrcBefore)
        ->and($client->getWebDriver()->findElement(WebDriverBy::id('profile-image-errors'))->isDisplayed())->toBeTrue();

    $fileInput->sendKeys($fixture);
    $client->wait(5)->until(static fn () => '' === $client->executeScript("return document.getElementById('profile-image-errors').textContent;"));

    expect($client->getCrawler()->filter('#profile-image-errors')->text())->toBe('');
});

it('keeps the photo when an oversized pick follows remove', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'user@whoisooo.app', '123');
    $client->request('GET', '/app/user/profile');
    $client->waitFor('#profile-image-preview');

    $previewSrcBefore = $client->executeScript("return document.getElementById('profile-image-preview').src;");
    $oversizedBrowserProfilePhoto = ProfilePhotoFixture::jpegOfSize(16_000_000);
    $client->executeScript("document.getElementById('profile-image-remove-btn').click();");
    $client->getWebDriver()->findElement(WebDriverBy::cssSelector('.profile-avatar-file-input'))
        ->sendKeys($oversizedBrowserProfilePhoto);
    $client->waitForElementToContain('#profile-image-errors', 'This photo is too large. The maximum size is 15 MB.');

    expect($client->executeScript("return document.getElementById('user_profile_removeProfileImage').value;"))->toBe('')
        ->and($client->executeScript("return document.getElementById('profile-image-preview').src;"))->toBe($previewSrcBefore)
        ->and($client->getWebDriver()->findElement(WebDriverBy::id('profile-image-preview'))->isDisplayed())->toBeTrue();
});

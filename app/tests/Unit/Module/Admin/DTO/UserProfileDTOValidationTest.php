<?php

declare(strict_types=1);

use App\Module\Admin\DTO\UserProfileDTO;
use App\Tests\_fixtures\ProfilePhotoFixture;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Yaml\Yaml;

dataset('profilePhotoOtherTypes', [
    'PNG' => [base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='), 'image/png'],
    'WebP' => [base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAwA0JaQAA3AA/vuUAAA='), 'image/webp'],
]);

dataset('profilePhotoDisallowedTypes', [
    'GIF' => [base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')],
    'PDF renamed to .jpg' => ["%PDF-1.4\n%%EOF\n"],
]);

beforeEach(function (): void {
    $translator = new Translator('en');
    $translator->addLoader('yaml', new YamlFileLoader());
    $translator->addResource('yaml', dirname(__DIR__, 5).'/translations/validators.en.yaml', 'en', 'validators');

    $this->validator = Validation::createValidatorBuilder()
        ->enableAttributeMapping()
        ->setTranslator($translator)
        ->setTranslationDomain('validators')
        ->getValidator();
});

afterEach(function (): void {
    ProfilePhotoFixture::cleanup();
});

it('accepts a 3 MB phone photo', function (): void {
    $photo = new UploadedFile(ProfilePhotoFixture::jpegOfSize(3_000_000), 'photo.jpg', 'image/jpeg', null, true);

    expect($this->validator->validateProperty(new UserProfileDTO(profileImageFile: $photo), 'profileImageFile'))->toHaveCount(0);
});

it('accepts a photo of exactly the limit', function (): void {
    $photo = new UploadedFile(ProfilePhotoFixture::jpegOfSize(UserProfileDTO::PROFILE_IMAGE_MAX_BYTES), 'photo.jpg', 'image/jpeg', null, true);

    expect($this->validator->validateProperty(new UserProfileDTO(profileImageFile: $photo), 'profileImageFile'))->toHaveCount(0);
});

it('rejects a photo one byte over the limit with a message naming 15 MB', function (): void {
    $photo = new UploadedFile(ProfilePhotoFixture::jpegOfSize(UserProfileDTO::PROFILE_IMAGE_MAX_BYTES + 1), 'photo.jpg', 'image/jpeg', null, true);
    $violations = $this->validator->validateProperty(new UserProfileDTO(profileImageFile: $photo), 'profileImageFile');

    expect($violations)->toHaveCount(1)
        ->and((string) $violations[0]->getMessage())->toBe('This photo is too large. The maximum size is 15 MB.');
});

it('keeps the constraint, the byte constant and the message in sync', function (): void {
    $constraint = new ReflectionProperty(UserProfileDTO::class, 'profileImageFile')
        ->getAttributes(Assert\Image::class)[0]->newInstance();
    $messages = Yaml::parseFile(dirname(__DIR__, 5).'/translations/validators.en.yaml');

    expect($constraint->maxSize)->toBe(UserProfileDTO::PROFILE_IMAGE_MAX_BYTES)
        ->and($constraint->maxSizeMessage)->toBe(UserProfileDTO::PROFILE_IMAGE_TOO_LARGE_MESSAGE)
        ->and($constraint->uploadIniSizeErrorMessage)->toBe(UserProfileDTO::PROFILE_IMAGE_TOO_LARGE_MESSAGE)
        ->and($messages['user']['profile_image']['too_large'])->toContain(sprintf('%d MB', UserProfileDTO::PROFILE_IMAGE_MAX_SIZE_MB))
        ->and($messages['user']['profile_image']['request_too_large'])->toContain(sprintf('%d MB', UserProfileDTO::PROFILE_IMAGE_MAX_SIZE_MB));
});

it('accepts PNG and WebP photos of exactly the limit', function (string $contents, string $mimeType): void {
    $photo = new UploadedFile(ProfilePhotoFixture::fromContents($contents, UserProfileDTO::PROFILE_IMAGE_MAX_BYTES), 'photo', $mimeType, null, true);

    expect($this->validator->validateProperty(new UserProfileDTO(profileImageFile: $photo), 'profileImageFile'))->toHaveCount(0);
})->with('profilePhotoOtherTypes');

it('rejects PNG and WebP photos one byte over the limit with the fixed message', function (string $contents, string $mimeType): void {
    $photo = new UploadedFile(ProfilePhotoFixture::fromContents($contents, UserProfileDTO::PROFILE_IMAGE_MAX_BYTES + 1), 'photo', $mimeType, null, true);
    $violations = $this->validator->validateProperty(new UserProfileDTO(profileImageFile: $photo), 'profileImageFile');

    expect($violations)->toHaveCount(1)
        ->and((string) $violations[0]->getMessage())->toBe('This photo is too large. The maximum size is 15 MB.');
})->with('profilePhotoOtherTypes');

it('rejects disallowed photo types by their content, not their name', function (string $contents): void {
    $photo = new UploadedFile(ProfilePhotoFixture::fromContents($contents, strlen($contents)), 'photo', 'image/jpeg', null, true);
    $violations = $this->validator->validateProperty(new UserProfileDTO(profileImageFile: $photo), 'profileImageFile');

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->getCode())->toBe(Assert\Image::INVALID_MIME_TYPE_ERROR);
})->with('profilePhotoDisallowedTypes');

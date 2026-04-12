<?php

declare(strict_types=1);

use App\Module\Admin\DTO\UserProfileDTO;
use App\Module\Admin\Form\UserProfileType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

it('configures data_class as UserProfileDTO with admin translation domain', function (): void {
    $formType = new UserProfileType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);

    $resolved = $resolver->resolve();

    expect($resolved['data_class'])->toBe(UserProfileDTO::class)
        ->and($resolved['translation_domain'])->toBe('admin');
});

it('adds all expected fields with correct types', function (): void {
    $fields = [];

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function (string $child, string $type, array $options = []) use (&$fields, $builder) {
            $fields[$child] = ['type' => $type, 'options' => $options];

            return $builder;
        }
    );

    $formType = new UserProfileType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($fields)->toHaveCount(13)
        ->and($fields['firstName']['type'])->toBe(TextType::class)
        ->and($fields['lastName']['type'])->toBe(TextType::class)
        ->and($fields['workingDays']['type'])->toBe(ChoiceType::class)
        ->and($fields['holidayCalendar']['type'])->toBe(EntityType::class)
        ->and($fields['subdivisionCode']['type'])->toBe(TextType::class)
        ->and($fields['birthDate']['type'])->toBe(DateType::class)
        ->and($fields['contractStartedAt']['type'])->toBe(DateType::class)
        ->and($fields['absenceBalanceResetDay']['type'])->toBe(DateType::class)
        ->and($fields['hasCelebrateWorkAnniversary']['type'])->toBe(CheckboxType::class)
        ->and($fields['isEmailNotificationsEnabled']['type'])->toBe(CheckboxType::class)
        ->and($fields['slackStatusSyncEnabled']['type'])->toBe(CheckboxType::class)
        ->and($fields['profileImageFile']['type'])->toBe(FileType::class)
        ->and($fields['removeProfileImage']['type'])->toBe(HiddenType::class);
});

it('workingDays is expanded multiple choice', function (): void {
    $fields = [];

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function (string $child, string $type, array $options = []) use (&$fields, $builder) {
            $fields[$child] = $options;

            return $builder;
        }
    );

    $formType = new UserProfileType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($fields['workingDays']['expanded'])->toBeTrue()
        ->and($fields['workingDays']['multiple'])->toBeTrue()
        ->and($fields['workingDays']['required'])->toBeTrue();
});

it('contractStartedAt and absenceBalanceResetDay are disabled', function (): void {
    $fields = [];

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function (string $child, string $type, array $options = []) use (&$fields, $builder) {
            $fields[$child] = $options;

            return $builder;
        }
    );

    $formType = new UserProfileType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($fields['contractStartedAt']['disabled'])->toBeTrue()
        ->and($fields['absenceBalanceResetDay']['disabled'])->toBeTrue();
});

it('profileImageFile and removeProfileImage are not required', function (): void {
    $fields = [];

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function (string $child, string $type, array $options = []) use (&$fields, $builder) {
            $fields[$child] = $options;

            return $builder;
        }
    );

    $formType = new UserProfileType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($fields['profileImageFile']['required'])->toBeFalse()
        ->and($fields['removeProfileImage']['required'])->toBeFalse();
});

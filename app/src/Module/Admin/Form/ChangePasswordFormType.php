<?php

declare(strict_types=1);

namespace App\Module\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'settings.account_security.field.current_password',
                'translation_domain' => 'admin',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'settings.account_security.field.new_password',
                    'translation_domain' => 'admin',
                ],
                'second_options' => [
                    'label' => 'settings.account_security.field.confirm_password',
                    'translation_domain' => 'admin',
                ],
                'invalid_message' => 'settings.account_security.error.passwords_must_match',
                'translation_domain' => 'admin',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 8),
                ],
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Module\User\Form;

use App\Module\User\DTO\PasswordResetDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PasswordResetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'password_reset.error.passwords_must_match',
                'first_options' => ['label' => 'password_reset.new_password'],
                'second_options' => ['label' => 'password_reset.confirm_password'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PasswordResetDTO::class,
            'translation_domain' => 'messages',
        ]);
    }
}

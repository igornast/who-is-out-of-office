<?php

declare(strict_types=1);

namespace App\Module\Admin\Form;

use App\Module\Admin\DTO\TwoFactorDisableDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TwoFactorDisableType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('password', PasswordType::class, [
                'label' => 'settings.two_factor.disable.field.password',
                'translation_domain' => 'admin',
                'attr' => [
                    'autocomplete' => 'current-password',
                    'class' => 'form-control',
                ],
            ])
            ->add('totpCode', TextType::class, [
                'label' => 'settings.two_factor.disable.field.totp_code',
                'translation_domain' => 'admin',
                'attr' => [
                    'autocomplete' => 'one-time-code',
                    'inputmode' => 'numeric',
                    'maxlength' => 6,
                    'placeholder' => '000000',
                    'class' => 'form-control',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TwoFactorDisableDTO::class,
        ]);
    }
}

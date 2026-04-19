<?php

declare(strict_types=1);

namespace App\Module\Admin\Form;

use App\Module\Admin\DTO\TwoFactorSetupDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TwoFactorSetupVerifyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'settings.two_factor.setup.field.current_password',
                'translation_domain' => 'admin',
                'attr' => [
                    'autocomplete' => 'current-password',
                    'class' => 'form-control',
                ],
            ])
            ->add('verificationCode', TextType::class, [
                'label' => 'settings.two_factor.setup.field.verification_code',
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
            'data_class' => TwoFactorSetupDTO::class,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Admin\Form;

use App\Module\Admin\DTO\AdjustBalanceDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdjustBalanceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('delta', IntegerType::class, [
                'label' => 'user.adjust_balance.delta',
                'help' => 'user.adjust_balance.delta_help',
                'translation_domain' => 'admin',
            ])
            ->add('reason', TextType::class, [
                'label' => 'user.adjust_balance.reason',
                'help' => 'user.adjust_balance.reason_help',
                'translation_domain' => 'admin',
                'empty_data' => '',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AdjustBalanceDTO::class]);
    }
}

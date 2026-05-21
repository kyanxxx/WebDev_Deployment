<?php

namespace App\Form;

use App\Entity\Orders;
use App\Entity\Products;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'class' => Products::class,
                'choice_label' => 'name', // shows product names from menu
                'placeholder' => 'Select a product',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('quantity', IntegerType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter quantity',
                    'min' => 1
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Orders::class,
        ]);
    }
}

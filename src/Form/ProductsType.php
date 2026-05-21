<?php

namespace App\Form;

use App\Entity\Products;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProductsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Static list of product names for dropdown
        $productChoices = [
            'Iced Latte' => 'Iced Latte',
            'Caramel Macchiato' => 'Caramel Macchiato',
            'Mocha' => 'Mocha',
            'Cappuccino' => 'Cappuccino',
            'Americano' => 'Americano',
            'Frappuccino' => 'Frappuccino',
            'Vanilla Latte' => 'Vanilla Latte',
            'Hazelnut Cold Brew' => 'Hazelnut Cold Brew',
            'Thai Tea' => 'Thai Tea',
            'Chai Tea' => 'Chai Tea',
            'Matcha Latte' => 'Matcha Latte',
            'Hot Chocolate' => 'Hot Chocolate',
            'Herbal Tea' => 'Herbal Tea',
            'Croissant' => 'Croissant',
            'Blueberry Muffin' => 'Blueberry Muffin',
            'Chocolate Cookie' => 'Chocolate Cookie',
            'Avocado Toast' => 'Avocado Toast',
        ];
        
        $builder
            ->add('name', ChoiceType::class, [
                'choices' => $productChoices,
                'placeholder' => 'Select a product name',
                'required' => true,
                'attr' => [
                    'id' => 'product_name',
                    'class' => 'form-control',
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'id' => 'product_description',
                    'class' => 'form-control',
                    'rows' => 3,
                ],
            ])
            ->add('price', NumberType::class, [
                'required' => true,
                'scale' => 2,
                'attr' => [
                    'id' => 'product_price',
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => '0',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Products::class,
        ]);
    }
}

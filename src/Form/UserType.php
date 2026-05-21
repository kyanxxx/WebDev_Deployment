<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;
        $user = $options['user'] ?? null;

        $builder
            ->add('username', TextType::class, [
                'label' => 'Username',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Enter username',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter email address',
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Role',
                'choices' => [
                    'Administrator' => 'ROLE_ADMIN',
                    'Staff' => 'ROLE_STAFF',
                    'User' => 'ROLE_USER',
                ],
                'multiple' => false,
                'expanded' => false,
                'required' => true,
                'placeholder' => 'Select a role',
            ]);

        // Add data transformer for roles (convert array to string and vice versa)
        $builder->get('roles')
            ->addModelTransformer(new CallbackTransformer(
                // Transform array to string (for form display)
                function ($rolesArray) {
                    if (empty($rolesArray)) {
                        return 'ROLE_USER';
                    }
                    // Get the primary role (Admin > Staff > User)
                    if (in_array('ROLE_ADMIN', $rolesArray, true)) {
                        return 'ROLE_ADMIN';
                    }
                    if (in_array('ROLE_STAFF', $rolesArray, true)) {
                        return 'ROLE_STAFF';
                    }
                    return 'ROLE_USER';
                },
                // Transform string to array (for entity storage)
                function ($roleString) {
                    return [$roleString];
                }
            ));
        
        $builder
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Active' => 'active',
                    'Disabled' => 'disabled',
                    'Archived' => 'archived',
                ],
                'required' => true,
                'data' => $user?->getStatus() ?? 'active',
            ]);

        // Password field - always show, but optional in edit mode
        $builder->add('plainPassword', PasswordType::class, [
            'label' => $isEdit ? 'New Password (leave blank to keep current)' : 'Password',
            'mapped' => false,
            'required' => !$isEdit,
            'attr' => [
                'placeholder' => $isEdit ? 'Enter new password or leave blank' : 'Enter password',
                'autocomplete' => 'new-password',
            ],
            'constraints' => $isEdit ? [] : [
                new NotBlank([
                    'message' => 'Please enter a password',
                ]),
                new Length([
                    'min' => 6,
                    'minMessage' => 'Your password should be at least {{ limit }} characters',
                    'max' => 4096,
                ]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
            'reset_password' => false,
            'user' => null,
        ]);
    }
}


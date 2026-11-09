<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire Symfony "UserType".
 *
 * Associé à l'objet User.
 */
class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'user@example.com'
                ]
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Jean'
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Dupont'
                ]
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'multiple' => true,
                'choices' => [
                    'Utilisateur' => 'ROLE_USER',
                    'Administrateur' => 'ROLE_ADMIN',
                    'Super Administrateur' => 'ROLE_SUPER_ADMIN',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
                'required' => true,
            ])
            ->add('isEnabled', CheckboxType::class, [
                'label' => 'Compte activé',
                'required' => false,
                'label_attr' => ['class' => 'form-check-label'],
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('isVerified', CheckboxType::class, [
                'label' => 'Email vérifié',
                'required' => false,
                'label_attr' => ['class' => 'form-check-label'],
                'attr' => ['class' => 'form-check-input'],
            ])
        ;

        // Si on est en mode création ou si l'option edit_password est true
        if ($options['edit_password'] || !$options['data']->getId()) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les mots de passe doivent correspondre.',
                'options' => [
                    'attr' => [
                        'class' => 'form-control password-field',
                        'autocomplete' => 'new-password'
                    ]
                ],
                'required' => $options['require_password'],
                'first_options'  => [
                    'label' => 'Mot de passe',
                    'attr' => [
                        'placeholder' => '••••••••',
                        'class' => 'form-control'
                    ]
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => [
                        'placeholder' => '••••••••',
                        'class' => 'form-control'
                    ]
                ],
                'mapped' => false,
                'constraints' => $options['require_password'] ? [
                    new NotBlank([
                        'message' => 'Veuillez entrer un mot de passe',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Votre mot de passe doit contenir au moins {{ limit }} caractères',
                        'max' => 4096,
                    ]),
                ] : [],
            ]);
        }

        // Champs optionnels selon les besoins
        if ($options['with_phone']) {
            $builder->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '+33 1 23 45 67 89'
                ]
            ]);
        }

        if ($options['with_address']) {
            $builder
                ->add('address', TextType::class, [
                    'label' => 'Adresse',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => '123 rue de la Paix'
                    ]
                ])
                ->add('postalCode', TextType::class, [
                    'label' => 'Code postal',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => '75000'
                    ]
                ])
                ->add('city', TextType::class, [
                    'label' => 'Ville',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Paris'
                    ]
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'require_password' => false,
            'edit_password' => false,
            'with_phone' => false,
            'with_address' => false,
        ]);
    }
}
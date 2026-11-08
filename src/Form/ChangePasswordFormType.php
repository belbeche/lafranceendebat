<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire Symfony « ChangePasswordFormType ».
 */
class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'attr' => [
                        'placeholder' => '10 caractères minimum',
                        'class' => 'form-control',
                        'autocomplete' => 'new-password'
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmez le mot de passe',
                    'attr' => [
                        'placeholder' => 'Répétez le mot de passe',
                        'class' => 'form-control',
                        'autocomplete' => 'new-password'
                    ],
                ],
                'invalid_message' => 'Les mots de passe doivent correspondre',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un mot de passe',
                    ]),
                    new Length(min: 10, minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.', max: 4096),
                    new \Symfony\Component\Validator\Constraints\NotCompromisedPassword(message: 'Ce mot de passe figure dans des fuites de données connues. Choisissez-en un autre.', skipOnError: true),
                ],
            ])
        ;
    }
}
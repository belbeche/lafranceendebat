<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Formulaire Symfony "ProfileType".
 *
 * Associé à l'objet User.
 */
class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'Adresse email', 'disabled' => true, 'help' => 'L\'adresse email ne peut pas être modifiée.'])
            ->add('firstName', TextType::class, ['label' => 'Prénom', 'constraints' => [new NotBlank(message: 'Veuillez entrer votre prénom.')]])
            ->add('lastName', TextType::class, ['label' => 'Nom', 'help' => 'Seule l\'initiale de votre nom est affichée publiquement.', 'constraints' => [new NotBlank(message: 'Veuillez entrer votre nom.')]])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal (facultatif)',
                'required' => false,
                'constraints' => [new Regex(pattern: '/^[0-9]{5}$/', message: 'Le code postal doit contenir 5 chiffres.')],
            ])
            ->add('city', TextType::class, ['label' => 'Ville (facultatif)', 'required' => false])
            ->add('phone', TelType::class, ['label' => 'Téléphone (facultatif)', 'required' => false])
            ->add('avatarFile', FileType::class, [
                'label' => 'Photo de profil (facultatif)',
                'required' => false,
                'mapped' => false,
                'constraints' => [new File(maxSize: '1024k', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], mimeTypesMessage: 'Utilisez une image JPEG, PNG ou WebP.')],
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}

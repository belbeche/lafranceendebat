<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Inscription minimale (RGPD) : email, prenom, nom, mot de passe. Pas d'adresse postale.
 * Le code postal est facultatif et sert uniquement aux consultations territoriales.
 */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('profile', ChoiceType::class, [
                'mapped' => false, 'expanded' => true, 'data' => $options['profile'],
                'label' => 'Vous participez en tant que',
                'choices' => ['Citoyen' => 'citoyen', 'Contribuable' => 'contribuable', 'Association ou collectif' => 'association', 'Entreprise ou indépendant' => 'entreprise', 'Élu ou institution' => 'elu', 'Expert ou médiateur' => 'expert'],
            ])
            ->add('organisation', TextType::class, [
                'mapped' => false, 'required' => false, 'label' => 'Nom de votre organisation (élu, association, entreprise)',
                'help' => 'Obligatoire pour ces profils. Les droits d\'acteur sont accordés après vérification par l\'équipe.',
                'constraints' => [new Length(max: 120)],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [new NotBlank(message: 'Veuillez entrer une adresse email.')],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'help' => 'Votre prénom et l\'initiale de votre nom seront visibles à côté de vos contributions.',
                'constraints' => [new NotBlank(message: 'Veuillez entrer votre prénom.')],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'constraints' => [new NotBlank(message: 'Veuillez entrer votre nom.')],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone (facultatif)', 'required' => false,
                'help' => 'Utile pour les candidatures aux missions et pour les acteurs. Jamais affiché publiquement.',
                'attr' => ['autocomplete' => 'tel'],
                'constraints' => [new Regex(pattern: '/^\+?[0-9 .\-()]{8,20}$/', message: 'Numéro invalide.')],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal (facultatif)',
                'required' => false,
                'constraints' => [new Regex(pattern: '/^[0-9]{5}$/', message: 'Le code postal doit contenir 5 chiffres.')],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'label' => 'Mot de passe',
                'help' => '10 caractères minimum. Utilisez le bouton « afficher » pour vérifier votre saisie.',
                'attr' => ['autocomplete' => 'new-password', 'data-toggle-password' => '1'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez choisir un mot de passe.'),
                    new \Symfony\Component\Validator\Constraints\NotCompromisedPassword(message: 'Ce mot de passe figure dans des fuites de données connues. Choisissez-en un autre.', skipOnError: true),
                    new Length(min: 10, minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.', max: 4096),
                ],
            ])
            ->add('ageOk', CheckboxType::class, [
                'label' => 'J\'ai 15 ans ou plus (ou mon représentant légal m\'autorise à créer ce compte).',
                'mapped' => false,
                'constraints' => [new IsTrue(message: 'La création d\'un compte est ouverte à partir de 15 ans.')],
            ])
            ->add('agreeCharter', CheckboxType::class, [
                'label' => 'Je m\'engage à respecter la charte de paix (débattre sans insulter, menacer ni diffuser de rumeur) et j\'accepte les conditions d\'utilisation et la politique de confidentialité.',
                'mapped' => false,
                'constraints' => [new IsTrue(message: 'Vous devez accepter la charte de paix et les conditions pour créer un compte.')],
            ])
            ->add('newsletter', CheckboxType::class, [
                'label' => 'Je souhaite recevoir la lettre de La France en Débat (facultatif). Un e-mail de confirmation me sera envoyé, et je pourrai me désinscrire en un clic.',
                'mapped' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'profile' => 'citoyen']);
        $resolver->setAllowedTypes('profile', 'string');
    }
}

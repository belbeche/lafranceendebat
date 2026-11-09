<?php
// src/Form/MissionType.php

namespace App\Form;

use App\Entity\Mission;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

/**
 * Formulaire Symfony "MissionType".
 *
 * Associé à l'objet Mission.
 */
class MissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'form-control']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description courte',
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('contenu', TextareaType::class, [
                'label' => 'Contenu détaillé (une ligne par point)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 5],
                'help' => 'Pour les listes à puces, mettez chaque élément sur une nouvelle ligne'
            ])
            ->add('icone', TextType::class, [
                'label' => 'Icône FontAwesome',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'help' => 'Ex: fas fa-handshake, fas fa-users'
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Valeur' => 'valeur',
                    'Action' => 'action',
                    'Témoignage' => 'temoignage'
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('statistique', TextType::class, [
                'label' => 'Statistique (pour les actions)',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'help' => 'Ex: +2,000 repas/mois, 85% de réussite'
            ])
            ->add('badge', TextType::class, [
                'label' => 'Badge (pour les actions)',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'help' => 'Ex: bg-primary, bg-success'
            ])
            ->add('ordreAffichage', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'attr' => ['class' => 'form-control']
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Mission::class,
        ]);
    }
}
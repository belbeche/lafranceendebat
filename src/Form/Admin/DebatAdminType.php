<?php

namespace App\Form\Admin;

use App\Entity\Debat;
use App\Entity\Proposition;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire Symfony "DebatAdminType".
 *
 * Associé à l'objet Debat.
 */
class DebatAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('slug', TextType::class, ['label' => 'Lien (minuscules et tirets)'])
            ->add('theme', ChoiceType::class, ['label' => 'Thème', 'choices' => array_combine(Proposition::THEMES, Proposition::THEMES)])
            ->add('summary', TextareaType::class, ['label' => 'Présentation neutre du problème', 'attr' => ['rows' => 5]])
            ->add('facts', TextareaType::class, ['label' => 'Faits vérifiés, avec source et date', 'required' => false, 'attr' => ['rows' => 6]])
            ->add('competentAuthority', TextType::class, ['label' => 'Autorité compétente', 'required' => false])
            ->add('status', ChoiceType::class, ['label' => 'Statut', 'choices' => array_flip(\App\Entity\Debat::STATUS_LABELS)])
            ->add('closesAt', DateTimeType::class, ['label' => 'Clôture prévue', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('outcome', TextareaType::class, ['label' => 'Compte rendu et suites données', 'required' => false, 'attr' => ['rows' => 5]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Debat::class]);
    }
}

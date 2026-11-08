<?php

namespace App\Form\Admin;

use App\Entity\Revendication;
use App\Entity\Proposition;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\DateType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire Symfony « RevendicationAdminType ».
 *
 * Associé à l'objet Revendication.
 */
class RevendicationAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Intitulé de la revendication'])
            ->add('slug', TextType::class, ['label' => 'Lien (minuscules et tirets)'])
            ->add('groupLabel', ChoiceType::class, ['label' => 'Portée par', 'choices' => array_combine(Revendication::GROUPS, Revendication::GROUPS)])
            ->add('theme', ChoiceType::class, ['label' => 'Thème', 'choices' => array_combine(Proposition::THEMES, Proposition::THEMES)])
            ->add('summary', TextareaType::class, ['label' => 'Ce qui est dénoncé ou demandé', 'attr' => ['rows' => 5]])
            ->add('verificationNote', TextareaType::class, ['label' => 'Ce que la source établit, et ce qui reste à vérifier', 'required' => false, 'attr' => ['rows' => 4]])
            ->add('sourceLabel', TextType::class, ['label' => 'Nom de la source', 'required' => false])
            ->add('sourceUrl', UrlType::class, ['label' => 'Lien vers la source', 'required' => false, 'default_protocol' => 'https'])
            ->add('competentAuthority', TextType::class, ['label' => 'Autorité qui peut répondre', 'required' => false])
            ->add('legalPath', TextType::class, ['label' => 'Voie juridique envisageable', 'required' => false])
            ->add('status', ChoiceType::class, ['label' => 'Suite donnée', 'choices' => array_flip(Revendication::STATUS_LABELS)])
            ->add('locale', ChoiceType::class, ['label' => 'Langue de cette revendication (lue par les visiteurs de cette langue)', 'choices' => array_combine(Revendication::LOCALES, Revendication::LOCALES)])
            ->add('isPublished', CheckboxType::class, ['label' => 'Publiée', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Revendication::class]);
    }
}

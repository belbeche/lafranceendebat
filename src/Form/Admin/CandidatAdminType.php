<?php

namespace App\Form\Admin;

use App\Entity\Candidat;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire Symfony « CandidatAdminType ».
 *
 * Associé à l'objet Candidat.
 */
class CandidatAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('slug', TextType::class, ['label' => 'Lien (minuscules et tirets)'])
            ->add('declaredLabel', TextType::class, ['label' => 'Étiquette politique déclarée par la personne', 'required' => false])
            ->add('role', TextType::class, ['label' => 'Fonction ou mandat', 'required' => false])
            ->add('biography', TextareaType::class, ['label' => 'Biographie factuelle', 'required' => false, 'attr' => ['rows' => 5]])
            ->add('programUrl', UrlType::class, ['label' => 'Lien vers le programme officiel', 'required' => false, 'default_protocol' => 'https'])
            ->add('acceptsPublicDebate', CheckboxType::class, ['label' => 'A accepté un débat public contradictoire', 'required' => false])
            ->add('isPublished', CheckboxType::class, ['label' => 'Publié', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Candidat::class]);
    }
}

<?php

namespace App\Form\Admin;

use App\Entity\Event;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire Symfony "EventAdminType".
 *
 * Associé à l'objet Event.
 */
class EventAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Intitulé'])
            ->add('kind', ChoiceType::class, ['label' => 'Type de rencontre', 'choices' => array_flip(\App\Entity\Event::KINDS), 'required' => false])
            ->add('date', DateTimeType::class, ['label' => 'Date et heure', 'widget' => 'single_text'])
            ->add('location', TextType::class, ['label' => 'Lieu ou "En ligne"', 'required' => false])
            ->add('description', TextareaType::class, ['label' => 'Description et règles de la rencontre', 'attr' => ['rows' => 5]])
            ->add('link', UrlType::class, ['label' => 'Lien d\'inscription ou de diffusion', 'required' => false, 'default_protocol' => 'https'])
            ->add('status', ChoiceType::class, ['label' => 'Statut', 'choices' => ['À venir (publié)' => 'upcoming', 'En validation (proposé par un acteur)' => 'pending', 'Terminé' => 'completed', 'Annulé' => 'cancelled', 'Refusé' => 'rejected']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Event::class]);
    }
}

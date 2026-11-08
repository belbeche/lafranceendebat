<?php

namespace App\Form\Admin;

use App\Entity\Transmission;
use App\Entity\Proposition;
use App\Entity\Revendication;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire Symfony « TransmissionAdminType ».
 *
 * Associé à l'objet Transmission.
 */
class TransmissionAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('revendication', EntityType::class, ['class' => Revendication::class, 'choice_label' => 'title', 'required' => false, 'label' => 'Revendication concernée'])
            ->add('proposition', EntityType::class, ['class' => Proposition::class, 'choice_label' => 'title', 'required' => false, 'label' => 'Proposition concernée'])
            ->add('recipient', TextType::class, ['label' => 'Destinataire (institution, service ou personne)'])
            ->add('route', ChoiceType::class, ['label' => 'Voie utilisée', 'choices' => array_flip(Transmission::ROUTES)])
            ->add('status', ChoiceType::class, ['label' => 'Statut', 'choices' => array_flip(Transmission::STATUS_LABELS)])
            ->add('sentAt', DateTimeType::class, ['label' => 'Envoyée le', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('message', TextareaType::class, ['label' => 'Texte transmis', 'required' => false, 'attr' => ['rows' => 6]])
            ->add('responseSummary', TextareaType::class, ['label' => 'Réponse reçue (citer, ne pas reformuler)', 'required' => false, 'attr' => ['rows' => 4]])
            ->add('referenceUrl', UrlType::class, ['label' => 'Lien de référence (accusé, réponse, page officielle)', 'required' => false, 'default_protocol' => 'https']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Transmission::class]);
    }
}

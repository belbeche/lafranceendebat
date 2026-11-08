<?php
// src/Form/ImportCampaignType.php

namespace App\Form;

use App\Entity\NewsletterCampaign;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

/**
 * Formulaire Symfony « ImportCampaignType ».
 */
class ImportCampaignType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $campaigns = $options['campaigns'] ?? [];
        
        $builder
            ->add('campaign_mode', ChoiceType::class, [
                'label' => 'Mode de campagne',
                'choices' => [
                    'Utiliser une campagne existante' => 'existing',
                    'Créer une nouvelle campagne' => 'new',
                ],
                'expanded' => true,
                'multiple' => false,
                'required' => true,
            ])
            ->add('existing_campaign', EntityType::class, [
                'label' => 'Campagne existante',
                'class' => NewsletterCampaign::class,
                'choices' => $campaigns,
                'choice_label' => 'name',
                'placeholder' => 'Sélectionnez une campagne',
                'required' => false,
            ])
            ->add('campaign_name', TextType::class, [
                'label' => 'Nom de la campagne',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Nom de la campagne',
                ],
            ])
            ->add('campaign_source', TextType::class, [
                'label' => 'Source de la campagne',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: Import CSV, Site web, etc.',
                ],
            ])
            ->add('campaign_date', DateType::class, [
                'label' => 'Date de la campagne',
                'widget' => 'single_text',
                'required' => false,
                'data' => new \DateTime(),
            ])
            ->add('campaign_tags', TextType::class, [
                'label' => 'Tags',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Tags séparés par des virgules',
                ],
            ]);
    }
    
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'campaigns' => [],
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'import_campaign',
        ]);
        
        $resolver->setAllowedTypes('campaigns', 'array');
    }
}

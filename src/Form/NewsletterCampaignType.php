<?php

// src/Form/NewsletterCampaignType.php

namespace App\Form;

use App\Entity\NewsletterCampaign;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * Formulaire Symfony "NewsletterCampaignType".
 *
 * Associé à l'objet NewsletterCampaign.
 */
class NewsletterCampaignType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', TextType::class, [
                'label' => 'Sujet *',
                'required' => true,
                'constraints' => [new NotBlank(['message' => 'Le sujet est obligatoire'])],
                'attr' => [
                    'placeholder' => 'Sujet de la newsletter',
                    'maxlength' => 150,
                    'class' => 'form-control'
                ]
            ])
            ->add('previewText', TextType::class, [
                'label' => 'Texte de prévisualisation',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Texte visible dans l\'aperçu de l\'email',
                    'maxlength' => 250,
                    'class' => 'form-control'
                ]
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu *',
                'required' => true,
                'constraints' => [new NotBlank(['message' => 'Le contenu est obligatoire'])],
                'attr' => [
                    'rows' => 15,
                    'class' => 'form-control editor',
                    'placeholder' => 'Contenu de votre newsletter...'
                ]
            ])
            ->add('senderName', TextType::class, [
                'label' => 'Nom de l\'expéditeur',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Votre nom ou société',
                    'class' => 'form-control'
                ]
            ])
            ->add('senderEmail', TextType::class, [
                'label' => 'Email de l\'expéditeur *',
                'required' => true,
                'constraints' => [new NotBlank(['message' => 'L\'email de l\'expéditeur est obligatoire'])],
                'attr' => [
                    'placeholder' => 'newsletter@exemple.com',
                    'class' => 'form-control'
                ]
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priorité',
                'choices' => [
                    'Basse' => 'low',
                    'Normale' => 'normal',
                    'Haute' => 'high',
                ],
                'required' => true,
                'data' => 'normal',
                'attr' => ['class' => 'form-select']
            ])
            ->add('template', ChoiceType::class, [
                'label' => 'Template',
                'choices' => [
                    'Simple' => 'simple',
                    'Avec en-tête' => 'header',
                    'Responsive' => 'responsive'
                ],
                'required' => true,
                'data' => 'simple',
                'attr' => ['class' => 'form-select']
            ])
            ->add('trackOpens', CheckboxType::class, [
                'label' => 'Suivre les ouvertures',
                'required' => false,
                'data' => true,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('trackClicks', CheckboxType::class, [
                'label' => 'Suivre les clics',
                'required' => false,
                'data' => true,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('scheduledFor', DateTimeType::class, [
                'label' => 'Date de planification',
                'required' => false,
                'widget' => 'single_text',
                'html5' => false,
                'attr' => [
                    'class' => 'form-control datetimepicker-input',
                    'data-toggle' => 'datetimepicker',
                    'data-target' => '#newsletter_campaign_scheduledFor',
                ],
            ])
            ->add('importFile', FileType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'accept' => '.csv,.txt',
                    'class' => 'form-control d-none'
                ]
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NewsletterCampaign::class,
            'attr' => [
                'class' => 'needs-validation',
                'novalidate' => true,
                'autocomplete' => 'off'
            ]
        ]);
    }
}
<?php
// src/Form/PostType.php

namespace App\Form;

use App\Entity\Post;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Validator\Constraints\File;

/**
 * Formulaire Symfony « PostType ».
 *
 * Associé à l'objet Post.
 */
class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de la publication',
                'attr' => ['class' => 'form-control']
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'attr' => ['class' => 'form-control', 'rows' => 5]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de publication',
                'choices' => [
                    'Texte' => 'text',
                    'Image' => 'image',
                    'Vidéo' => 'video',
                    'Document' => 'document'
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('mediaFile', FileType::class, [
                'label' => 'Fichier média',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '1024M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'video/mp4',
                            'video/mpeg',
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader un fichier valide (image, vidéo ou document)',
                    ])
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'Publier immédiatement',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
        ]);
    }
}
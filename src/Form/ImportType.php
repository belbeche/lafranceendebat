<?php
// src/Form/ImportType.php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Validator\Constraints\File;

/**
 * Envoi du fichier CSV pour l'import de contacts (séparateur, en-tête, doublons). Pour accepter un autre
 * format, adapter les contraintes du champ du fichier et le lecteur d'import.
 */
class ImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('csv_file', FileType::class, [
                'label' => 'Fichier CSV',
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '500M',
                        'mimeTypes' => [
                            'text/csv',
                            'text/plain',
                            'text/tab-separated-values',
                            'application/csv',
                            'application/vnd.ms-excel',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger un fichier CSV valide',
                    ]),
                ],
            ])
            ->add('delimiter', ChoiceType::class, [
                'label' => 'Séparateur',
                'choices' => [
                    'Virgule (,)' => ',',
                    'Point-virgule (;)' => ';',
                    'Tabulation' => "\t",
                    'Pipe (|)' => '|',
                ],
                'data' => ',',
            ])
            ->add('has_header', CheckboxType::class, [
                'label' => 'Le fichier contient une ligne d\'en-tête',
                'required' => false,
                'data' => true,
            ])
            ->add('skip_duplicates', CheckboxType::class, [
                'label' => 'Ignorer les doublons',
                'required' => false,
                'data' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'import_item',
        ]);
    }
}

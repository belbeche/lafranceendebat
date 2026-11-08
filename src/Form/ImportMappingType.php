<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * Formulaire Symfony « ImportMappingType ».
 */
class ImportMappingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $mapping = $options['mapping'] ?? [];
        $headers = $options['headers'] ?? [];

        // Créer les options de colonnes
        $columnChoices = ['-- Ignorer --' => '--ignore--'];
        foreach ($headers as $index => $header) {
            $headerLabel = !empty($header) ? $header : "Colonne " . ($index + 1);
            $columnChoices[$headerLabel] = 'col_' . $index;
        }

        // Ajouter les champs de mapping
        foreach ($mapping as $fieldName => $fieldConfig) {
            $label = $fieldConfig['label'] ?? $fieldName;
            $required = $fieldConfig['required'] ?? false;
            $default = $fieldConfig['default'] ?? '--ignore--';

            $builder->add('mapping_' . $fieldName, ChoiceType::class, [
                'label' => $label . ($required ? ' *' : ''),
                'required' => false,
                'choices' => $columnChoices,
                'placeholder' => 'Sélectionner une colonne...',
                'data' => $default,
                'help' => $fieldConfig['description'] ?? null,
                'attr' => [
                    'class' => 'form-select mapping-select',
                    'data-field' => $fieldName,
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'mapping' => [],
            'headers' => [],
        ]);
        $resolver->setAllowedTypes('mapping', 'array');
        $resolver->setAllowedTypes('headers', 'array');
    }
}
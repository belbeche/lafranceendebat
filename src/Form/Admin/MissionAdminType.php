<?php

namespace App\Form\Admin;

use App\Entity\Mission;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire Symfony « MissionAdminType ».
 *
 * Associé à l'objet Mission.
 */
class MissionAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, ['label' => 'Titre'])
            ->add('description', TextareaType::class, ['label' => 'Description courte', 'attr' => ['rows' => 3]])
            ->add('contenu', TextareaType::class, ['label' => 'Détail (une ligne par point)', 'required' => false, 'attr' => ['rows' => 5]])
            ->add('icone', TextType::class, ['label' => 'Icône (ex. fas fa-handshake)', 'required' => false])
            ->add('type', ChoiceType::class, ['label' => 'Type', 'choices' => ['Valeur' => 'valeur', 'Action' => 'action', 'Témoignage' => 'temoignage']])
            ->add('ordreAffichage', IntegerType::class, ['label' => 'Ordre d\'affichage', 'required' => false])
            ->add('isActive', CheckboxType::class, ['label' => 'Affiché sur le site', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Mission::class]);
    }
}

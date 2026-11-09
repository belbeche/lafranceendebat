<?php

namespace App\Form;

use App\Entity\Signalement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire Symfony "SignalementType".
 *
 * Associé à l'objet Signalement.
 */
class SignalementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reason', ChoiceType::class, [
                'label' => 'Motif du signalement',
                'choices' => array_flip(Signalement::REASONS),
                'expanded' => true,
            ])
            ->add('details', TextareaType::class, [
                'label' => 'Précisions (facultatif)',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 1000],
                'help' => 'Dites en une ou deux phrases ce qui pose problème. Un modérateur humain examinera votre signalement.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Signalement::class]);
    }
}

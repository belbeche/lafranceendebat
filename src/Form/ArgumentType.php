<?php

namespace App\Form;

use App\Entity\Argument;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

/**
 * Formulaire Symfony "ArgumentType".
 *
 * Associé à l'objet Argument.
 */
class ArgumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['is_reply']) {
            $builder->add('reformulation', TextareaType::class, [
                'label' => 'Avant de répondre : ce que je comprends de sa position',
                'required' => false,
                'help' => 'Facultatif mais recommandé. Reformuler permet de vérifier que l\'on s\'est bien compris.',
                'attr' => ['rows' => 2, 'maxlength' => 1000],
            ]);
        }

        $builder
            ->add('stance', ChoiceType::class, [
                'label' => 'Ma position',
                'choices' => array_flip(Argument::STANCES),
                'expanded' => true,
            ])
            ->add('content', TextareaType::class, [
                'label' => $options['is_reply'] ? 'Ma réponse' : 'Mon argument',
                'attr' => ['rows' => 5, 'maxlength' => 3000],
                'help' => 'Appuyez-vous sur des faits. Critiquez une idée, jamais une personne.',
            ])
            ->add('sourceUrl', UrlType::class, [
                'label' => 'Source (lien)',
                'required' => false,
                'default_protocol' => 'https',
            ])
            ->add('charter', CheckboxType::class, [
                'label' => 'Je respecte la charte de paix.',
                'mapped' => false,
                'constraints' => [new IsTrue(message: 'Vous devez accepter la charte de paix pour publier.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Argument::class, 'is_reply' => false]);
        $resolver->setAllowedTypes('is_reply', 'bool');
    }
}

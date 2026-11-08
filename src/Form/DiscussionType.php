<?php

namespace App\Form;

use App\Entity\Discussion;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

/**
 * Formulaire Symfony « DiscussionType ».
 *
 * Associé à l'objet Discussion.
 */
class DiscussionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', ChoiceType::class, ['label' => 'Catégorie', 'choices' => array_flip(Discussion::CATEGORIES)])
            ->add('title', TextType::class, ['label' => 'Titre', 'attr' => ['maxlength' => 160]])
            ->add('body', TextareaType::class, [
                'label' => 'Mon message',
                'attr' => ['rows' => 7, 'maxlength' => 4000],
                'help' => 'Expliquez ce qui vous préoccupe et ce que vous aimeriez construire. Critiquez des idées, jamais des personnes.',
            ])
            ->add('charter', CheckboxType::class, [
                'label' => 'Je respecte la charte de paix : pas d\'insulte, de menace, d\'appel à la violence ni de données personnelles.',
                'mapped' => false,
                'constraints' => [new IsTrue(message: 'Vous devez accepter la charte de paix pour publier.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Discussion::class]);
    }
}

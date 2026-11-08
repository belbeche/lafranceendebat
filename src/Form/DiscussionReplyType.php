<?php

namespace App\Form;

use App\Entity\DiscussionReply;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire Symfony « DiscussionReplyType ».
 *
 * Associé à l'objet DiscussionReply.
 */
class DiscussionReplyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reformulation', TextareaType::class, [
                'label' => 'Avant de répondre : ce que je comprends de ce message',
                'required' => false,
                'attr' => ['rows' => 2, 'maxlength' => 800],
                'help' => 'Facultatif, mais reformuler montre que vous avez écouté et valorise votre contribution.',
            ])
            ->add('body', TextareaType::class, ['label' => 'Ma réponse', 'attr' => ['rows' => 5, 'maxlength' => 3000]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DiscussionReply::class]);
    }
}

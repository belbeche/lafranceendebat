<?php

namespace App\Form;

use App\Entity\NewsletterSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

/**
 * Formulaire Symfony "NewsletterType".
 *
 * Associé à l'objet NewsletterSubscriber.
 */
class NewsletterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Votre email',
                    'class' => 'form-control form-control-lg'
                ]
            ])
            ->add('consent', CheckboxType::class, [
                'label' => 'J\'accepte de recevoir la newsletter et la politique de confidentialité',
                'mapped' => false,
                'required' => true,
                'attr' => ['class' => 'form-check-input']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NewsletterSubscriber::class,
        ]);
    }
}
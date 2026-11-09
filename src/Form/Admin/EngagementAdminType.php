<?php

namespace App\Form\Admin;

use App\Entity\Engagement;
use App\Entity\Candidat;
use App\Entity\Proposition;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire Symfony "EngagementAdminType".
 *
 * Associé à l'objet Engagement.
 */
class EngagementAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('candidat', EntityType::class, ['class' => Candidat::class, 'choice_label' => 'name', 'label' => 'Candidat ou élu'])
            ->add('theme', ChoiceType::class, ['label' => 'Thème', 'choices' => array_combine(Proposition::THEMES, Proposition::THEMES)])
            ->add('title', TextType::class, ['label' => 'Intitulé de l\'engagement'])
            ->add('problem', TextareaType::class, ['label' => 'Problème visé et situation de départ', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('measure', TextareaType::class, ['label' => 'Mesure proposée', 'attr' => ['rows' => 4]])
            ->add('competentAuthority', TextType::class, ['label' => 'Autorité compétente', 'required' => false])
            ->add('announcedCost', TextType::class, ['label' => 'Coût annoncé et méthode de calcul', 'required' => false])
            ->add('financing', TextType::class, ['label' => 'Financement envisagé', 'required' => false])
            ->add('timeline', TextType::class, ['label' => 'Délai de mise en oeuvre', 'required' => false])
            ->add('indicator', TextType::class, ['label' => 'Indicateur de résultat', 'required' => false])
            ->add('risks', TextareaType::class, ['label' => 'Risques et effets secondaires', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('objectionsReply', TextareaType::class, ['label' => 'Réponse aux objections', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('sourceUrl', UrlType::class, ['label' => 'Source vérifiable', 'required' => false, 'default_protocol' => 'https'])
            ->add('status', ChoiceType::class, ['label' => 'Suivi', 'choices' => array_flip(\App\Entity\Engagement::STATUS_LABELS)]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Engagement::class]);
    }
}

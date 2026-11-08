<?php

namespace App\Form;

use App\Entity\Proposition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

/**
 * Formulaire Symfony « PropositionType ».
 *
 * Associé à l'objet Proposition.
 */
class PropositionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de la proposition',
                'help' => 'Une phrase qui dit ce que vous proposez.',
                'attr' => ['maxlength' => 180],
            ])
            ->add('theme', ChoiceType::class, [
                'label' => 'Thème',
                'choices' => array_combine(Proposition::THEMES, Proposition::THEMES),
                'expanded' => true,
            ])
        ;
        if ($options['dossier_choices']) {
            $builder->add('dossierSlug', ChoiceType::class, [
                'label' => 'Dossier de référence',
                'choices' => $options['dossier_choices'],
                'placeholder' => 'Choisir un dossier',
                'required' => true,
                'constraints' => [new \Symfony\Component\Validator\Constraints\NotBlank(message: 'Choisissez un dossier de référence, ou proposez-en un nouveau.')],
                'help' => 'Chaque proposition s\'appuie sur un dossier. Aucun ne convient ? Proposez un nouveau dossier (validé avant publication).',
            ]);
        }
        $builder
            ->add('description', TextareaType::class, [
                'label' => 'La mesure proposée',
                'help' => 'Que faut-il changer, concrètement ? Parlez des faits et des mesures, pas des personnes.',
                'attr' => ['rows' => 7],
            ])
            ->add('sourceUrl', UrlType::class, [
                'label' => 'Source vérifiable (lien)',
                'required' => false,
                'help' => 'INSEE, Cour des comptes, Légifrance, étude publiée... Une source permet de vérifier les faits.',
                'default_protocol' => 'https',
            ])
            ->add('problem', TextareaType::class, [
                'label' => 'Problème visé et situation de départ',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('competentAuthority', TextType::class, [
                'label' => 'Qui peut décider ?',
                'required' => false,
                'help' => 'Commune, département, région, Parlement, gouvernement, Europe...',
            ])
            ->add('estimatedCost', TextType::class, [
                'label' => 'Coût estimé et méthode de calcul',
                'required' => false,
                'help' => 'Si vous ne savez pas, écrivez "à chiffrer". Ne donnez pas de chiffre sans source.',
            ])
            ->add('financing', TextType::class, [
                'label' => 'Financement envisagé',
                'required' => false,
            ])
            ->add('timeline', TextType::class, [
                'label' => 'Délai de mise en oeuvre',
                'required' => false,
            ])
            ->add('indicator', TextType::class, [
                'label' => 'Comment mesurer le résultat ?',
                'required' => false,
            ])
            ->add('risks', TextareaType::class, [
                'label' => 'Risques et effets secondaires possibles',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('peaceContribution', TextareaType::class, [
                'label' => 'En quoi cette proposition favorise le dialogue ou l\'apaisement ?',
                'required' => false,
                'attr' => ['rows' => 3],
                'help' => 'Qui cela rapproche-t-il ? Quelles tensions cela peut-il réduire ?',
            ])
            ->add('petition', CheckboxType::class, [
                'label' => 'Ouvrir cette proposition à signature comme pétition citoyenne',
                'required' => false,
                'help' => 'Une pétition citoyenne ici est consultative : elle mesure le soutien du réseau et se partage facilement. Elle ne remplace pas une pétition officielle.',
            ])
            ->add('petitionGoal', \Symfony\Component\Form\Extension\Core\Type\IntegerType::class, [
                'label' => 'Objectif de signatures (facultatif)',
                'required' => false,
                'attr' => ['min' => 10, 'inputmode' => 'numeric'],
            ])
            ->add('charter', CheckboxType::class, [
                'label' => 'Je m\'engage à respecter la charte de paix : pas d\'insulte, de menace ni de rumeur.',
                'mapped' => false,
                'constraints' => [new IsTrue(message: 'Vous devez accepter la charte de paix pour publier.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Proposition::class, 'dossier_choices' => []]);
    }
}

<?php

namespace App\Form\Admin;

use App\Entity\Proposition;
use App\Entity\Debat;
use App\Entity\Revendication;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire Symfony "PropositionAdminType".
 *
 * Associé à l'objet Proposition.
 */
class PropositionAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('theme', ChoiceType::class, ['label' => 'Thème', 'choices' => array_combine(Proposition::THEMES, Proposition::THEMES)])
            ->add('description', TextareaType::class, ['label' => 'Mesure proposée', 'attr' => ['rows' => 6]])
            ->add('status', ChoiceType::class, ['label' => 'Statut de modération', 'choices' => array_combine(Proposition::STATUSES, Proposition::STATUSES)])
            ->add('moderationNote', TextareaType::class, ['label' => 'Motif communiqué à l\'auteur en cas de rejet', 'required' => false, 'attr' => ['rows' => 2]])
            ->add('origin', TextType::class, ['label' => 'Origine éditoriale (si pas d\'auteur citoyen)', 'required' => false])
            ->add('revendication', EntityType::class, ['class' => Revendication::class, 'choice_label' => 'title', 'required' => false, 'label' => 'Répond à la revendication'])
            ->add('debat', EntityType::class, ['class' => Debat::class, 'choice_label' => 'title', 'required' => false, 'label' => 'Rattachée au débat'])
            ->add('sourceUrl', UrlType::class, ['label' => 'Source', 'required' => false, 'default_protocol' => 'https'])
            ->add('problem', TextareaType::class, ['label' => 'Problème visé', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('competentAuthority', TextType::class, ['label' => 'Autorité compétente', 'required' => false])
            ->add('legalVehicle', TextType::class, ['label' => 'Véhicule juridique (loi, amendement, décret, expérimentation...)', 'required' => false])
            ->add('legalReference', TextType::class, ['label' => 'Texte ou article concerné', 'required' => false])
            ->add('estimatedCost', TextType::class, ['label' => 'Coût estimé et méthode', 'required' => false])
            ->add('financing', TextType::class, ['label' => 'Financement', 'required' => false])
            ->add('timeline', TextType::class, ['label' => 'Délai', 'required' => false])
            ->add('indicator', TextType::class, ['label' => 'Indicateur', 'required' => false])
            ->add('risks', TextareaType::class, ['label' => 'Risques', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('safeguards', TextareaType::class, ['label' => 'Garde-fous', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('peaceContribution', TextareaType::class, ['label' => 'Contribution au dialogue et à l\'apaisement', 'required' => false, 'attr' => ['rows' => 3]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Proposition::class]);
    }
}

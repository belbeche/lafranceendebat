<?php

namespace App\Service\Programme;

use App\Entity\Proposition;

/**
 * Thème INDICATIF d'une proposition, d'après des mots-clés de son titre. Règle simple, publique et sans apprentissage :
 * la première famille de mots trouvée l'emporte, sinon aucun thème. L'interface affiche "thème indicatif".
 */
final class ThemeGuesser
{
    /** @var array<string,list<string>> mot-clé (sans accent, minuscule) par thème de Proposition::THEMES */
    private const KEYWORDS = [
        'Santé' => ['sante', 'hopitaux', 'nutri', 'alcool', 'tabac', 'sucr', 'hopital', 'medecin', 'soins', 'maladie', 'medicament', 'infirmi', 'pharmac', 'handicap', 'dependance'],
        'École et transmission' => ['ecole', 'enseignant', 'eleve', 'scolaire', 'education', 'etudiant', 'universit', 'lycee', 'college'],
        'Justice, Sécurité et réinsertion' => ['justice', 'penal', 'prison', 'magistrat', 'tribunal', 'peine', 'detention', 'securite', 'police', 'gendarm', 'delinquan', 'terroris', 'violence'],
        'Pouvoir d\'achat et logement' => ['logement', 'loyer', 'locataire', 'habitat', 'copropriete', 'hlm', 'pouvoir d\'achat', 'prix'],
        'Écologie et énergie' => ['energie', 'nucleaire', 'electricite', 'carburant', 'renouvelable', 'environnement', 'climat', 'biodiversite', 'pollution', 'ecolog', 'dechet'],
        'Agriculture et alimentation' => ['agricul', 'agriculteur', 'elevage', 'alimentation', 'paysan'],
        'Emploi et formation' => ['emploi', 'travail', 'chomage', 'salari', 'apprentissage', 'formation'],
        'Finances publiques' => ['impot', 'fiscal', 'taxe', 'tva', 'budget', 'finances publiques', 'depenses publiques'],
        'Retraites et solidarités' => ['retraite', 'pension', 'solidarite', 'minima', 'aidant'],
        'Industrie et recherche' => ['industrie', 'recherche', 'innovation', 'numerique', 'entreprise'],
        'Démocratie et dialogue' => ['election', 'referendum', 'citoyen', 'democratie', 'constitution', 'depute', 'senateur', 'parlement'],
        'Défense et résilience' => ['defense', 'armee', 'militaire', 'resilience'],
        'Europe et international' => ['europe', 'international', 'union europeenne', 'traite'],
        'Services publics' => ['service public', 'commune', 'collectivit', 'administration', 'fonctionnaire'],
    ];


    public static function guess(string $text): ?string
    {
        $t = ' ' . mb_strtolower(self::stripAccents($text)) . ' ';
        foreach (self::KEYWORDS as $theme => $words) {
            if (!in_array($theme, Proposition::THEMES, true)) {
                continue;
            }
            foreach ($words as $w) {
                if (str_contains($t, $w)) {
                    return $theme;
                }
            }
        }

        return null;
    }

    private static function stripAccents(string $s): string
    {
        return strtr($s, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c', 'É' => 'E', 'È' => 'E', 'À' => 'A', 'Ç' => 'C']);
    }
}

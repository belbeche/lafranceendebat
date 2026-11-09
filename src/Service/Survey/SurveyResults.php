<?php

namespace App\Service\Survey;

use App\Entity\Survey;
use App\Entity\SurveyResponse;
use Doctrine\ORM\EntityManagerInterface;

/** Depouillement : effectifs, pourcentages, moyenne des echelles, reponses libres. */
class SurveyResults
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function count(Survey $s): int
    {
        return (int) $this->em->createQuery('SELECT COUNT(r.id) FROM App\Entity\SurveyResponse r WHERE r.survey = :s')->setParameter('s', $s)->getSingleScalarResult();
    }

    /** @return list<array<string,mixed>> */
    public function compute(Survey $s, bool $withTexts = false): array
    {
        /** @var list<SurveyResponse> $rows */
        $rows = $this->em->getRepository(SurveyResponse::class)->findBy(['survey' => $s]);
        $total = count($rows);
        $out = [];
        foreach ($s->getQuestions() as $i => $q) {
            $answered = 0;
            $counts = [];
            $texts = [];
            $sum = 0;
            $nums = 0;
            $options = 'echelle' === $q['type'] ? ['1', '2', '3', '4', '5'] : $q['options'];
            foreach ($options as $o) {
                $counts[$o] = 0;
            }
            foreach ($rows as $r) {
                $v = $r->getAnswers()[$i] ?? null;
                if (null === $v || '' === $v || [] === $v) {
                    continue;
                }
                ++$answered;
                if ('texte' === $q['type']) {
                    $texts[] = (string) $v;
                    continue;
                }
                foreach ((array) $v as $one) {
                    $one = (string) $one;
                    if (isset($counts[$one])) {
                        ++$counts[$one];
                        if ('echelle' === $q['type']) {
                            $sum += (int) $one;
                            ++$nums;
                        }
                    }
                }
            }
            $out[] = [
                'label' => $q['label'], 'type' => $q['type'], 'answered' => $answered, 'total' => $total,
                'counts' => $counts, 'percent' => array_map(static fn ($c) => $answered ? round(100 * $c / $answered) : 0, $counts),
                'average' => $nums ? round($sum / $nums, 2) : null,
                'texts' => $withTexts ? $texts : [], 'textCount' => count($texts),
            ];
        }

        return $out;
    }

    /** @return list<array<string,mixed>> une ligne par reponse, pour l'export CSV */
    public function rows(Survey $s): array
    {
        $out = [];
        foreach ($this->em->getRepository(SurveyResponse::class)->findBy(['survey' => $s], ['id' => 'ASC']) as $r) {
            $line = ['date' => $r->getCreatedAt()->format('Y-m-d H:i'), 'canal' => $r->getChannel(), 'lieu' => $r->getPlace() ?? ''];
            foreach ($s->getQuestions() as $i => $q) {
                $line[$q['label']] = implode(' / ', array_map('strval', (array) ($r->getAnswers()[$i] ?? [])));
            }
            $out[] = $line;
        }

        return $out;
    }

    /** @return array<string,int> effectif par canal de collecte : en_ligne, membre, terrain */
    public function channels(Survey $s): array
    {
        $out = ['en_ligne' => 0, 'membre' => 0, 'terrain' => 0];
        foreach ($this->em->getRepository(SurveyResponse::class)->findBy(['survey' => $s]) as $r) {
            ++$out[$r->getChannel()];
        }

        return $out;
    }

    /** @return list<string> lieux de collecte distincts (réponses de terrain) */
    public function places(Survey $s): array
    {
        $out = [];
        foreach ($this->em->getRepository(SurveyResponse::class)->findBy(['survey' => $s, 'profile' => 'terrain']) as $r) {
            $out[$r->getPlace() ?: 'non précisé'] = ($out[$r->getPlace() ?: 'non précisé'] ?? 0) + 1;
        }
        ksort($out);

        return array_map(static fn (string $k) => $k . ' (' . $out[$k] . ')', array_keys($out));
    }

    /**
     * Empreinte SHA-256 des questions et de toutes les réponses, dans un ordre fixe.
     * Toute modification, ajout ou suppression d'une réponse change l'empreinte : elle prouve que le procès-verbal
     * correspond exactement aux données au moment de son édition.
     */
    public function fingerprint(Survey $s): string
    {
        $payload = ['questions' => $s->getQuestions(), 'responses' => []];
        foreach ($this->em->getRepository(SurveyResponse::class)->findBy(['survey' => $s], ['id' => 'ASC']) as $r) {
            $payload['responses'][] = [$r->getCreatedAt()->format('c'), $r->getChannel(), $r->getPlace(), $r->getAnswers()];
        }

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}

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
            $line = ['date' => $r->getCreatedAt()->format('Y-m-d H:i'), 'profil' => $r->getProfile() ?? 'anonyme'];
            foreach ($s->getQuestions() as $i => $q) {
                $line[$q['label']] = implode(' / ', array_map('strval', (array) ($r->getAnswers()[$i] ?? [])));
            }
            $out[] = $line;
        }

        return $out;
    }
}

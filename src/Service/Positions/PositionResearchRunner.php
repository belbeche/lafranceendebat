<?php

namespace App\Service\Positions;

use App\Entity\Candidat;
use App\Entity\PositionStance;
use App\Entity\PositionStatement;
use Doctrine\ORM\EntityManagerInterface;

/** Lance la recherche pour une affirmation, par lots, sans jamais écraser une position existante. */
class PositionResearchRunner
{
    public function __construct(private readonly StanceResearcher $researcher, private readonly EntityManagerInterface $em)
    {
    }

    /** @return array{added:int,none:int,errors:list<string>,remaining:int} */
    public function run(PositionStatement $s, int $limit = 6, int $maxSeconds = 90): array
    {
        $deadline = time() + $maxSeconds;
        $done = array_map(static fn (PositionStance $x): int => (int) $x->getCandidat()->getId(), $this->em->getRepository(PositionStance::class)->findBy(['statement' => $s]));
        $todo = array_values(array_filter($this->em->getRepository(Candidat::class)->findBy(['isPublished' => true], ['name' => 'ASC']), static fn (Candidat $c): bool => !in_array((int) $c->getId(), $done, true)));
        $res = ['added' => 0, 'none' => 0, 'errors' => [], 'remaining' => count($todo)];
        foreach ($todo as $c) {
            if ($limit-- <= 0 || time() >= $deadline) {
                break;
            }
            $r = $this->researcher->research((string) $c->getName(), $s->getText());
            --$res['remaining'];
            if (null === $r) {
                ++$res['none'];
                continue;
            }
            if (isset($r['error'])) {
                $res['errors'][] = $c->getName() . ' : ' . $r['error'];
                continue;
            }
            $this->em->persist(new PositionStance($s, $c, $r['stance'], $r['summary'], $r['source_url'], true));
            $this->em->flush();
            ++$res['added'];
        }

        return $res;
    }
}

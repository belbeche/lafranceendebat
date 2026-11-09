<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un total par jour et par type d'événement. Aucune personne, aucune adresse, aucun identifiant :
 * on ne peut pas retrouver qui a fait quoi, seulement combien de fois par jour.
 */
#[ORM\Entity]
#[ORM\Table(name: 'conversion_count')]
#[ORM\UniqueConstraint(name: 'uniq_conversion_day_name', columns: ['day', 'name'])]
class ConversionCount
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 10)]
    private string $day;
    #[ORM\Column(length: 40)]
    private string $name;
    #[ORM\Column]
    private int $total = 0;

    public function __construct(string $day, string $name)
    {
        $this->day = $day;
        $this->name = $name;
    }
}

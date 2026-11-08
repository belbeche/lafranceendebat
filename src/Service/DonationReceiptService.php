<?php

namespace App\Service;

use App\Entity\DonationReceipt;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Twig\Environment;

/**
 * Émet des reçus de don pour la réduction d'impôt (articles 200 et 238 bis du CGI).
 * Désactivé par défaut : seuls les organismes d'intérêt général ou reconnus d'utilité publique peuvent en délivrer.
 * Un reçu délivré à tort expose l'organisme à une amende de 25 % des sommes indiquées (article 1740 A du CGI).
 */
class DonationReceiptService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Environment $twig,
        private readonly bool $enabled,
        private readonly string $orgName,
        private readonly string $orgAddress,
        private readonly string $orgRna,
        private readonly string $orgObject,
        private readonly string $eligibility,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->missing() === [];
    }

    /** @return list<string> informations d'organisme manquantes */
    public function missing(): array
    {
        $m = [];
        foreach (['ASSO_NAME' => $this->orgName, 'ASSO_ADDRESS' => $this->orgAddress, 'ASSO_RNA' => $this->orgRna, 'ASSO_OBJECT' => $this->orgObject, 'ASSO_ELIGIBILITY' => $this->eligibility] as $k => $v) {
            if (trim($v) === '') {
                $m[] = $k;
            }
        }

        return $m;
    }

    public function issue(string $donor, string $address, int $amountCents, \DateTimeImmutable $receivedAt, string $mode, ?User $by): DonationReceipt
    {
        if (!$this->isEnabled()) {
            throw new \DomainException('Les reçus fiscaux sont désactivés : l\'organisme émetteur doit être éligible et ses informations complètes.');
        }
        if ($amountCents < 100 || $amountCents > 100000000) {
            throw new \DomainException('Montant invalide.');
        }
        if ($receivedAt > new \DateTimeImmutable()) {
            throw new \DomainException('La date de versement ne peut pas être dans le futur.');
        }
        if (mb_strlen(trim($donor)) < 3 || mb_strlen(trim($address)) < 8) {
            throw new \DomainException('Nom et adresse complète du donateur obligatoires.');
        }
        $year = $receivedAt->format('Y');
        $n = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM donation_receipt WHERE number LIKE ?', ['RF-' . $year . '-%']) + 1;
        $r = new DonationReceipt(sprintf('RF-%s-%04d', $year, $n), $donor, $address, $amountCents, $receivedAt, $mode, $by);
        $this->em->persist($r);
        $this->em->flush();

        return $r;
    }

    public function pdf(DonationReceipt $r): string
    {
        $html = $this->twig->render('pdf/donation_receipt.html.twig', [
            'r' => $r, 'org' => ['name' => $this->orgName, 'address' => $this->orgAddress, 'rna' => $this->orgRna, 'object' => $this->orgObject, 'eligibility' => $this->eligibility],
            'words' => self::amountInWords($r->getAmountCents()),
        ]);
        $d = new Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $d->loadHtml($html, 'UTF-8');
        $d->setPaper('A4');
        $d->render();

        return $d->output();
    }

    public static function amountInWords(int $cents): string
    {
        $e = intdiv($cents, 100);
        $c = $cents % 100;
        $s = self::words($e) . ' euro' . ($e > 1 ? 's' : '');
        if ($c > 0) {
            $s .= ' et ' . self::words($c) . ' centime' . ($c > 1 ? 's' : '');
        }

        return $s;
    }

    private static function words(int $n): string
    {
        if ($n === 0) {
            return 'zéro';
        }
        $u = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $t = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];
        $below100 = function (int $n) use ($u, $t): string {
            if ($n < 20) {
                return $u[$n];
            }
            if ($n < 70) {
                $d = intdiv($n, 10);
                $r = $n % 10;
                return $t[$d] . ($r === 1 ? ' et un' : ($r ? '-' . $u[$r] : ''));
            }
            if ($n < 80) {
                $r = $n - 60;
                return 'soixante' . ($r === 11 ? ' et onze' : '-' . $u[$r]);
            }
            $r = $n - 80;
            return 'quatre-vingt' . ($r === 0 ? 's' : '-' . $u[$r]);
        };
        $below1000 = function (int $n) use ($below100, $u): string {
            $h = intdiv($n, 100);
            $r = $n % 100;
            $s = '';
            if ($h > 0) {
                $s = ($h === 1 ? 'cent' : $u[$h] . ' cent' . ($r === 0 ? 's' : ''));
            }
            if ($r > 0) {
                $s .= ($s ? ' ' : '') . $below100($r);
            }
            return $s;
        };
        $out = '';
        if ($n >= 1000000) {
            $m = intdiv($n, 1000000);
            $out .= $below1000($m) . ' million' . ($m > 1 ? 's' : '');
            $n %= 1000000;
            if ($n) {
                $out .= ' ';
            }
        }
        if ($n >= 1000) {
            $k = intdiv($n, 1000);
            $out .= ($k === 1 ? 'mille' : $below1000($k) . ' mille');
            $n %= 1000;
            if ($n) {
                $out .= ' ';
            }
        }
        if ($n) {
            $out .= $below1000($n);
        }

        return $out;
    }
}

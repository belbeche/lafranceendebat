<?php

namespace App\Controller;

use App\Entity\Thesis;
use App\Service\Positions\ThesisWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ThesisController extends AbstractController
{
    #[Route('/analyses/{slug}', name: 'app_thesis', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(string $slug, EntityManagerInterface $em): Response
    {
        $t = $em->getRepository(Thesis::class)->findOneBy(['slug' => $slug, 'approved' => true]);
        if (!$t) {
            throw $this->createNotFoundException();
        }

        return $this->render('theses/show.html.twig', ['t' => $t, 'd' => $t->getData(), 'labels' => ThesisWriter::SECTIONS]);
    }
}

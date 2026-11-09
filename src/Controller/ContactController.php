<?php

// src/Controller/ContactController.php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Contrôleur HTTP "ContactController".
 *
 * Routes :
 *  - GET /contact (app_contact)
 */
class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, EntityManagerInterface $entityManager, \App\Service\TransactionalMailer $mails, \Symfony\Component\RateLimiter\RateLimiterFactory $contactIpLimiter): Response
    {
        if ($request->isMethod('POST') && !$contactIpLimiter->create($request->getClientIp() ?? 'x')->consume()->isAccepted()) {
            return new Response('Trop de messages. Réessayez plus tard.', Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => '3600']);
        }
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sauvegarde en base de données
            $entityManager->persist($contact);
            $entityManager->flush();

            // Envoi d'email
            $mails->contactReceived($contact);

            // Message de succès
            $this->addFlash('success', 'Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.');

            // Redirection pour éviter le re-soumission du formulaire
            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}

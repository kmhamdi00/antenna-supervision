<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class DashboardController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $apiKey,
    ) {}

    /**
     * Page unique de supervision. Le rendu de la liste est fait côté client
     * (fetch natif vers /api/antennas).
     *
     * la clé API est injectée dans la page pour permettre au bouton "Clôturer" 
     * d'appeler une route protégée depuis le navigateur. 
     * C'est acceptable pour un outil interne
     * sur réseau de confiance dans le cadre de cet exercice, mais pas pour
     * une exposition publique.
     */
    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return new Response($this->twig->render('dashboard/index.html.twig', [
            'api_key' => $this->apiKey,
        ]));
    }
}

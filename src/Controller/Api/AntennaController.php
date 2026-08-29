<?php

namespace App\Controller\Api;

use App\Enum\AntennaStatus;
use App\Repository\AntennaRepository;
use App\Repository\InterventionRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class AntennaController
{
    private const MAX_LIMIT = 100;
    private const DEFAULT_LIMIT = 20;

    public function __construct(
        private readonly AntennaRepository $antennas,
        private readonly InterventionRepository $interventions,
    ) {}

    /**
     * GET /api/antennas?city=Paris&status=DOWN&page=1&limit=20
     *
     * Route publique en lecture (pas d'authentification requise), utilisée
     * aussi bien par le dashboard que par d'éventuels outils de supervision
     * tiers. Toujours paginée : sur 100 000+ antennes, une liste non bornée
     * n'est pas envisageable en production.
     */
    #[Route('/api/antennas', name: 'api_antennas_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $city = $request->query->get('city');

        $statusParam = $request->query->get('status');
        $status = null;
        if (null !== $statusParam) {
            $status = AntennaStatus::tryFrom($statusParam);
            if (null === $status) {
                throw new BadRequestHttpException(sprintf(
                    'Invalid status "%s". Allowed values: UP, DOWN.',
                    $statusParam
                ));
            }
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(self::MAX_LIMIT, max(1, $request->query->getInt('limit', self::DEFAULT_LIMIT)));

        $result = $this->antennas->search($city, $status, $page, $limit);
        $antennaIds = array_map(static fn($antenna) => $antenna->getId(), $result['items']);
        $lastInterventions = $this->interventions->findLastForAntennaIds($antennaIds);

        $data = array_map(
            function ($antenna) use ($lastInterventions) {
                $last = $lastInterventions[$antenna->getId()] ?? null;

                return [
                    'id' => $antenna->getId(),
                    'name' => $antenna->getName(),
                    'city' => $antenna->getCity(),
                    'status' => $antenna->getStatus()->value,
                    'created_at' => $antenna->getCreatedAt()->format(\DATE_ATOM),
                    'last_intervention' => null === $last ? null : [
                        'id' => (int) $last['id'],
                        'description' => $last['description'],
                        'technician_identity' => $last['technician_identity'],
                        'priority' => $last['priority'],
                        'created_at' => (new \DateTimeImmutable($last['created_at']))->format(\DATE_ATOM),
                        'ended_at' => null === $last['ended_at']
                            ? null
                            : (new \DateTimeImmutable($last['ended_at']))->format(\DATE_ATOM),
                    ],
                ];
            },
            $result['items']
        );

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
            ],
        ], Response::HTTP_OK);
    }
}

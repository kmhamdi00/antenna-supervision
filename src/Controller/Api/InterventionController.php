<?php

namespace App\Controller\Api;

use App\Dto\CreateInterventionRequest;
use App\Entity\Antenna;
use App\Entity\Intervention;
use App\Enum\AntennaStatus;
use App\Enum\InterventionPriority;
use App\Exception\ActiveInterventionExistsException;
use App\Exception\InterventionAlreadyClosedException;
use App\Repository\AntennaRepository;
use App\Repository\InterventionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class InterventionController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AntennaRepository $antennas,
        private readonly InterventionRepository $interventions,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * POST /api/interventions
     *
     * Règle métier : une seule intervention active par antenne.
     *
     */
    #[Route('/api/interventions', name: 'api_interventions_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->decodeJsonBody($request);
        $dto = CreateInterventionRequest::fromArray($data);

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            throw new ValidationFailedException($dto, $violations);
        }

        $antenna = $this->antennas->find($dto->antennaId);
        if (null === $antenna) {
            throw new NotFoundHttpException(sprintf('Antenna #%d not found.', $dto->antennaId));
        }

        if (null !== $this->interventions->findActiveForAntenna($antenna)) {
            throw new ActiveInterventionExistsException($antenna->getId());
        }

        $intervention = new Intervention(
            $antenna,
            $dto->description,
            $dto->technicianIdentity,
            InterventionPriority::from($dto->priority),
        );

        $this->em->wrapInTransaction(function () use ($intervention, $antenna) {
            $this->em->persist($intervention);
            $antenna->setStatus(AntennaStatus::DOWN);
            $this->em->flush();
        });

        return new JsonResponse($this->serializeIntervention($intervention), Response::HTTP_CREATED);
    }

    /**
     * PATCH /api/interventions/{id}/close
     *
     */
    #[Route('/api/interventions/{id}/close', name: 'api_interventions_close', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function close(int $id): JsonResponse
    {
        $intervention = $this->interventions->find($id);
        if (null === $intervention) {
            throw new NotFoundHttpException(sprintf('Intervention #%d not found.', $id));
        }

        if (!$intervention->isActive()) {
            throw new InterventionAlreadyClosedException($id);
        }

        $antenna = $intervention->getAntenna();

        $this->em->wrapInTransaction(function () use ($intervention, $antenna) {
            $intervention->close();
            $antenna->setStatus(AntennaStatus::UP);
            $this->em->flush();
        });

        return new JsonResponse($this->serializeIntervention($intervention), Response::HTTP_OK);
    }

    private function decodeJsonBody(Request $request): array
    {
        $content = $request->getContent();
        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new BadRequestHttpException('Request body must be valid JSON.');
        }

        return $data;
    }

    private function serializeIntervention(Intervention $intervention): array
    {
        return [
            'id' => $intervention->getId(),
            'antenna_id' => $intervention->getAntenna()->getId(),
            'description' => $intervention->getDescription(),
            'technician_identity' => $intervention->getTechnicianIdentity(),
            'priority' => $intervention->getPriority()->value,
            'created_at' => $intervention->getCreatedAt()->format(\DATE_ATOM),
            'ended_at' => $intervention->getEndedAt()?->format(\DATE_ATOM),
            'antenna_status' => $intervention->getAntenna()->getStatus()->value,
        ];
    }
}

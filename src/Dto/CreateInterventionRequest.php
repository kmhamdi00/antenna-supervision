<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO de validation du corps de la requête POST /api/interventions.
 * On valide explicitement ce DTO plutôt que l'entité Doctrine directement.
 */
class CreateInterventionRequest
{
    #[Assert\NotNull(message: 'antenna_id is required.')]
    #[Assert\Positive(message: 'antenna_id must be a positive integer.')]
    public ?int $antennaId = null;

    #[Assert\NotBlank(message: 'description is required.')]
    #[Assert\Length(max: 5000)]
    public ?string $description = null;

    #[Assert\NotBlank(message: 'technician_identity is required.')]
    #[Assert\Length(max: 255)]
    public ?string $technicianIdentity = null;

    #[Assert\NotBlank(message: 'priority is required.')]
    #[Assert\Choice(choices: ['LOW', 'MEDIUM', 'HIGH'], message: 'priority must be one of LOW, MEDIUM, HIGH.')]
    public ?string $priority = null;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->antennaId = isset($data['antenna_id']) ? (int) $data['antenna_id'] : null;
        $dto->description = $data['description'] ?? null;
        $dto->technicianIdentity = $data['technician_identity'] ?? null;
        $dto->priority = $data['priority'] ?? null;

        return $dto;
    }
}

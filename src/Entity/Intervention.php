<?php

namespace App\Entity;

use App\Enum\InterventionPriority;
use App\Repository\InterventionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InterventionRepository::class)]
#[ORM\Table(name: 'intervention')]
#[ORM\Index(columns: ['antenna_id', 'created_at'], name: 'idx_intervention_antenna_created')]
class Intervention
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Antenna::class, inversedBy: 'interventions')]
    #[ORM\JoinColumn(name: 'antenna_id', referencedColumnName: 'id', nullable: false)]
    private Antenna $antenna;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(name: 'technician_identity', length: 255)]
    private string $technicianIdentity;

    #[ORM\Column(length: 10, enumType: InterventionPriority::class)]
    private InterventionPriority $priority;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'ended_at', nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    /**
     * Verrou optimiste : protège la clôture (`close`) contre une double
     * requête concurrente sur la même intervention (double clic, retry client...).
     */
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version;

    public function __construct(
        Antenna $antenna,
        string $description,
        string $technicianIdentity,
        InterventionPriority $priority,
    ) {
        $this->antenna = $antenna;
        $this->description = $description;
        $this->technicianIdentity = $technicianIdentity;
        $this->priority = $priority;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAntenna(): Antenna
    {
        return $this->antenna;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getTechnicianIdentity(): string
    {
        return $this->technicianIdentity;
    }

    public function getPriority(): InterventionPriority
    {
        return $this->priority;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function isActive(): bool
    {
        return null === $this->endedAt;
    }

    public function close(): void
    {
        if (!$this->isActive()) {
            throw new \LogicException('This intervention is already closed.');
        }

        $this->endedAt = new \DateTimeImmutable();
    }
}

<?php

namespace App\Entity;

use App\Enum\AntennaStatus;
use App\Repository\AntennaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AntennaRepository::class)]
#[ORM\Table(name: 'antenna')]
#[ORM\Index(columns: ['city'], name: 'idx_antenna_city')]
#[ORM\Index(columns: ['status'], name: 'idx_antenna_status')]
class Antenna
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $city;

    #[ORM\Column(length: 10, enumType: AntennaStatus::class)]
    private AntennaStatus $status;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Intervention> */
    #[ORM\OneToMany(targetEntity: Intervention::class, mappedBy: 'antenna', orphanRemoval: false)]
    private Collection $interventions;

    public function __construct(string $name, string $city, AntennaStatus $status = AntennaStatus::UP)
    {
        $this->name = $name;
        $this->city = $city;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
        $this->interventions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getStatus(): AntennaStatus
    {
        return $this->status;
    }

    public function setStatus(AntennaStatus $status): void
    {
        $this->status = $status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Intervention> */
    public function getInterventions(): Collection
    {
        return $this->interventions;
    }
}

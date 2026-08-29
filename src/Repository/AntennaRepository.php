<?php

namespace App\Repository;

use App\Entity\Antenna;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Enum\AntennaStatus;

/**
 * @extends ServiceEntityRepository<Antenna>
 */
class AntennaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Antenna::class);
    }

    /**
     * Recherche paginée des antennes, filtrée par ville et statut.
     *
     * @return array{items: Antenna[], total: int}
     */
    public function search(?string $city, ?AntennaStatus $status, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('a');

        if (null !== $city) {
            $qb->andWhere('a.city = :city')->setParameter('city', $city);
        }

        if (null !== $status) {
            $qb->andWhere('a.status = :status')->setParameter('status', $status);
        }

        $countQb = (clone $qb)->select('COUNT(a.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $items = $qb
            ->orderBy('a.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}

<?php

namespace App\Repository;

use App\Entity\Antenna;
use App\Entity\Intervention;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Intervention>
 */
class InterventionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Intervention::class);
    }

    public function findActiveForAntenna(Antenna $antenna): ?Intervention
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.antenna = :antenna')
            ->andWhere('i.endedAt IS NULL')
            ->setParameter('antenna', $antenna)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne, pour un lot d'IDs d'antennes, la dernière intervention de
     * chacune (active ou non), en une seule requête.
     *
     * @param int[] $antennaIds
     *
     * @return array<int, array{id:int, description:string, technician_identity:string, priority:string, created_at:string, ended_at:?string}>
     *              indexé par antenna_id
     */
    public function findLastForAntennaIds(array $antennaIds): array
    {
        if ([] === $antennaIds) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<SQL
            SELECT DISTINCT ON (antenna_id)
                   id, antenna_id, description, technician_identity, priority, created_at, ended_at
            FROM intervention
            WHERE antenna_id IN (:ids)
            ORDER BY antenna_id, created_at DESC, id DESC
        SQL;

        $rows = $conn->executeQuery(
            $sql,
            ['ids' => $antennaIds],
            ['ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
        )->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['antenna_id']] = $row;
        }

        return $result;
    }
}

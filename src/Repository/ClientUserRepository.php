<?php

namespace App\Repository;

use App\Entity\ClientUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientUser>
 *
 * @method ClientUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClientUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClientUser[]    findAll()
 * @method ClientUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientUser::class);
    }

    public function save(ClientUser $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ClientUser $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findAllWithPagination(int $clientId, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('cu')
            ->orderBy('cu.id', 'DESC')
            ->where('cu.client = :clientId')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->setParameter('clientId', $clientId)
            ->getQuery()
            ->getResult()
        ;
    }

//    /**
//     * @return ClientUser[] Returns an array of ClientUser objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ClientUser
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

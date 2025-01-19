<?php

namespace App\Repository;

use App\Entity\UserEntity;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class UserRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, $em->getClassMetadata(UserEntity::class));
    }

    public function save(UserEntity $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function delete(UserEntity $user): void
    {
        $this->getEntityManager()->remove($user);
        $this->getEntityManager()->flush();
    }

    public function findByUuid(string $uuid): ?UserEntity
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function findByEmail(string $email): ?UserEntity
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findByUsername(string $username): ?UserEntity
    {
        return $this->findOneBy(['username' => $username]);
    }

    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.isActive = :active')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->where('JSONB_CONTAINS(u.roles, :role) = true')
            ->setParameter('role', $role)
            ->getQuery()
            ->getResult();
    }

    public function searchUsers(array $criteria, array $orderBy = null, int $limit = null, int $offset = null): array
    {
        $qb = $this->createQueryBuilder('u');
        $this->applyCriteria($qb, $criteria);

        if ($orderBy) {
            foreach ($orderBy as $field => $direction) {
                $qb->addOrderBy("u.$field", $direction);
            }
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        if ($offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    public function findRecentlyActive(int $days = 30): array
    {
        $date = new \DateTimeImmutable("-$days days");

        return $this->createQueryBuilder('u')
            ->where('u.lastLoginAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('u.lastLoginAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findInactiveUsers(int $days = 90): array
    {
        $date = new \DateTimeImmutable("-$days days");

        return $this->createQueryBuilder('u')
            ->where('u.lastLoginAt <= :date OR u.lastLoginAt IS NULL')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    public function countActiveUsers(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isActive = :active')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function applyCriteria(QueryBuilder $qb, array $criteria): void
    {
        $i = 0;
        foreach ($criteria as $field => $value) {
            $parameter = 'p' . $i++;
            $fieldPath = "u.$field";

            if (is_array($value)) {
                $qb->andWhere($qb->expr()->in($fieldPath, ":$parameter"))
                    ->setParameter($parameter, $value);
            } elseif ($value === null) {
                $qb->andWhere($qb->expr()->isNull($fieldPath));
            } elseif (is_string($value) && str_contains($value, '%')) {
                $qb->andWhere($qb->expr()->like($fieldPath, ":$parameter"))
                    ->setParameter($parameter, $value);
            } else {
                $qb->andWhere($qb->expr()->eq($fieldPath, ":$parameter"))
                    ->setParameter($parameter, $value);
            }
        }
    }

    public function softDelete(UserEntity $user): void
    {
        $user->delete();
        $this->save($user);
    }

    public function restore(UserEntity $user): void
    {
        $user->logOperation('restored');
        $this->save($user);
    }

    public function findDeletedUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.deletedAt IS NOT NULL')
            ->orderBy('u.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function purgeDeletedUsers(int $days = 30): int
    {
        $date = new \DateTimeImmutable("-$days days");

        return $this->createQueryBuilder('u')
            ->delete()
            ->where('u.deletedAt <= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}

<?php
/**
 * Repository for the User entity
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Account\Repository;

use App\Account\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Loading for authentication: the email is compared in lowercase,
     * whatever the database engine and its collation.
     */
    public function loadUserByIdentifier(string $identifier): ?User
    {
        return $this->findOneByEmail($identifier);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    /**
     * Updates the last login date with a direct query: a login
     * is not a modification of the account and must not appear in the audit.
     */
    public function touchLastLogin(User $user, \DateTimeImmutable $at): void
    {
        $this->createQueryBuilder('u')
            ->update()
            ->set('u.lastLoginAt', ':at')
            ->where('u.id = :id')
            ->setParameter('at', $at, 'datetime_immutable')
            ->setParameter('id', $user->getId())
            ->getQuery()
            ->execute();
    }
}

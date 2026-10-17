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
    /**
     * UserRepository constructor.
     *
     * @param ManagerRegistry $registry Doctrine registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Loading for authentication: the email is compared in lowercase, whatever the database engine.
     *
     * @param string $identifier Email address typed at the login
     * @return User|null The account, null if none matches
     */
    public function loadUserByIdentifier(string $identifier): ?User
    {
        return $this->findOneByEmail($identifier);
    }

    /**
     * Finds an account by email address, ignoring case and surrounding spaces.
     *
     * @param string $email Email address
     * @return User|null The account, null if none matches
     */
    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    /**
     * Updates the last login date with a direct query: a login is not a modification of the account
     * and must not appear in the audit.
     *
     * @param User $user Account that just signed in
     * @param \DateTimeImmutable $at Moment of the login
     * @return void
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

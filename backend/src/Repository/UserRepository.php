<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * Repository User — fournit les méthodes de requêtes Doctrine pour l'entité User.
 *
 * Implémente PasswordUpgraderInterface pour permettre à Symfony de re-hasher
 * automatiquement les mots de passe si l'algorithme de hachage change (ex: argon2i → bcrypt).
 *
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        // Le 2e argument lie ce repository à l'entité User
        parent::__construct($registry, User::class);
    }

    /**
     * Mise à jour automatique du mot de passe hashé.
     * Appelé par Symfony Security lors d'une connexion si le hash est jugé obsolète.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Les instances de "%s" ne sont pas supportées.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouve un utilisateur par son email.
     * Utilisé notamment dans le processus d'authentification.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }
}

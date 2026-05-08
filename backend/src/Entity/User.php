<?php

namespace App\Entity;

// Importation du repository associé à cette entité
use App\Repository\UserRepository;
// ORM Mapping : ensemble des annotations Doctrine pour mapper la classe vers une table SQL
use Doctrine\ORM\Mapping as ORM;
// Interfaces Symfony Security pour la gestion des utilisateurs authentifiables
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Entité User — représente un utilisateur administrateur de Stock&Shop.
 *
 * Implémente :
 *   - UserInterface                       → exigé par le système de sécurité Symfony
 *   - PasswordAuthenticatedUserInterface  → indique que cet utilisateur s'authentifie par mot de passe hashé
 *
 * La table est nommée `user` (backticks pour éviter le conflit avec le mot-clé réservé MySQL).
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Clé primaire auto-incrémentée
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Email unique : utilisé comme identifiant de connexion (getUserIdentifier)
    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    // Mot de passe hashé (jamais stocké en clair) — hashé via UserPasswordHasherInterface
    #[ORM\Column]
    private ?string $password = null;

    // Nom affiché de l'utilisateur dans l'interface
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    // Rôle unique de l'utilisateur (ex: ROLE_ADMIN, ROLE_USER)
    // Stocké en chaîne plutôt qu'en array pour simplifier le modèle de données
    #[ORM\Column(length: 50)]
    private string $role = 'ROLE_ADMIN';

    // Date de création de l'utilisateur, initialisée automatiquement dans le constructeur
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        // Initialisation automatique de la date lors de la création de l'objet
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Identifiant unique de l'utilisateur pour Symfony Security.
     * On utilise l'email comme identifiant (au lieu du username).
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Retourne la liste des rôles de l'utilisateur.
     * Symfony exige toujours au minimum ROLE_USER dans ce tableau.
     * On ajoute le rôle spécifique de l'utilisateur en plus.
     */
    public function getRoles(): array
    {
        // ROLE_USER est obligatoire pour que le firewall Symfony fonctionne correctement
        $roles = ['ROLE_USER'];

        if ($this->role) {
            $roles[] = $this->role;
        }

        return array_unique($roles);
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Efface les données sensibles temporaires de l'utilisateur (ex: mot de passe en clair).
     * Appelé automatiquement par Symfony après l'authentification.
     */
    public function eraseCredentials(): void
    {
        // Pas de données sensibles temporaires à effacer dans cette implémentation
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}

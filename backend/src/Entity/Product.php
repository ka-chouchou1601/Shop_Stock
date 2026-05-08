<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité Product — représente un produit du catalogue Stock&Shop.
 *
 * Relation : un Product appartient à une Category (ManyToOne).
 * C'est le côté propriétaire de la relation : la colonne FK `category_id` est dans cette table.
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    // Clé primaire auto-incrémentée
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Nom du produit, limité à 255 caractères
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    // Description courte pour les listes et aperçus (max 255 caractères)
    #[ORM\Column(length: 255)]
    private ?string $shortDescription = null;

    // Description complète : type TEXT pour contenu long (pas de limite de 255 chars)
    #[ORM\Column(type: 'text')]
    private ?string $description = null;

    // Prix avec 2 décimales et jusqu'à 8 chiffres avant la virgule (ex: 99999999.99)
    // Stocké en DECIMAL (précis) plutôt que FLOAT (arrondi) pour éviter les erreurs monétaires
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $price = null;

    // Quantité en stock (entier) — utilisé par getAvailability()
    #[ORM\Column]
    private ?int $quantity = null;

    // URL de l'image du produit — nullable : le produit peut ne pas avoir d'image
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    // Date de création, initialisée dans le constructeur
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    // Date de dernière modification — mise à jour manuellement ou via un event listener Doctrine
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * Relation ManyToOne vers Category.
     * inversedBy: 'products' → fait référence à la propriété $products dans Category.
     * nullable: true → un produit peut exister temporairement sans catégorie.
     */
    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Category $category = null;

    public function __construct()
    {
        // Initialisation automatique des dates à la création du produit
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * Retourne la disponibilité textuelle du produit selon son stock.
     *
     * Règle métier :
     *   - quantity > 5  → "En stock"      (stock suffisant)
     *   - 1 ≤ quantity ≤ 5 → "Stock faible" (attention, bientôt épuisé)
     *   - quantity = 0  → "Rupture"       (plus disponible)
     */
    public function getAvailability(): string
    {
        if ($this->quantity > 5) {
            return 'En stock';
        }

        if ($this->quantity >= 1) {
            return 'Stock faible';
        }

        return 'Rupture';
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }
}

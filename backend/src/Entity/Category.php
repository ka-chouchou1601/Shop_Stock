<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité Category — représente une catégorie de produits (ex: E-liquides, Accessoires…).
 *
 * Relation : une Category peut contenir plusieurs Products (OneToMany).
 * Côté inverse : la relation est owée par Product (champ ManyToOne dans Product).
 */
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category
{
    // Clé primaire auto-incrémentée
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Nom de la catégorie, unique en base (pas deux catégories avec le même nom)
    #[ORM\Column(length: 255, unique: true)]
    private ?string $name = null;

    // Date de création, initialisée automatiquement dans le constructeur
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Relation OneToMany vers Product.
     * mappedBy: 'category' → fait référence à la propriété $category dans l'entité Product
     * cascade: ['persist', 'remove'] → si une catégorie est supprimée, ses produits le sont aussi
     * orphanRemoval: true → supprime les produits qui ne sont plus rattachés à une catégorie
     */
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Product::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $products;

    public function __construct()
    {
        // Initialisation automatique de la date de création
        $this->createdAt = new \DateTime();
        // ArrayCollection est l'implémentation Doctrine des collections d'entités liées
        $this->products = new ArrayCollection();
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Retourne la collection de produits associés à cette catégorie.
     *
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            // Synchronise le côté propriétaire (Product) de la relation bidirectionnelle
            $product->setCategory($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            // Désassocie le produit de cette catégorie si c'est bien cette catégorie qui le possédait
            if ($product->getCategory() === $this) {
                $product->setCategory(null);
            }
        }

        return $this;
    }
}

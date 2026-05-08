<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository Category — fournit les méthodes de requêtes Doctrine pour l'entité Category.
 *
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Retourne toutes les catégories triées par nom alphabétique.
     * Utile pour les menus déroulants et les listes de filtres côté frontend.
     *
     * @return Category[]
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une catégorie par son nom exact.
     * Utilisé dans les fixtures pour éviter les doublons lors du rechargement.
     */
    public function findByName(string $name): ?Category
    {
        return $this->findOneBy(['name' => $name]);
    }
}

<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository Product — fournit les méthodes de requêtes Doctrine pour l'entité Product.
 *
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Retourne tous les produits avec leur catégorie chargée en une seule requête (JOIN FETCH).
     * Évite le problème N+1 : sans JOIN FETCH, Doctrine ferait une requête SQL par produit
     * pour charger la catégorie associée.
     *
     * @return Product[]
     */
    public function findAllWithCategory(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les produits d'une catégorie donnée, triés par nom.
     *
     * @return Product[]
     */
    public function findByCategory(int $categoryId): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->where('c.id = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les produits en rupture de stock (quantity = 0).
     * Utile pour les alertes d'inventaire dans le dashboard admin.
     *
     * @return Product[]
     */
    public function findOutOfStock(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.quantity = 0')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

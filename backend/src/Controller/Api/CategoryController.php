<?php

namespace App\Controller\Api;

use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * CategoryController — expose les catégories de produits via l'API REST.
 *
 * Préfixe de route : /api/categories
 */
#[Route('/api/categories')]
class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository
    ) {}

    /**
     * GET /api/categories — Retourne la liste de toutes les catégories.
     *
     * Utilisé par le frontend pour peupler les filtres et les formulaires de sélection.
     *
     * Réponse (200) :
     *   [{ "id": 1, "name": "E-liquides" }, ...]
     *
     * Triées par ordre alphabétique via findAllOrderedByName().
     */
    #[Route('', name: 'api_categories_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $categories = $this->categoryRepository->findAllOrderedByName();

        // Transformation des entités en tableaux simples pour la sérialisation JSON
        // On expose uniquement id + name (les produits associés ne sont pas chargés ici)
        $data = array_map(
            fn($category) => [
                'id'   => $category->getId(),
                'name' => $category->getName(),
            ],
            $categories
        );

        return $this->json($data);
    }
}

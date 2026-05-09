<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * ProductController — CRUD complet sur les produits via l'API REST.
 *
 * Routes exposées (préfixe /api/products) :
 *   GET    /api/products          → liste avec filtres optionnels
 *   GET    /api/products/{id}     → détail d'un produit
 *   POST   /api/products          → création d'un produit
 *   PUT    /api/products/{id}     → mise à jour d'un produit
 *   DELETE /api/products/{id}     → suppression d'un produit
 */
#[Route('/api/products')]
class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository    $productRepository,
        private readonly CategoryRepository   $categoryRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    // ─────────────────────────────────────────────────────────
    // GET /api/products
    // ─────────────────────────────────────────────────────────

    /**
     * Retourne la liste des produits avec filtres optionnels.
     *
     * Paramètres de requête (?...) :
     *   - category     : id de la catégorie (filtrage exact)
     *   - availability : "En stock" | "Stock faible" | "Rupture"
     *   - search       : texte libre — LIKE sur le nom du produit
     *
     * Exemple : GET /api/products?category=2&availability=En+stock&search=kit
     */
    #[Route('', name: 'api_products_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Construction d'une requête DQL dynamique via le QueryBuilder
        $qb = $this->productRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')             // Évite les requêtes N+1 pour la catégorie
            ->orderBy('p.createdAt', 'DESC');

        // Filtre par catégorie (id entier)
        if ($categoryId = $request->query->get('category')) {
            $qb->andWhere('c.id = :categoryId')
               ->setParameter('categoryId', (int) $categoryId);
        }

        // Filtre par disponibilité — traduit en condition sur la colonne quantity
        if ($availability = $request->query->get('availability')) {
            match ($availability) {
                'En stock'     => $qb->andWhere('p.quantity > 5'),
                'Stock faible' => $qb->andWhere('p.quantity >= 1')->andWhere('p.quantity <= 5'),
                'Rupture'      => $qb->andWhere('p.quantity = 0'),
                default        => null,  // Valeur inconnue : filtre ignoré
            };
        }

        // Filtre par texte — recherche partielle LIKE sur le nom du produit
        if ($search = $request->query->get('search')) {
            $qb->andWhere('p.name LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $products = $qb->getQuery()->getResult();

        return $this->json(array_map([$this, 'serializeProduct'], $products));
    }

    // ─────────────────────────────────────────────────────────
    // GET /api/products/{id}
    // ─────────────────────────────────────────────────────────

    /**
     * Retourne le détail complet d'un produit.
     *
     * Réponse (200) : objet produit complet
     * Réponse (404) : { "error": "Produit introuvable" }
     */
    #[Route('/{id}', name: 'api_products_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            return $this->json(['error' => 'Produit introuvable'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeProduct($product));
    }

    // ─────────────────────────────────────────────────────────
    // POST /api/products
    // ─────────────────────────────────────────────────────────

    /**
     * Crée un nouveau produit.
     *
     * Body JSON attendu :
     *   { "name", "shortDescription", "description", "price", "quantity", "categoryId", "imageUrl?" }
     *
     * Réponse succès (201) : produit créé sérialisé
     * Réponse erreur  (422) : { "errors": ["...", ...] }
     */
    #[Route('', name: 'api_products_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        // Validation des données entrantes
        [$errors, $category] = $this->validateProductData($data);

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Création et hydratation de l'entité Product
        $product = new Product();
        $product->setName(trim($data['name']));
        $product->setShortDescription(trim($data['shortDescription'] ?? ''));
        $product->setDescription(trim($data['description'] ?? ''));
        $product->setPrice((string) $data['price']);
        $product->setQuantity((int) $data['quantity']);
        $product->setImageUrl($data['imageUrl'] ?? null);
        $product->setCategory($category);

        // Persistance en base (INSERT)
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $this->json($this->serializeProduct($product), JsonResponse::HTTP_CREATED);
    }

    // ─────────────────────────────────────────────────────────
    // PUT /api/products/{id}
    // ─────────────────────────────────────────────────────────

    /**
     * Met à jour un produit existant.
     *
     * Réponse succès (200) : produit mis à jour sérialisé
     * Réponse (404) si produit non trouvé
     * Réponse (422) si données invalides
     */
    #[Route('/{id}', name: 'api_products_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            return $this->json(['error' => 'Produit introuvable'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // Même règles de validation que pour la création
        [$errors, $category] = $this->validateProductData($data);

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Mise à jour des champs de l'entité existante
        $product->setName(trim($data['name']));
        $product->setShortDescription(trim($data['shortDescription'] ?? ''));
        $product->setDescription(trim($data['description'] ?? ''));
        $product->setPrice((string) $data['price']);
        $product->setQuantity((int) $data['quantity']);
        $product->setImageUrl($data['imageUrl'] ?? null);
        $product->setCategory($category);
        // Mise à jour manuelle de updatedAt à chaque modification
        $product->setUpdatedAt(new \DateTime());

        // Pas besoin de persist() : Doctrine détecte automatiquement les changements
        // sur les entités déjà gérées (tracked) via l'EntityManager
        $this->entityManager->flush();

        return $this->json($this->serializeProduct($product));
    }

    // ─────────────────────────────────────────────────────────
    // DELETE /api/products/{id}
    // ─────────────────────────────────────────────────────────

    /**
     * Supprime un produit.
     *
     * Réponse succès (200) : { "message": "Produit supprimé" }
     * Réponse (404) si produit non trouvé
     */
    #[Route('/{id}', name: 'api_products_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            return $this->json(['error' => 'Produit introuvable'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();

        return $this->json(['message' => 'Produit supprimé']);
    }

    // ─────────────────────────────────────────────────────────
    // MÉTHODES PRIVÉES
    // ─────────────────────────────────────────────────────────

    /**
     * Sérialise un produit en tableau PHP prêt pour la réponse JSON.
     * Centralisé ici pour éviter la duplication entre list(), show(), create() et update().
     *
     * @return array<string, mixed>
     */
    private function serializeProduct(Product $product): array
    {
        return [
            'id'               => $product->getId(),
            'name'             => $product->getName(),
            'shortDescription' => $product->getShortDescription(),
            'description'      => $product->getDescription(),
            'price'            => $product->getPrice(),
            'quantity'         => $product->getQuantity(),
            'availability'     => $product->getAvailability(),
            'imageUrl'         => $product->getImageUrl(),
            // La catégorie est incluse en objet imbriqué (évite une requête séparée côté frontend)
            'category'         => $product->getCategory() ? [
                'id'   => $product->getCategory()->getId(),
                'name' => $product->getCategory()->getName(),
            ] : null,
            'createdAt'        => $product->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt'        => $product->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Valide les données d'un produit (création ou mise à jour).
     *
     * Règles :
     *   - name       : obligatoire, non vide
     *   - price      : présent, valeur numérique > 0
     *   - quantity   : présent, entier >= 0
     *   - categoryId : présent, doit correspondre à une catégorie existante en base
     *
     * @return array{0: string[], 1: \App\Entity\Category|null}
     *   [liste d'erreurs, objet Category ou null si non trouvé]
     */
    private function validateProductData(array $data): array
    {
        $errors   = [];
        $category = null;

        if (empty($data['name']) || !is_string($data['name']) || trim($data['name']) === '') {
            $errors[] = 'Le nom est requis';
        }

        if (!isset($data['price']) || !is_numeric($data['price']) || (float) $data['price'] <= 0) {
            $errors[] = 'Le prix doit être supérieur à 0';
        }

        if (!isset($data['quantity']) || !is_numeric($data['quantity']) || (int) $data['quantity'] < 0) {
            $errors[] = 'La quantité doit être un entier >= 0';
        }

        if (empty($data['categoryId'])) {
            $errors[] = 'La catégorie est requise';
        } else {
            // Vérification que la catégorie référencée existe réellement en base
            $category = $this->categoryRepository->find((int) $data['categoryId']);
            if (!$category) {
                $errors[] = 'Catégorie introuvable';
            }
        }

        return [$errors, $category];
    }
}

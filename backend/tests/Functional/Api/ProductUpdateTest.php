<?php

namespace App\Tests\Functional\Api;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TEST 3 — Mise à jour d'un produit via PUT /api/products/{id}.
 *
 * Cas testés :
 *   - PUT valide          → 200 OK + updatedAt mis à jour dans la réponse
 *   - PUT id inexistant   → 404 Not Found
 *
 * Commande : php bin/phpunit tests/Functional/Api/ProductUpdateTest.php --testdox
 */
class ProductUpdateTest extends WebTestCase
{
    private KernelBrowser $client;
    private int $productId;
    private int $categoryId;

    // ─────────────────────────────────────────────────────────
    // SETUP — exécuté avant chaque test
    // ─────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $em           = static::getContainer()->get(EntityManagerInterface::class);

        // Recrée le schéma pour un état vierge
        $schemaTool = new SchemaTool($em);
        $metadata   = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // Crée une catégorie et un produit de base pour les tests de mise à jour
        $category = new Category();
        $category->setName('Test Catégorie');
        $em->persist($category);

        // Le produit est créé avec une date updatedAt dans le passé (il y a 1 minute)
        // pour qu'on puisse vérifier que la mise à jour l'a bien rafraîchi.
        $product = new Product();
        $product->setName('Produit original');
        $product->setShortDescription('Description courte originale');
        $product->setDescription('Description longue originale');
        $product->setPrice('15.00');
        $product->setQuantity(5);
        $product->setCategory($category);
        // On force updatedAt dans le passé pour vérifier sa mise à jour après PUT
        $product->setUpdatedAt(new \DateTime('-1 minute'));
        $em->persist($product);

        $em->flush();

        $this->categoryId = $category->getId();
        $this->productId  = $product->getId();
    }

    // ─────────────────────────────────────────────────────────
    // HELPER — envoie une requête PUT /api/products/{id}
    // ─────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $body
     */
    private function putProduct(int $id, array $body): void
    {
        $this->client->request(
            'PUT',
            "/api/products/{$id}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($body)
        );
    }

    // ─────────────────────────────────────────────────────────
    // TEST 1 : mise à jour valide → 200 + updatedAt récent
    // ─────────────────────────────────────────────────────────

    /**
     * Un PUT avec des données valides doit :
     *   - retourner 200
     *   - retourner le produit mis à jour
     *   - avoir un updatedAt récent (dans les 5 dernières secondes)
     */
    public function testMiseAJourValideRetourne200AvecUpdatedAt(): void
    {
        $this->putProduct($this->productId, [
            'name'             => 'Produit modifié',
            'shortDescription' => 'Nouvelle description courte',
            'description'      => 'Nouvelle description longue',
            'price'            => 25.00,
            'quantity'         => 8,
            'categoryId'       => $this->categoryId,
        ]);

        $this->assertResponseStatusCodeSame(200);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        // Le nom doit avoir changé
        $this->assertSame('Produit modifié', $response['name']);

        // updatedAt doit être présent et récent (dans les 5 dernières secondes)
        $this->assertArrayHasKey('updatedAt', $response);

        $updatedAt = new \DateTime($response['updatedAt']);
        $now       = new \DateTime();
        $diffSeconds = abs($now->getTimestamp() - $updatedAt->getTimestamp());

        $this->assertLessThanOrEqual(
            5,
            $diffSeconds,
            "updatedAt devrait être récent (dans les 5 dernières secondes), diff = {$diffSeconds}s"
        );
    }

    // ─────────────────────────────────────────────────────────
    // TEST 2 : id inexistant → 404
    // ─────────────────────────────────────────────────────────

    /**
     * Un PUT sur un ID qui n'existe pas en base doit retourner 404.
     * Le contrôleur utilise $productRepository->find($id) et retourne 404 si null.
     */
    public function testMiseAJourIdInexistantRetourne404(): void
    {
        $this->putProduct(99999, [
            'name'             => 'Produit inexistant',
            'shortDescription' => 'Description courte',
            'description'      => 'Description longue',
            'price'            => 10.00,
            'quantity'         => 1,
            'categoryId'       => $this->categoryId,
        ]);

        $this->assertResponseStatusCodeSame(404);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
    }
}

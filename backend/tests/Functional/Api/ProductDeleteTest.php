<?php

namespace App\Tests\Functional\Api;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TEST 4 — Suppression d'un produit via DELETE /api/products/{id}.
 *
 * Cas testés :
 *   - DELETE existant      → 200 OK + produit absent de la base après suppression
 *   - DELETE id inexistant → 404 Not Found
 *
 * Commande : php bin/phpunit tests/Functional/Api/ProductDeleteTest.php --testdox
 */
class ProductDeleteTest extends WebTestCase
{
    private KernelBrowser $client;
    private int $productId;

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

        // Crée une catégorie et un produit que l'on va supprimer
        $category = new Category();
        $category->setName('Test Catégorie');
        $em->persist($category);

        $product = new Product();
        $product->setName('Produit à supprimer');
        $product->setShortDescription('Description courte');
        $product->setDescription('Description longue');
        $product->setPrice('9.90');
        $product->setQuantity(3);
        $product->setCategory($category);
        $em->persist($product);

        $em->flush();

        $this->productId = $product->getId();
    }

    // ─────────────────────────────────────────────────────────
    // TEST 1 : suppression réussie → 200 + produit absent en base
    // ─────────────────────────────────────────────────────────

    /**
     * Un DELETE sur un produit existant doit :
     *   - retourner 200
     *   - ne plus retrouver le produit en base via le repository
     *
     * La vérification en base (et pas seulement le code HTTP) est importante :
     * un bug pourrait retourner 200 sans supprimer réellement en base.
     */
    public function testSuppressionRetourne200EtProduitAbsentEnBase(): void
    {
        $this->client->request('DELETE', "/api/products/{$this->productId}");

        // Vérifie le code HTTP
        $this->assertResponseStatusCodeSame(200);

        // Vérifie que le produit n'existe plus en base de données
        // On recharge un EntityManager frais pour éviter le cache de l'EM précédent
        $em         = static::getContainer()->get(EntityManagerInterface::class);
        $repository = static::getContainer()->get(ProductRepository::class);

        // find() retourne null si le produit n'existe pas
        $productEnBase = $repository->find($this->productId);

        $this->assertNull(
            $productEnBase,
            "Le produit #{$this->productId} devrait avoir été supprimé de la base"
        );
    }

    // ─────────────────────────────────────────────────────────
    // TEST 2 : id inexistant → 404
    // ─────────────────────────────────────────────────────────

    /**
     * Un DELETE sur un ID inexistant doit retourner 404 avec un message d'erreur.
     */
    public function testSuppressionIdInexistantRetourne404(): void
    {
        $this->client->request('DELETE', '/api/products/99999');

        $this->assertResponseStatusCodeSame(404);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
    }
}

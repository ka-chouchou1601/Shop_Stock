<?php

namespace App\Tests\Functional\Api;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TEST 2 — Création d'un produit via POST /api/products.
 *
 * Test FONCTIONNEL : on envoie de vraies requêtes HTTP au kernel Symfony
 * et on vérifie le code de réponse et le corps JSON retourné.
 *
 * Base de données : SQLite (voir DATABASE_URL dans .env.test).
 * Le schéma est recréé avant chaque test (setUp) pour garantir l'isolation.
 *
 * Cas testés :
 *   - POST valide          → 201 Created + objet produit dans la réponse
 *   - POST sans nom        → 422 Unprocessable Entity + tableau d'erreurs
 *   - POST prix négatif    → 422
 *   - POST quantité négative → 422
 *
 * Commande : php bin/phpunit tests/Functional/Api/ProductCreationTest.php --testdox
 */
class ProductCreationTest extends WebTestCase
{
    private KernelBrowser $client;
    private int $categoryId;

    // ─────────────────────────────────────────────────────────
    // SETUP — exécuté avant chaque test
    // ─────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        // Crée le client HTTP Symfony (démarre le kernel en mode test)
        $this->client = static::createClient();

        // Récupère l'EntityManager depuis le container de test
        // (le container de test donne accès aux services privés)
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Recrée le schéma depuis zéro pour garantir un état vierge
        $schemaTool = new SchemaTool($em);
        $metadata   = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // Crée une catégorie de test : les tests de création en ont besoin
        $category = new Category();
        $category->setName('Test Catégorie');
        $em->persist($category);
        $em->flush();

        $this->categoryId = $category->getId();
    }

    // ─────────────────────────────────────────────────────────
    // HELPER — envoie une requête POST /api/products
    // ─────────────────────────────────────────────────────────

    /**
     * Envoie une requête POST JSON et retourne le tableau décodé de la réponse.
     *
     * @param array<string, mixed> $body
     */
    private function postProduct(array $body): void
    {
        $this->client->request(
            'POST',
            '/api/products',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($body)
        );
    }

    // ─────────────────────────────────────────────────────────
    // TEST 1 : création valide → 201
    // ─────────────────────────────────────────────────────────

    /**
     * Un POST avec toutes les données valides doit créer le produit
     * et retourner un 201 avec l'objet produit complet.
     */
    public function testCreationValideRetourne201(): void
    {
        $this->postProduct([
            'name'             => 'Produit de test',
            'shortDescription' => 'Courte description',
            'description'      => 'Description complète du produit de test',
            'price'            => 19.90,
            'quantity'         => 10,
            'categoryId'       => $this->categoryId,
        ]);

        // Vérifie le code HTTP retourné
        $this->assertResponseStatusCodeSame(201);

        // Vérifie que la réponse est du JSON
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        // Vérifie que l'objet retourné contient les champs attendus
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $response);
        $this->assertSame('Produit de test', $response['name']);
    }

    // ─────────────────────────────────────────────────────────
    // TEST 2 : nom manquant → 422
    // ─────────────────────────────────────────────────────────

    /**
     * Un produit sans nom doit être rejeté avec un 422.
     * Le contrôleur valide les données avant de persister.
     */
    public function testCreationSansNomRetourne422(): void
    {
        $this->postProduct([
            // 'name' intentionnellement absent
            'shortDescription' => 'Courte description',
            'description'      => 'Description',
            'price'            => 19.90,
            'quantity'         => 10,
            'categoryId'       => $this->categoryId,
        ]);

        $this->assertResponseStatusCodeSame(422);

        // La réponse doit contenir un tableau "errors" avec au moins une entrée
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
        $this->assertNotEmpty($response['errors']);
    }

    // ─────────────────────────────────────────────────────────
    // TEST 3 : prix négatif → 422
    // ─────────────────────────────────────────────────────────

    /**
     * Un prix négatif ou nul n'est pas valide pour un produit.
     * La règle métier : price > 0.
     */
    public function testCreationPrixNegatifRetourne422(): void
    {
        $this->postProduct([
            'name'             => 'Produit invalide',
            'shortDescription' => 'Courte description',
            'description'      => 'Description',
            'price'            => -5.00,   // prix invalide
            'quantity'         => 10,
            'categoryId'       => $this->categoryId,
        ]);

        $this->assertResponseStatusCodeSame(422);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
    }

    // ─────────────────────────────────────────────────────────
    // TEST 4 : quantité négative → 422
    // ─────────────────────────────────────────────────────────

    /**
     * Une quantité négative est impossible physiquement.
     * La règle métier : quantity >= 0.
     */
    public function testCreationQuantiteNegativeRetourne422(): void
    {
        $this->postProduct([
            'name'             => 'Produit invalide',
            'shortDescription' => 'Courte description',
            'description'      => 'Description',
            'price'            => 19.90,
            'quantity'         => -1,      // quantité invalide
            'categoryId'       => $this->categoryId,
        ]);

        $this->assertResponseStatusCodeSame(422);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
    }
}

<?php

namespace App\Tests\Functional\Api;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TEST 5 — Filtrage des produits via GET /api/products.
 *
 * Cas testés :
 *   - GET ?availability=Rupture → retourne UNIQUEMENT les produits en rupture (quantity = 0)
 *   - GET ?search=kit           → retourne UNIQUEMENT les produits dont le nom contient "kit"
 *
 * Ces tests vérifient non seulement que les bons produits sont retournés,
 * mais aussi que les produits qui ne correspondent PAS au filtre sont absents.
 * C'est la partie critique : un filtre trop permissif retournerait tout.
 *
 * Commande : php bin/phpunit tests/Functional/Api/ProductFilterTest.php --testdox
 */
class ProductFilterTest extends WebTestCase
{
    private KernelBrowser $client;

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

        // Charge des produits de test couvrant tous les cas de filtre
        $this->seedProducts($em);
    }

    // ─────────────────────────────────────────────────────────
    // HELPER — crée les produits nécessaires aux deux filtres
    // ─────────────────────────────────────────────────────────

    /**
     * Insère 3 produits avec des caractéristiques distinctes :
     *
     *   1. "Kit découverte"     | quantity = 0  → Rupture + contient "kit"
     *   2. "Chargeur USB"       | quantity = 10 → En stock + ne contient pas "kit"
     *   3. "Kit accessoire Pro" | quantity = 8  → En stock + contient "kit"
     *
     * Cela permet de tester :
     *   - ?availability=Rupture → uniquement le produit 1
     *   - ?search=kit           → les produits 1 et 3 (et PAS le produit 2)
     */
    private function seedProducts(EntityManagerInterface $em): void
    {
        $category = new Category();
        $category->setName('Test Catégorie');
        $em->persist($category);

        $produits = [
            ['nom' => 'Kit découverte',     'quantity' => 0],   // Rupture, contient "kit"
            ['nom' => 'Chargeur USB',        'quantity' => 10],  // En stock, sans "kit"
            ['nom' => 'Kit accessoire Pro',  'quantity' => 8],   // En stock, contient "kit"
        ];

        foreach ($produits as $data) {
            $product = new Product();
            $product->setName($data['nom']);
            $product->setShortDescription('Description courte');
            $product->setDescription('Description longue');
            $product->setPrice('9.90');
            $product->setQuantity($data['quantity']);
            $product->setCategory($category);
            $em->persist($product);
        }

        $em->flush();
    }

    // ─────────────────────────────────────────────────────────
    // TEST 1 : filtre availability=Rupture
    // ─────────────────────────────────────────────────────────

    /**
     * Le filtre ?availability=Rupture doit retourner UNIQUEMENT les produits
     * dont la quantity = 0.
     *
     * On vérifie :
     *   - Que le tableau retourné n'est pas vide
     *   - Que CHAQUE produit retourné est bien en "Rupture"
     *   - Que les produits "En stock" ou "Stock faible" sont absents
     */
    public function testFiltreRuptureRetourneUniquementLesRuptures(): void
    {
        $this->client->request('GET', '/api/products?availability=Rupture');

        $this->assertResponseStatusCodeSame(200);

        $produits = json_decode($this->client->getResponse()->getContent(), true);

        // Le filtre doit retourner au moins un résultat (notre "Kit découverte")
        $this->assertNotEmpty($produits, 'Le filtre Rupture devrait retourner au moins un produit');

        // Vérifie que TOUS les produits retournés sont bien en rupture
        foreach ($produits as $produit) {
            $this->assertSame(
                'Rupture',
                $produit['availability'],
                "Le produit '{$produit['name']}' ne devrait pas apparaître dans les ruptures"
            );
        }

        // Vérifie que les produits "En stock" sont absents
        $noms = array_column($produits, 'name');
        $this->assertNotContains('Chargeur USB',        $noms);
        $this->assertNotContains('Kit accessoire Pro',  $noms);
    }

    // ─────────────────────────────────────────────────────────
    // TEST 2 : filtre search=kit
    // ─────────────────────────────────────────────────────────

    /**
     * Le filtre ?search=kit doit retourner UNIQUEMENT les produits
     * dont le nom contient "kit" (recherche insensible à la casse via LIKE).
     *
     * On vérifie :
     *   - Que le résultat contient "Kit découverte" et "Kit accessoire Pro"
     *   - Que "Chargeur USB" est absent (son nom ne contient pas "kit")
     */
    public function testFiltreSearchKitRetourneUniquementLesProduitsSansKit(): void
    {
        $this->client->request('GET', '/api/products?search=kit');

        $this->assertResponseStatusCodeSame(200);

        $produits = json_decode($this->client->getResponse()->getContent(), true);

        // Le filtre doit trouver les 2 produits contenant "kit"
        $this->assertNotEmpty($produits);

        $noms = array_column($produits, 'name');

        // Produits attendus dans la réponse
        $this->assertContains('Kit découverte',    $noms);
        $this->assertContains('Kit accessoire Pro', $noms);

        // Produit qui NE doit PAS être dans la réponse
        $this->assertNotContains(
            'Chargeur USB',
            $noms,
            '"Chargeur USB" ne contient pas "kit" et ne devrait pas apparaître'
        );

        // Vérifie que chaque produit retourné contient "kit" dans son nom (insensible à la casse)
        foreach ($produits as $produit) {
            $this->assertStringContainsStringIgnoringCase(
                'kit',
                $produit['name'],
                "Le produit '{$produit['name']}' ne contient pas 'kit'"
            );
        }
    }
}

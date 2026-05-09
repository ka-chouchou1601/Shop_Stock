<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Product;
use PHPUnit\Framework\TestCase;

/**
 * TEST 1 — Disponibilité d'un produit selon son stock.
 *
 * On teste la méthode getAvailability() de l'entité Product.
 * C'est un test UNITAIRE pur : aucune base de données, aucun HTTP.
 * On instancie directement l'entité et on vérifie la valeur retournée.
 *
 * Règle métier testée :
 *   quantity = 0           → "Rupture"      (rupture de stock)
 *   1 ≤ quantity ≤ 5      → "Stock faible"  (bientôt épuisé)
 *   quantity > 5           → "En stock"     (stock suffisant)
 *
 * Commande : php bin/phpunit tests/Unit/Entity/ProductAvailabilityTest.php --testdox
 */
class ProductAvailabilityTest extends TestCase
{
    // ─────────────────────────────────────────────────────────
    // HELPER
    // ─────────────────────────────────────────────────────────

    /**
     * Crée un Product avec uniquement la quantité renseignée.
     * Les autres champs (name, price…) sont inutiles pour ce test.
     */
    private function makeProduct(int $quantity): Product
    {
        $product = new Product();
        $product->setQuantity($quantity);

        return $product;
    }

    // ─────────────────────────────────────────────────────────
    // CAS "RUPTURE" — quantity = 0
    // ─────────────────────────────────────────────────────────

    /**
     * Une quantité nulle doit retourner "Rupture".
     * C'est la valeur sentinelle : le produit ne peut pas être commandé.
     */
    public function testQuantiteZeroEstRupture(): void
    {
        $this->assertSame('Rupture', $this->makeProduct(0)->getAvailability());
    }

    // ─────────────────────────────────────────────────────────
    // CAS "STOCK FAIBLE" — 1 ≤ quantity ≤ 5
    // ─────────────────────────────────────────────────────────

    /**
     * Borne basse : 1 unité restante → encore commandable mais presque épuisé.
     */
    public function testQuantiteTroisEstStockFaible(): void
    {
        $this->assertSame('Stock faible', $this->makeProduct(3)->getAvailability());
    }

    /**
     * Borne haute : 5 unités restantes → toujours "Stock faible" (seuil exclusif à 5).
     * Ce cas valide que la règle est quantity > 5 (et non ≥ 5) pour "En stock".
     */
    public function testQuantiteCinqEstStockFaible(): void
    {
        $this->assertSame('Stock faible', $this->makeProduct(5)->getAvailability());
    }

    // ─────────────────────────────────────────────────────────
    // CAS "EN STOCK" — quantity > 5
    // ─────────────────────────────────────────────────────────

    /**
     * Borne basse de "En stock" : 6 unités franchissent le seuil.
     * Ce test documente la frontière précise entre "Stock faible" et "En stock".
     */
    public function testQuantiteSixEstEnStock(): void
    {
        $this->assertSame('En stock', $this->makeProduct(6)->getAvailability());
    }

    /**
     * Valeur confortable : 12 unités → clairement "En stock".
     */
    public function testQuantiteDouzEstEnStock(): void
    {
        $this->assertSame('En stock', $this->makeProduct(12)->getAvailability());
    }
}

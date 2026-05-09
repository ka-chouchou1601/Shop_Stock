<?php

namespace App\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;

/**
 * TEST 6 — Calcul du montant en centimes pour Stripe.
 *
 * Test UNITAIRE pur : aucune base de données, aucun HTTP, aucune clé Stripe.
 * On teste la formule exacte utilisée dans PaymentController::createIntent() :
 *
 *   $total        = somme des (price × quantity) pour chaque item
 *   $totalCentimes = (int) round($total * 100)
 *
 * Pourquoi tester cette formule ?
 *   - Stripe exige des centimes entiers (pas de virgule).
 *   - Les flottants PHP sont inexacts : 29.90 * 100 peut donner 2989.9999...
 *     round() corrige cet arrondi avant la conversion en int.
 *   - Un bug ici (oubli du ×100, mauvais arrondi) se traduirait par un montant
 *     facturé erroné au client — erreur silencieuse et critique.
 *
 * Commande : php bin/phpunit tests/Unit/Payment/PaymentCalculationTest.php --testdox
 */
class PaymentCalculationTest extends TestCase
{
    // ─────────────────────────────────────────────────────────
    // HELPER — réplique exacte de la logique du contrôleur
    // ─────────────────────────────────────────────────────────

    /**
     * Calcule le total en centimes à partir d'un tableau d'articles.
     *
     * Cette méthode est une copie fidèle du code dans PaymentController.
     * L'objectif est de tester la formule en isolation, sans dépendance HTTP.
     *
     * @param array<array{price: float|int, quantity: int}> $items
     */
    private function calculerCentimes(array $items): int
    {
        // Accumulation du total en euros (float)
        $total = 0.0;

        foreach ($items as $item) {
            $price    = (float) ($item['price']    ?? 0);
            $quantity = (int)   ($item['quantity'] ?? 0);
            $total   += $price * $quantity;
        }

        // Conversion en centimes : ×100 puis round() pour corriger les erreurs
        // de précision flottante avant de caster en entier.
        return (int) round($total * 100);
    }

    /**
     * Vérifie si un montant en centimes serait rejeté par la validation du contrôleur.
     * La règle : $totalCentimes <= 0 → erreur.
     */
    private function estMontantInvalide(int $centimes): bool
    {
        return $centimes <= 0;
    }

    // ─────────────────────────────────────────────────────────
    // CAS : produit unique
    // ─────────────────────────────────────────────────────────

    /**
     * 29,90 € × 1 unité → 2990 centimes.
     *
     * Ce test valide aussi le comportement de round() sur les flottants :
     * en PHP, 29.90 * 100 donne parfois 2989.9999... sans round().
     */
    public function testUnProduitA29_90EurosDonne2990Centimes(): void
    {
        $centimes = $this->calculerCentimes([
            ['price' => 29.90, 'quantity' => 1],
        ]);

        $this->assertSame(2990, $centimes);
    }

    // ─────────────────────────────────────────────────────────
    // CAS : produit avec quantité > 1
    // ─────────────────────────────────────────────────────────

    /**
     * 5,90 € × 2 unités = 11,80 € → 1180 centimes.
     *
     * Valide que la multiplication price × quantity est correcte
     * et que l'arrondi fonctionne aussi sur les multiples.
     */
    public function testDeuxProduitA5_90EurosDonne1180Centimes(): void
    {
        $centimes = $this->calculerCentimes([
            ['price' => 5.90, 'quantity' => 2],
        ]);

        $this->assertSame(1180, $centimes);
    }

    // ─────────────────────────────────────────────────────────
    // CAS : montant nul → erreur de validation
    // ─────────────────────────────────────────────────────────

    /**
     * Un panier avec des articles à prix 0 ou quantité 0 → total = 0 centimes.
     * La validation du contrôleur refuse tout montant ≤ 0.
     *
     * Stripe refuserait lui aussi un paiement à 0 centime, donc ce filtre
     * est une double protection : on ne l'envoie jamais à Stripe.
     */
    public function testMontantZeroEstInvalide(): void
    {
        // Un article avec prix nul → total = 0
        $centimes = $this->calculerCentimes([
            ['price' => 0.0, 'quantity' => 1],
        ]);

        $this->assertSame(0, $centimes);

        // La validation du contrôleur ($totalCentimes <= 0) doit rejeter ce cas
        $this->assertTrue(
            $this->estMontantInvalide($centimes),
            'Un montant de 0 centimes doit être considéré invalide'
        );
    }
}

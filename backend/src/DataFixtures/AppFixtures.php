<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * AppFixtures — charge les données initiales de test en base de données.
 *
 * Exécution : php bin/console doctrine:fixtures:load --no-interaction
 * ATTENTION : cette commande vide toutes les tables avant d'insérer les fixtures.
 *
 * Données chargées :
 *   - 1 utilisateur admin
 *   - 5 catégories
 *   - 5 produits répartis dans les catégories
 */
class AppFixtures extends Fixture
{
    // Injection du service de hachage de mot de passe via le constructeur
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ─────────────────────────────────────────────────────────
        // 1. UTILISATEUR ADMIN
        // ─────────────────────────────────────────────────────────

        $admin = new User();
        $admin->setEmail('admin@stockshop.test');
        $admin->setName('Admin StockShop');
        $admin->setRole('ROLE_ADMIN');

        // Le mot de passe est hashé via le service Symfony : jamais stocké en clair en base
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'password');
        $admin->setPassword($hashedPassword);

        $manager->persist($admin);

        // ─────────────────────────────────────────────────────────
        // 2. CATÉGORIES
        // ─────────────────────────────────────────────────────────

        // Tableau associatif : nom → objet Category (pour réutilisation dans les produits)
        $categories = [];
        $categoryNames = ['E-liquides', 'Accessoires', 'Matériel', 'Pièces détachées', 'Autre'];

        foreach ($categoryNames as $name) {
            $category = new Category();
            $category->setName($name);
            $manager->persist($category);
            // On garde une référence indexée par nom pour l'associer aux produits ci-dessous
            $categories[$name] = $category;
        }

        // ─────────────────────────────────────────────────────────
        // 3. PRODUITS
        // ─────────────────────────────────────────────────────────

        // Définition des produits : [nom, catégorie, description courte, description, prix, quantité, imageUrl]
        $productsData = [
            [
                'name'             => 'Kit découverte',
                'category'         => 'Matériel',
                'shortDescription' => 'Kit complet pour débuter',
                'description'      => 'Kit tout-en-un idéal pour les débutants. Inclut un mod, un clearomiseur et un chargeur USB.',
                'price'            => '29.90',
                'quantity'         => 12,  // quantity > 5 → "En stock"
                'imageUrl'         => 'https://images.unsplash.com/photo-1625895197185-efcec01cffe0?w=400',
            ],
            [
                'name'             => 'Chargeur USB-C',
                'category'         => 'Accessoires',
                'shortDescription' => 'Chargeur rapide USB-C universel',
                'description'      => 'Chargeur universel USB-C compatible avec la plupart des mods du marché. Charge rapide 2A.',
                'price'            => '12.90',
                'quantity'         => 4,   // 1 ≤ quantity ≤ 5 → "Stock faible"
                'imageUrl'         => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=400',
            ],
            [
                'name'             => 'Résistance 0.8 ohm',
                'category'         => 'Pièces détachées',
                'shortDescription' => 'Résistance mesh 0.8 ohm — pack de 5',
                'description'      => 'Résistances mesh de remplacement 0.8 ohm. Compatible avec les clearomiseurs standard. Vendu par pack de 5.',
                'price'            => '3.90',
                'quantity'         => 0,   // quantity = 0 → "Rupture"
                'imageUrl'         => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=400',
            ],
            [
                'name'             => 'Pochette de rangement',
                'category'         => 'Accessoires',
                'shortDescription' => 'Pochette de transport pour kit complet',
                'description'      => 'Pochette de rangement zippée pour transporter votre matériel en toute sécurité. Compartiments dédiés.',
                'price'            => '8.50',
                'quantity'         => 25,  // quantity > 5 → "En stock"
                'imageUrl'         => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400',
            ],
            [
                'name'             => 'Produit test e-liquide sans nicotine',
                'category'         => 'E-liquides',
                'shortDescription' => 'E-liquide 0mg — 50ml',
                'description'      => 'E-liquide de test sans nicotine, base 50/50 PG/VG. Arôme neutre. Flacon de 50ml.',
                'price'            => '5.90',
                'quantity'         => 7,   // quantity > 5 → "En stock"
                'imageUrl'         => 'https://images.unsplash.com/photo-1571506165871-ee72a35bc9d4?w=400',
            ],
        ];

        foreach ($productsData as $data) {
            $product = new Product();
            $product->setName($data['name']);
            $product->setShortDescription($data['shortDescription']);
            $product->setDescription($data['description']);
            $product->setPrice($data['price']);
            $product->setQuantity($data['quantity']);
            $product->setCategory($categories[$data['category']]);
            $product->setImageUrl($data['imageUrl']);
            $manager->persist($product);
        }

        // Envoi de toutes les entités en base de données en une seule transaction
        $manager->flush();
    }
}

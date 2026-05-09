<?php

namespace App\Controller\Api;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * AuthController — gère l'authentification des utilisateurs de l'API.
 *
 * Approche choisie : vérification manuelle email + mot de passe hashé.
 * On n'utilise pas le système json_login de Symfony car l'API est stateless
 * (pas de session) et le frontend gère l'état de connexion côté client.
 */
class AuthController extends AbstractController
{
    /**
     * POST /api/login — Authentification d'un utilisateur.
     *
     * Body JSON attendu :
     *   { "email": "...", "password": "..." }
     *
     * Réponse succès (200) :
     *   { "id": 1, "email": "...", "name": "...", "role": "ROLE_ADMIN" }
     *
     * Réponse échec (401) :
     *   { "error": "Identifiants incorrects" }
     *
     * Note sécurité : on retourne toujours le même message d'erreur qu'il s'agisse
     * d'un email inconnu ou d'un mauvais mot de passe (évite l'énumération d'emails).
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        // Décodage du corps JSON de la requête
        $data = json_decode($request->getContent(), true);

        $email    = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        // Vérification de la présence des champs obligatoires
        if (!$email || !$password) {
            return $this->json(['error' => 'Identifiants incorrects'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Recherche de l'utilisateur par email en base de données
        $user = $userRepository->findByEmail($email);

        // Si l'utilisateur n'existe pas OU si le mot de passe ne correspond pas au hash stocké
        // isPasswordValid() compare le mot de passe en clair avec le hash bcrypt/argon stocké
        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Identifiants incorrects'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Authentification réussie : retourne les données non-sensibles de l'utilisateur
        // Le mot de passe (hashé) n'est jamais inclus dans la réponse
        return $this->json([
            'id'    => $user->getId(),
            'email' => $user->getEmail(),
            'name'  => $user->getName(),
            'role'  => $user->getRole(),
        ]);
    }
}

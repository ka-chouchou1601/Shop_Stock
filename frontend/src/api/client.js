/**
 * client.js — Instance Axios partagée par tous les modules API.
 *
 * Centraliser la configuration ici évite de répéter la baseURL et les headers
 * dans chaque fichier api/*.js. Tous les appels passent par cet objet.
 *
 * Fonctionnalités :
 *   - baseURL     : pointe vers le backend Symfony (port 8001)
 *   - Intercepteur requête  : ajoute automatiquement Content-Type: application/json
 *   - Intercepteur réponse  : redirige vers /login si le backend retourne 401
 */
import axios from 'axios';

const client = axios.create({
  // URL de base du backend Symfony — toutes les routes /api/... y sont relatives
  baseURL: 'http://127.0.0.1:8001',
});

// ── Intercepteur de requête ────────────────────────────────────────────────
// Exécuté avant chaque appel HTTP. Ajoute le Content-Type JSON pour que
// Symfony puisse décoder le body avec $request->getContent() + json_decode().
client.interceptors.request.use((config) => {
  config.headers['Content-Type'] = 'application/json';
  return config;
});

// ── Intercepteur de réponse ────────────────────────────────────────────────
// Exécuté après chaque réponse HTTP.
// Si le backend retourne 401 (non authentifié ou token expiré), on redirige
// vers /login pour que l'utilisateur se reconnecte.
client.interceptors.response.use(
  // Réponse OK (2xx) : on la laisse passer sans modification
  (response) => response,

  // Erreur HTTP : on intercepte les 401 pour la redirection
  (error) => {
    if (error.response?.status === 401) {
      // Supprime les données de session et redirige vers la page de connexion
      localStorage.removeItem('user');
      window.location.href = '/login';
    }
    // Propage l'erreur pour que le code appelant puisse la gérer (catch)
    return Promise.reject(error);
  }
);

export default client;

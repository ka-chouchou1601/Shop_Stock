/**
 * authApi.js — Fonctions d'authentification.
 *
 * login()  : envoie les identifiants au backend et reçoit les infos utilisateur.
 *             Le backend vérifie l'email/mot de passe et retourne { token, user }.
 *
 * logout() : supprime les données de session du localStorage côté client.
 *             Pas de requête serveur nécessaire si l'API est stateless (JWT).
 */
import client from './client';

/**
 * Authentifie un utilisateur.
 * Retourne une Promise<AxiosResponse> avec { token, user } en cas de succès.
 */
export const login = (email, password) =>
  client.post('/api/login', { email, password });

/**
 * Déconnecte l'utilisateur côté client.
 * Supprime la clé 'user' du localStorage (token + données de session).
 */
export const logout = () => localStorage.removeItem('user');

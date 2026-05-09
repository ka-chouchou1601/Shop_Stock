/**
 * categoryApi.js — Accès aux catégories de produits.
 *
 * Les catégories sont utilisées dans les formulaires (filtre de la liste,
 * sélecteur lors de la création/modification d'un produit).
 */
import client from './client';

/**
 * Récupère toutes les catégories.
 * Retourne une Promise<AxiosResponse> avec un tableau de { id, name }.
 */
export const getCategories = () => client.get('/api/categories');

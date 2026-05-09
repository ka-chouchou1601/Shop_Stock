/**
 * productApi.js — CRUD complet sur les produits.
 *
 * Chaque fonction correspond à une route du ProductController Symfony :
 *   getProducts  → GET  /api/products?filters
 *   getProduct   → GET  /api/products/:id
 *   createProduct→ POST /api/products
 *   updateProduct→ PUT  /api/products/:id
 *   deleteProduct→ DELETE /api/products/:id
 */
import client from './client';

/**
 * Récupère la liste des produits avec des filtres optionnels.
 *
 * @param {Object} filters - Filtres optionnels :
 *   - category     {number} id de la catégorie
 *   - availability {string} "En stock" | "Stock faible" | "Rupture"
 *   - search       {string} recherche textuelle sur le nom
 *
 * Axios sérialise l'objet `params` en query string : ?category=2&search=kit
 */
export const getProducts = (filters = {}) =>
  client.get('/api/products', { params: filters });

/** Récupère le détail d'un produit par son id. */
export const getProduct = (id) => client.get('/api/products/' + id);

/** Crée un nouveau produit. @param {Object} data - Champs du produit. */
export const createProduct = (data) => client.post('/api/products', data);

/** Met à jour un produit existant. */
export const updateProduct = (id, data) => client.put('/api/products/' + id, data);

/** Supprime un produit. */
export const deleteProduct = (id) => client.delete('/api/products/' + id);

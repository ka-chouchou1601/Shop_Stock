/**
 * App.jsx — Routeur principal de l'application Stock&Shop.
 *
 * Définit toutes les routes de l'application avec React Router v6.
 *
 * Routes publiques (accessibles sans connexion) :
 *   /login             → page de connexion admin
 *   /                  → catalogue produits (vitrine client)
 *   /products/:id      → fiche détail d'un produit
 *   /cart              → panier d'achat
 *   /checkout          → paiement Stripe
 *   /payment/success   → confirmation après paiement
 *
 * Route protégée :
 *   /admin             → gestion CRUD des produits
 *   La protection est gérée dans AdminProductsPage via localStorage.getItem('user').
 */
import { BrowserRouter, Routes, Route } from 'react-router-dom';

import LoginPage          from './pages/LoginPage';
import ProductCatalogPage from './pages/ProductCatalogPage';
import ProductDetailPage  from './pages/ProductDetailPage';
import CartPage           from './pages/CartPage';
import CheckoutPage       from './pages/CheckoutPage';
import PaymentSuccessPage from './pages/PaymentSuccessPage';
import AdminProductsPage  from './pages/AdminProductsPage';

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        {/* Authentification */}
        <Route path="/login" element={<LoginPage />} />

        {/* Vitrine client */}
        <Route path="/"                  element={<ProductCatalogPage />} />
        <Route path="/products/:id"      element={<ProductDetailPage />} />
        <Route path="/cart"              element={<CartPage />} />
        <Route path="/checkout"          element={<CheckoutPage />} />
        <Route path="/payment/success"   element={<PaymentSuccessPage />} />

        {/* Administration */}
        <Route path="/admin" element={<AdminProductsPage />} />
      </Routes>
    </BrowserRouter>
  );
}

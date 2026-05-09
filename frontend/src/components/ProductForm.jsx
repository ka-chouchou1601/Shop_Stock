/**
 * ProductForm — Formulaire de création ou de modification d'un produit.
 *
 * Utilisé dans AdminProductsPage pour les deux opérations (même composant,
 * comportement différent selon la présence de `product`).
 *
 * Props :
 *   product    {Object|null} — données du produit à modifier (null = création)
 *   categories {Array}       — liste des catégories [{ id, name }]
 *   onSubmit   {Function}    — callback(formData) appelé si le formulaire est valide
 *   onCancel   {Function}    — callback appelé quand l'utilisateur annule
 *   loading    {boolean}     — désactive le bouton submit pendant l'appel API
 *
 * Validation :
 *   Chaque champ est validé avant l'appel à onSubmit.
 *   Les messages d'erreur s'affichent sous le champ concerné.
 *   On ne passe jamais des données invalides au parent.
 */
import { useState } from 'react';

// ── Règles de validation ────────────────────────────────────────────────────
// Retourne un objet { champ: 'message d\'erreur' } pour chaque champ invalide.
// Un objet vide signifie que le formulaire est valide.
function validate(form) {
  const errors = {};

  if (!form.name.trim()) {
    errors.name = 'Le nom est obligatoire.';
  } else if (form.name.trim().length < 2) {
    errors.name = 'Le nom doit contenir au moins 2 caractères.';
  }

  const price = parseFloat(form.price);
  if (form.price === '' || isNaN(price)) {
    errors.price = 'Le prix est obligatoire.';
  } else if (price <= 0) {
    errors.price = 'Le prix doit être supérieur à 0.';
  }

  const qty = parseInt(form.quantity, 10);
  if (form.quantity === '' || isNaN(qty)) {
    errors.quantity = 'La quantité est obligatoire.';
  } else if (qty < 0) {
    errors.quantity = 'La quantité ne peut pas être négative.';
  }

  if (!form.categoryId) {
    errors.categoryId = 'Veuillez sélectionner une catégorie.';
  }

  if (form.imageUrl && !/^https?:\/\/.+/.test(form.imageUrl)) {
    errors.imageUrl = 'L\'URL doit commencer par http:// ou https://';
  }

  return errors;
}

// ── Composant réutilisable pour un champ de formulaire ─────────────────────
function Field({ label, error, required, children }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">
        {label} {required && <span className="text-red-500">*</span>}
      </label>
      {children}
      {error && (
        <p className="text-red-500 text-xs mt-1">{error}</p>
      )}
    </div>
  );
}

// ── Classes CSS partagées pour les inputs ──────────────────────────────────
const inputClass = (hasError) =>
  `w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 transition ${
    hasError
      ? 'border-red-400 focus:ring-red-300'
      : 'border-gray-300 focus:ring-indigo-400'
  }`;

export default function ProductForm({ product, categories, onSubmit, onCancel, loading }) {
  // Pré-remplit le formulaire si `product` est fourni (mode modification)
  const [form, setForm] = useState({
    name:             product?.name             ?? '',
    shortDescription: product?.shortDescription ?? '',
    description:      product?.description      ?? '',
    price:            product?.price            ?? '',
    quantity:         product?.quantity         ?? 0,
    categoryId:       product?.category?.id     ?? '',
    imageUrl:         product?.imageUrl         ?? '',
  });

  // errors : objet { champ: 'message' } — vide si le formulaire est valide
  const [errors, setErrors] = useState({});

  const handle = (key, value) => {
    setForm((prev) => ({ ...prev, [key]: value }));
    // Efface l'erreur du champ dès que l'utilisateur le modifie
    if (errors[key]) setErrors((prev) => ({ ...prev, [key]: undefined }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();

    const validationErrors = validate(form);
    if (Object.keys(validationErrors).length > 0) {
      // Il y a des erreurs : on les affiche et on n'appelle pas onSubmit
      setErrors(validationErrors);
      return;
    }

    // Formulaire valide : on transmet les données au parent (AdminProductsPage)
    onSubmit(form);
  };

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-4">
      {/*
       * noValidate désactive la validation native du navigateur (tooltips HTML5).
       * On préfère notre propre validation pour un meilleur contrôle de l'UX.
       */}

      <div className="grid grid-cols-2 gap-4">

        {/* ── Nom ─────────────────────────────────────────────────────────── */}
        <div className="col-span-2">
          <Field label="Nom" required error={errors.name}>
            <input
              type="text"
              value={form.name}
              onChange={(e) => handle('name', e.target.value)}
              placeholder="Ex : Kit découverte électronique"
              className={inputClass(!!errors.name)}
            />
          </Field>
        </div>

        {/* ── Prix ────────────────────────────────────────────────────────── */}
        <Field label="Prix (€)" required error={errors.price}>
          <input
            type="number"
            step="0.01"
            min="0.01"
            value={form.price}
            onChange={(e) => handle('price', e.target.value)}
            placeholder="0.00"
            className={inputClass(!!errors.price)}
          />
        </Field>

        {/* ── Quantité ─────────────────────────────────────────────────────── */}
        <Field label="Quantité" required error={errors.quantity}>
          <input
            type="number"
            min="0"
            value={form.quantity}
            onChange={(e) => handle('quantity', e.target.value)}
            className={inputClass(!!errors.quantity)}
          />
        </Field>

        {/* ── Catégorie ───────────────────────────────────────────────────── */}
        <Field label="Catégorie" required error={errors.categoryId}>
          <select
            value={form.categoryId}
            onChange={(e) => handle('categoryId', e.target.value)}
            className={inputClass(!!errors.categoryId)}
          >
            <option value="">— Choisir une catégorie —</option>
            {categories.map((cat) => (
              <option key={cat.id} value={cat.id}>{cat.name}</option>
            ))}
          </select>
        </Field>

        {/* ── URL image ───────────────────────────────────────────────────── */}
        <Field label="URL de l'image" error={errors.imageUrl}>
          <input
            type="url"
            value={form.imageUrl}
            onChange={(e) => handle('imageUrl', e.target.value)}
            placeholder="https://..."
            className={inputClass(!!errors.imageUrl)}
          />
        </Field>

        {/* ── Description courte ──────────────────────────────────────────── */}
        <div className="col-span-2">
          <Field label="Description courte" error={errors.shortDescription}>
            <input
              type="text"
              value={form.shortDescription}
              onChange={(e) => handle('shortDescription', e.target.value)}
              placeholder="Résumé en une phrase affiché sur la carte produit"
              className={inputClass(!!errors.shortDescription)}
            />
          </Field>
        </div>

        {/* ── Description complète ────────────────────────────────────────── */}
        <div className="col-span-2">
          <Field label="Description complète" error={errors.description}>
            <textarea
              rows={4}
              value={form.description}
              onChange={(e) => handle('description', e.target.value)}
              placeholder="Description détaillée affichée sur la fiche produit"
              className={inputClass(!!errors.description)}
            />
          </Field>
        </div>

      </div>

      {/* ── Boutons ─────────────────────────────────────────────────────────── */}
      <div className="flex gap-3 justify-end pt-2 border-t border-gray-100">
        <button
          type="button"
          onClick={onCancel}
          className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition"
        >
          Annuler
        </button>
        <button
          type="submit"
          disabled={loading}
          className="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition"
        >
          {loading ? 'Enregistrement…' : (product ? 'Enregistrer les modifications' : 'Créer le produit')}
        </button>
      </div>
    </form>
  );
}

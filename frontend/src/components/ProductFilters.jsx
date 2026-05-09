/**
 * ProductFilters — Barre de filtres pour le catalogue produits.
 *
 * Contrôles disponibles :
 *   - Recherche textuelle (search)
 *   - Filtre par catégorie (select)
 *   - Filtre par disponibilité (select)
 *
 * Props :
 *   filters     {Object}   — état courant { search, category, availability }
 *   categories  {Array}    — liste des catégories [{ id, name }]
 *   onChange    {Function} — callback(updatedFilters) déclenché à chaque changement
 */
export default function ProductFilters({ filters, categories, onChange }) {
  const handle = (key, value) => onChange({ ...filters, [key]: value });

  return (
    <div className="flex flex-wrap gap-3 mb-6">
      {/* Recherche textuelle */}
      <input
        type="text"
        placeholder="Rechercher un produit…"
        value={filters.search ?? ''}
        onChange={(e) => handle('search', e.target.value)}
        className="border border-gray-300 rounded-lg px-3 py-2 text-sm flex-1 min-w-48 focus:outline-none focus:ring-2 focus:ring-indigo-400"
      />

      {/* Filtre par catégorie */}
      <select
        value={filters.category ?? ''}
        onChange={(e) => handle('category', e.target.value)}
        className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
      >
        <option value="">Toutes les catégories</option>
        {categories.map((cat) => (
          <option key={cat.id} value={cat.id}>{cat.name}</option>
        ))}
      </select>

      {/* Filtre par disponibilité */}
      <select
        value={filters.availability ?? ''}
        onChange={(e) => handle('availability', e.target.value)}
        className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
      >
        <option value="">Toutes disponibilités</option>
        <option value="En stock">En stock</option>
        <option value="Stock faible">Stock faible</option>
        <option value="Rupture">Rupture</option>
      </select>
    </div>
  );
}

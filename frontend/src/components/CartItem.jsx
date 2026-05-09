/**
 * CartItem — Ligne d'un article dans le panier.
 *
 * Affiche le nom, le prix unitaire, un champ quantité (input number),
 * le sous-total de la ligne et un bouton de suppression.
 *
 * Props :
 *   item             {Object}   — { id, name, price, quantity }
 *   onRemove         {Function} — callback(id) : supprime l'article
 *   onQuantityChange {Function} — callback(id, newQuantity) : met à jour la quantité
 */
export default function CartItem({ item, onRemove, onQuantityChange }) {
  const subtotal = (parseFloat(item.price) * item.quantity).toFixed(2);

  const handleQuantityChange = (e) => {
    // parseInt retourne NaN si vide → on force 1 comme valeur minimale
    const val = Math.max(1, parseInt(e.target.value) || 1);
    onQuantityChange(item.id, val);
  };

  return (
    <div className="flex items-center gap-4 py-4 border-b border-gray-100">
      {/* Nom et prix unitaire */}
      <div className="flex-1 min-w-0">
        <p className="font-medium text-gray-800 truncate">{item.name}</p>
        <p className="text-sm text-gray-500">{parseFloat(item.price).toFixed(2)} € / unité</p>
      </div>

      {/* Input quantité — min 1, pas de max côté panier (le backend valide le stock) */}
      <input
        type="number"
        min="1"
        value={item.quantity}
        onChange={handleQuantityChange}
        className="w-16 border border-gray-300 rounded-lg px-2 py-1 text-sm text-center focus:outline-none focus:ring-2 focus:ring-indigo-400"
      />

      {/* Sous-total de la ligne */}
      <span className="w-20 text-right font-semibold text-indigo-700">{subtotal} €</span>

      {/* Bouton suppression */}
      <button
        className="text-red-400 hover:text-red-600 text-lg leading-none ml-1"
        onClick={() => onRemove(item.id)}
        aria-label={`Supprimer ${item.name}`}
      >
        ✕
      </button>
    </div>
  );
}

/**
 * AvailabilityBadge — Badge coloré indiquant la disponibilité d'un produit.
 *
 * Affiche un label avec une couleur différente selon le statut :
 *   "En stock"    → vert
 *   "Stock faible"→ orange
 *   "Rupture"     → rouge
 *
 * Correspond à la règle métier de Product::getAvailability() côté Symfony.
 */

const colors = {
  'En stock':     'bg-green-100 text-green-800',
  'Stock faible': 'bg-orange-100 text-orange-800',
  'Rupture':      'bg-red-100 text-red-800',
};

export default function AvailabilityBadge({ availability }) {
  const classes = colors[availability] ?? 'bg-gray-100 text-gray-700';

  return (
    <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-medium ${classes}`}>
      {availability}
    </span>
  );
}

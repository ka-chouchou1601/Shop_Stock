/**
 * StatCard — Carte statistique avec bande colorée sur le bord gauche.
 *
 * Utilisée dans le tableau de bord admin pour afficher des métriques clés.
 * La bande `border-l-4` identifie visuellement la catégorie de la stat.
 *
 * Props :
 *   title {string} — intitulé de la stat (ex: "Total produits")
 *   count {string|number} — valeur affichée en grand
 *   color {string} — classe Tailwind de la bande gauche (ex: "border-indigo-500")
 */
export default function StatCard({ title, count, color = 'border-indigo-500' }) {
  return (
    <div className={`bg-white rounded-xl shadow p-6 border-l-4 ${color}`}>
      <p className="text-sm text-gray-500 font-medium">{title}</p>
      <p className="text-3xl font-bold text-gray-800 mt-1">{count}</p>
    </div>
  );
}

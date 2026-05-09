/**
 * main.jsx — Point d'entrée de l'application React.
 *
 * Monte le composant <App /> dans le div#root du index.html.
 * <React.StrictMode> active des vérifications supplémentaires en développement :
 * double-rendu des composants, détection d'effets de bord non purs, etc.
 */
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './index.css';
import App from './App.jsx';

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>
);

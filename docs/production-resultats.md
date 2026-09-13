# Saisie et validation de production

Sur `/production`, chaque lot en attente ou en cours présente directement « Saisir les résultats ». Le panneau latéral reprend le lot, la machine et le chargement. La vue consolidée permet désormais la saisie, avec contrôle des droits sur le site du lot et utilisation de sa tolérance. Les anciens liens `/production/create` et `/production/{id}` ouvrent respectivement le premier formulaire disponible et le détail latéral.

Les étapes visibles sont À produire, À valider, Écart à approuver, Validée et Annulée. Les compteurs de tâches reconnaissent les nouveaux statuts. Les indicateurs de farine et de rendement utilisent exclusivement les lots validés, avec un rendement pondéré sur les quantités réellement chargées.

Le formulaire calcule farine, déchets, rendement et écart. Hors tolérance, la justification est obligatoire et une approbation complémentaire est requise. La soumission ne crée pas de stock. Seule la validation crée farine en vrac, déchets, document OF et clôture l’alimentation. La confirmation et les soumissions utilisent l’AJAX commun ; les erreurs conservent la saisie.

Avant validation, les résultats soumis peuvent être corrigés avec un motif obligatoire, conservé dans l’audit avec les anciennes valeurs. Chaque correction recalcule la tolérance et le statut. Les formulaires obsolètes sont refusés à la soumission comme à la validation. Les lots validés/annulés ne sont pas modifiables. Les dates impossibles ou antérieures au début, quantités non finies et types de déchets invalides sont refusés. Les quantités sont arrondies à trois décimales pour éviter les écarts de précision flottante.

La validation complémentaire enregistre désormais correctement son auteur et sa date avant le changement de statut SQL.

Vérifications sur la base de test : 14 contrôles dans `tests/ProductionResultsIntegrationTest.php`, parcours complet AJAX dans `tests/browser/ProductionResultsBrowserTest.cjs` (soumission, refus d’un formulaire obsolète, correction disponible, validation finale, mobile), 18 scénarios globaux et 115 contrôles d’interface réussis. Aucune migration ni écriture de test dans les données réelles.

## Interface visuelle et enchaînement

Les quatre tuiles d’étape sont des boutons de filtre (bleu, violet, orange, vert), avec sélection accessible via `aria-pressed`. Les cartes reprennent l’illustration vectorielle des machines et donnent la priorité au nom et au chargement. Après saisie, une barre montre farine, déchets et écart ; un dépassement est explicitement indiqué. Avant saisie, aucun faux bilan à zéro n’est affiché.

Le formulaire comporte trois blocs : lot sélectionné, résultats, contrôle et fin. Le résumé du rendement et de l’écart reste visible près de la soumission, y compris sur mobile. Après soumission réussie, un bandeau propose le prochain lot à produire accessible, avec son numéro ; s’il n’en reste aucun, il l’indique. Les filtres, la pagination et la position de liste sont conservés lors du rafraîchissement AJAX.

Vérifications supplémentaires : six contrôles de proportions et d’états limites (`ProductionBalanceViewTest.php`), parcours de soumission/validation AJAX existant et test des tuiles, du lot suivant et du bilan mobile (`ProductionVisualUxBrowserTest.cjs`). Les 115 contrôles d’interface passent.

Les panneaux de saisie et de consultation utilisent désormais un en-tête compact avec icône, des sections sur fond clair, un bilan mis en évidence et des actions fixes. Le détail regroupe chargement illustré, bilan, traçabilité, déchets et justification. Le formulaire conserve ses trois étapes et son résumé fixe. Le mode de réduction des animations désactive également les transitions des descendants pour éviter un état de visibilité masqué lors des réouvertures. `ProductionPanelLayoutBrowserTest.cjs` vérifie les deux panneaux, leur défilement et les actions à 1440, 390 et 320 px, avec et sans réduction des animations. Les 115 contrôles d’interface passent.

### Liste tabulaire
La liste de Production affiche un lot par ligne : début/fin, lot/BSS, machine/silo, chargement, farine, déchets, rendement, étape et actions. Les filtres compacts combinent recherche, machine, silo, étape et intervalle inclusif de dates (début du lot ou fin de production). Une période inversée affiche une erreur explicite ; les lots sans date de fin sont exclus lorsqu'une borne est appliquée sur la fin de production.

Le tableau utilise un tri local accessible, une pagination de 10/25/50 lignes et conserve les filtres dans la session du navigateur. Les mises à jour restent en AJAX et les détails/formulaires s'ouvrent dans les modals latéraux existants. Sur mobile, le défilement horizontal est limité au tableau. `tests/browser/ProductionTableBrowserTest.cjs` contrôle ces comportements sans écriture en base.

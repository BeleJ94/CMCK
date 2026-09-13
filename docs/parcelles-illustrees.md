# Répertoire illustré des parcelles

La vue agriculture/plots affiche des cartes avec une illustration SVG locale, le nom et le code, la ferme, le statut, la variété, la campagne présentée et les superficies. Les illustrations sont schématiques : elles ne représentent ni une photographie ni les limites géographiques réelles. Aucun fichier image distant ni service externe n’est nécessaire.

La campagne affichée est la plus récente des campagnes en cours/en récolte, dans la période du jour et avec une planification non clôturée. À défaut, la dernière planification est présentée. Les campagnes simultanées supplémentaires sont signalées ; toutes les planifications figurent dans le panneau latéral. La surface exploitée est celle de la campagne affichée, sans cumul de campagnes successives. La superficie totale correspond au référentiel physique.

Les filtres recherche/ferme/statut/campagne recalculent les indicateurs et la pagination de neuf cartes. Les critères sont conservés dans la session du navigateur. Le détail est latéral et le bouton Planifier préremplit la ferme et la parcelle. La création conserve le circuit AJAX et sa confirmation ; les formulaires sont actualisés pour pouvoir planifier immédiatement une parcelle nouvellement créée.

Validation : syntaxe PHP/JavaScript, 115 contrôles d’interface, navigateur sur base isolée (illustrations, filtres, résultat vide, indicateurs, détail, préremplissage, création AJAX sans navigation, critères conservés, disponibilité immédiate de la nouvelle parcelle dans la planification, mobile sans débordement).

```sh
DAGRIL_DB_DATABASE=dagril_works_verified_test node tests/browser/PlotDirectoryBrowserTest.cjs
```

Le test nécessite le serveur local isolé décrit dans travaux-agricoles-validation.md et Playwright. Il crée une parcelle uniquement dans la base de test.

## Exploitation et récoltes sur l’illustration

La zone verte occupe une proportion exacte du dessin de la parcelle (surface déclarée / superficie physique). Les sacs et la barre dorée accompagnent le volume net des récoltes validées, non supprimées, rattachées uniquement à la planification affichée. L’objectif est la surface exploitée multipliée par le rendement visé. Les barres sont plafonnées à 100 %, tandis que les libellés conservent les éventuels dépassements. Les états sans planification et sans objectif sont explicites. Aucun KPI global n’est affiché.

Huit contrôles de rendu et calcul sont disponibles via `php tests/PlotIllustrationTest.php`. Le rendu a également été vérifié sur ordinateur, mobile et dans le panneau latéral, avec les 115 contrôles d’interface existants.

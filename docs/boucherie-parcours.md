# Parcours boucherie

Le groupe Boucherie du menu contient six sous-pages : Réceptions, Abattage, Fabrication, Stocks et DLC, Ventes et transferts, Recettes. `/butchery` ouvre Réceptions ; chaque activité possède une adresse `/butchery/{section}` conservée au rechargement et après une soumission AJAX ou classique.

Les compteurs montrent les tâches de l’activité ou les quantités disponibles. Les stocks affichent aussi le nombre de lots dont la DLC arrive dans les trois jours. Les formulaires restent dans des panneaux latéraux ; les coûts et additifs de fabrication sont regroupés dans des sections facultatives. Les erreurs métier conservent la saisie. Les confirmations de succès précisent la prochaine étape.

Le suivi des transferts distingue les actions d’approbation et d’expédition. Les 100 ventes les plus récentes sont consultables dans Ventes et transferts, avec client, lot, produit, quantité et statut. La requête est limitée au site BOUCH selon les contrôles d’accès du modèle existant.

Les sous-menus exigent la lecture du module ; Recettes exige aussi l’administration, vérifiée dans le contrôleur. La planification d’une fabrication utilise le droit de création, cohérent avec sa route POST. Les règles métier et les migrations restent inchangées.

Vérifications :

- `php tests/ButcheryNavigationTest.php` : retours AJAX, sections autorisées, refus CSRF, erreurs métier, accès Recettes et 404, sans base de données.
- `php scripts/test_ui_experience.php` : 121 contrôles statiques.
- `tests/browser/ButcheryUxBrowserTest.cjs` : six sous-pages et menus actifs, rechargement, panneaux, actions visibles à 1440/390/320 px, absence de débordement après mise en page, refus CSRF et 404. Exécuter sur le serveur local de test décrit dans `travaux-agricoles-validation.md`, avec Playwright disponible via `DAGRIL_PLAYWRIGHT_MODULE` si nécessaire. Le test ne soumet aucune opération métier valide.

Aucune migration SQL nécessaire.

## Historiques des réceptions et abattages

Les pages Réceptions et Abattage affichent désormais un historique sous les opérations en cours. Tous les statuts sont inclus, du plus récent au plus ancien, dans le périmètre BOUCH autorisé. Les réceptions supprimées logiquement restent exclues. Les tableaux utilisent la DataTable commune : recherche textuelle (y compris date et statut), tri, pagination de 10/25/50/100 lignes et densité réglable.

« Consulter » ouvre un panneau en lecture seule, disponible également en vue consolidée. Il montre l’auteur, les quantités et le lot créé ; les réceptions incluent le contrôle sanitaire, son auteur, sa décision et ses observations ; les abattages incluent les poids brut/tare/net et la validation. Les informations absentes sont indiquées par un tiret.

Vérification : `DAGRIL_DB_DATABASE=dagril_works_verified_test php tests/ButcheryHistoryIntegrationTest.php` vérifie les requêtes, les statuts, le périmètre et le rendu dans une transaction annulée. Il génère des fragments HTML temporaires pour `tests/browser/ButcheryHistoryBrowserTest.cjs`, qui contrôle recherche, tri, pagination, ouverture/fermeture des détails, retour du focus et mobile, via le serveur local de test. Aucune migration nécessaire.

## Fiches de transformation initiales

`scripts/setup_butchery_recipes.php` initialise de manière additive et transactionnelle quatre fiches : DEC-PORC, DEC-BOEUF, DEC-VOLAILLE et PREP-POISSON. Chacune utilise 100 % de sa matière première en entrée ; il ne s’agit pas d’un rendement garanti. Les articles MORCEAUX-PORC, MORCEAUX-BOEUF, MORCEAUX-VOLAILLE et POISSON-PREPARE distinguent les sorties. Aucun rendement, additif, coproduit supplémentaire ni durée de conservation n’est imposé. Le produit obtenu reste à choisir à la saisie des résultats.

Le script conserve les références existantes et ne modifie aucun stock. Les fiches initiales sont attribuées au premier administrateur actif ; l’exécution automatique et les codes créés sont journalisés comme initialisation CLI. Exécuter `php scripts/setup_butchery_recipes.php` sur une autre installation après les migrations boucherie existantes. Une relance n’ajoute pas de doublons.

La planification filtre les lots par matière de la fiche. Le serveur vérifie aussi la correspondance lors de la planification, de la saisie des résultats et de la validation finale. Le contrôle autorise les matières explicitement déclarées dans les composants de la version, sans modifier les versions historiques.

Tests : initialisation répétée, `tests/ButcheryRecipeCompatibilityTest.php` et `scripts/test_butchery.php`, exclusivement avec `DAGRIL_DB_DATABASE` pointant sur une base `_test` ou `_testing`.

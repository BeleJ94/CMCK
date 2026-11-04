# Issues de meunerie et aliment bétail

Une production farine répartit 100 % de la matière chargée entre la farine (environ 80 %) et les **issues** (environ 20 % : son, remoulage, criblures…). Les issues ne sont pas une perte : elles sont **entièrement récupérées** et servent de **matière première** à la fabrication d’aliment bétail. Elles ne sont pas vendues en vrac.

## Circuit unique

1. **Production farine** : la validation du lot crée la farine en vrac et le stock d’issues par type (inchangé).
2. **Issues de meunerie** (`/waste`) : consultation des stocks, filtres, exports, mise en tampon.
3. **Aliment bétail** (`/pelletization`) : transfert des issues vers PELL (BTI), achat d’additifs, recettes versionnées, ordre de fabrication, saisie des résultats, validation, ensachage.

La transformation des issues ne passe plus que par un ordre de fabrication d’aliment bétail, avec recette, additifs, coût de revient et validation par une autre personne.

## Ce qui est fermé

- `GET /waste/process` redirige vers Aliment bétail ; `POST /waste/process` est refusé (HTTP 410 en AJAX).
- `POST /waste/sell` est refusé : plus de vente en vrac des issues. Le bouton et le formulaire sont retirés.
- Les méthodes `Waste::processWaste()` et `Waste::sell()` sont supprimées.
- Les raccourcis terrain « Encoder déchets » deviennent « Fabriquer aliment bétail » (`/pelletization?new=1`, qui ouvre directement le formulaire de nouvelle fabrication).

L’historique des anciens traitements directs (`waste_processings`) et des ventes (`waste_sales`) est conservé et reste consultable (`/waste/history`, rapports, traçabilité).

## Vocabulaire

Les libellés visibles « déchets » deviennent « issues », et « pelletisation » devient « aliment bétail ». Les noms techniques (tables `waste_*`/`pellet_*`, routes, permissions `waste` et `pelletization`) ne changent pas : les droits existants restent valables.

## Contrôle à la production farine

Les issues étant entièrement récupérées, la règle est **farine + issues = quantité chargée**. La saisie des résultats et le bilan du lot affichent « Farine X % + issues Y % = Z % du chargé ». Tout écart au-delà de la tolérance du site doit être justifié et exige une approbation complémentaire par un autre utilisateur (mécanisme existant, migration 018).

## Forme du produit (granulé ou farine)

Chaque version de recette précise la forme de l’aliment obtenu : **Granulé** (par défaut, toutes les recettes existantes) ou **Farine** (non granulé). Le circuit reste identique (OF, résultats, validation, ensachage). La forme apparaît dans les recettes, dans le choix de recette d’un OF, dans la liste des OF et dans le rapport. Elle est figée par version : pour la changer, créer une nouvelle version. Avant la migration 051, seule la forme Granulé est acceptée.

## Bilan issues et aliment bétail

Rapports › Issues (`/reports/waste`) présente sur la période :

- le bilan des lots validés : grain chargé → farine + issues récupérées + écart non expliqué, en kg et en % du chargé ;
- le bilan des fabrications validées : issues transformées, additifs, aliment bétail produit, pertes de fabrication et coût moyen par kg ;
- le détail des ordres de fabrication (recette, forme, quantités, rendement, coût, validateur) ;
- les anciens traitements directs, en lecture seule.

Les issues transformées sur la période peuvent provenir de lots antérieurs. Les exports Excel, PDF et impression reprennent la même page.

## Déploiement

Appliquer, dans l’ordre :

1. `database/050_rename_waste_to_milling_byproducts.sql` : renommage des libellés produits (codes inchangés) ;
2. `database/051_add_feed_recipe_output_form.sql` : forme du produit sur les versions de recette (défaut Granulé).

L’application fonctionne avant la migration 051 (forme Granulé uniquement).

## Vérifications

- `scripts/test_business_rules.php` : transformation directe et vente en vrac absentes, rapport des issues sans nouveau traitement direct.
- `scripts/test_pelletization.php` : forme Granulé par défaut et forme inconnue refusée.
- `scripts/test_roof_production.php`, `scripts/test_cancellation_integration.php` : conformes.
- `tests/ProductionResultsIntegrationTest.php`, `tests/ProductionBalanceViewTest.php`, `tests/ProductionExportTest.php`, `tests/ReportExecutiveTest.php` : conformes.
- `scripts/test_ui_experience.php` : aucun nouvel échec.

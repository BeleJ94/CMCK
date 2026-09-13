# Détail et modification des campagnes

Le détail s’ouvre dans un panneau latéral droit : période, statut, surfaces, objectif, production validée, progression, parcelles et récoltes. Le formulaire de modification est accessible depuis « Modifier la campagne » pour les administrateurs disposant du droit agriculture/update.

Champs modifiables : nom, code, saison, dates, statut. La ferme reste le rattachement d’origine ; les surfaces et objectifs proviennent de la planification. Un motif est obligatoire. Le serveur contrôle les droits et le site, l’unicité du code, la saison, les dates et les statuts. Un changement de période ne peut pas exclure les travaux, récoltes ou imputations d’intrants déjà enregistrés et non supprimés. L’empreinte des valeurs d’origine protège les corrections concurrentes. Les anciennes et nouvelles valeurs sont enregistrées dans activity_logs dans la même transaction.

L’enregistrement utilise AJAX et une confirmation SweetAlert. Le détail et le tableau sont actualisés avec les filtres conservés. Si la sauvegarde réussit mais que l’actualisation échoue, le formulaire indique que la modification est enregistrée et bloque une nouvelle soumission. Aucune migration SQL nécessaire.

Validation sur base isolée : 14 contrôles métier, 6 contrôles d’agrégation, 115 contrôles d’interface et parcours navigateur (validation, confirmation annulée, mise à jour réelle sans navigation, filtres, mobile et CSRF).

```sh
DAGRIL_DB_DATABASE=dagril_works_verified_test php tests/CampaignUpdateIntegrationTest.php
php tests/CampaignAggregationTest.php
php scripts/test_ui_experience.php
# Avec le serveur local de tests déjà démarré et Playwright disponible :
DAGRIL_DB_DATABASE=dagril_works_verified_test node tests/browser/CampaignUpdateBrowserTest.cjs
```

# Modification des récoltes

L’action Modifier ouvre le formulaire latéral prérempli. Les utilisateurs disposant du droit agriculture/update sur la ferme peuvent corriger les récoltes non validées. Une récolte validée exige en plus le rôle administrateur, vérifié côté serveur. Les récoltes annulées ne sont pas modifiables.

Les poids, date de récolte, humidités et dates de séchage sont modifiables. La campagne/parcelle, le numéro, l’auteur et la validation restent conservés. Un motif est obligatoire ; les anciennes/nouvelles valeurs sont journalisées. Une empreinte des valeurs refuse une correction basée sur un formulaire obsolète. Le poids net et le rendement sont recalculés, les humidités bornées à 0–100 % et l’ordre des dates de séchage contrôlé.

Pour une récolte validée, la différence entre l’ancien et le nouveau poids net ajuste le stock physique restant dans la même transaction, sous verrou. Une réduction ne peut absorber des quantités déjà expédiées, réservées ou nécessaires à des BT en brouillon. Les transports existants et les réservations ne sont pas réécrits. Chaque différence non nulle produit un mouvement `reversal` signé référencé par l’entrée unique du journal d’activité. Une modification sans différence de poids ne produit pas de mouvement.

La soumission utilise le circuit AJAX et sa confirmation. Le formulaire de création retrouve ses champs vides et sa parcelle sélectionnable après fermeture d’une correction. Le résumé précise que les récoltes validées incluent l’ancienne valeur lors d’une correction administrative.

Aucune migration SQL nécessaire. Validation : 25 contrôles métier, parcours navigateur (préremplissage, confirmation annulée, correction AJAX persistée, mobile, retour en création), 115 contrôles d’interface et suite globale 18/18. Le test de rapports de la suite a été aligné sur le fuseau de l’application afin de fonctionner après minuit local.

```sh
DAGRIL_DB_DATABASE=dagril_works_verified_test php tests/HarvestUpdateIntegrationTest.php
DAGRIL_DB_DATABASE=dagril_works_verified_test node tests/browser/HarvestUpdateBrowserTest.cjs
```

Utiliser uniquement la base de test et le serveur local décrits dans travaux-agricoles-validation.md. Ces tests créent leurs données de test.

## Plusieurs récoltes pour une même parcelle de campagne

La migration `database/035_allow_multiple_harvests_per_plot.sql` remplace l’unicité de campaign_plot_id par un index ordinaire. La clé étrangère est conservée. Chaque récolte conserve son numéro unique, sa validation et son stock propre ; les campagnes et illustrations cumulent uniquement les récoltes validées. Les numéros utilisent un suffixe aléatoire de 12 caractères hexadécimaux pour réduire le risque de collision lors de créations rapprochées.

Migration appliquée à la base locale après sauvegarde du schéma et des récoltes et comparaison intégrale des lignes avant/après. Validation : 33 contrôles métier (dont 8 sur les récoltes multiples), 6 contrôles d’agrégation, 8 contrôles d’illustration, 115 contrôles d’interface, suite globale 18/18 et deux créations AJAX successives sur la même parcelle dans la base isolée.

```sh
DAGRIL_DB_DATABASE=dagril_works_verified_test php tests/MultipleHarvestsIntegrationTest.php
```

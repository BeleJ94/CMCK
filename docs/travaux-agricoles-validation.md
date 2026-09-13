# Travaux agricoles — mise à jour et validation

Les interventions réalisées sont rattachées à une parcelle planifiée. La saisie permet plusieurs travailleurs et matériels, un responsable terrain distinct de l’auteur, des tarifs explicites et des autres coûts justifiés.

Chaque intervention utilise une seule devise (USD ou CDF). USD est sélectionné par défaut, avec un taux fixe de 1. En CDF, un taux positif (CDF pour 1 USD, six décimales maximum enregistrées) est obligatoire ; l’équivalent USD est calculé par division du montant CDF par le taux. Le taux est conservé et historisé à chaque correction. Les tarifs sont déclarés dans la devise sélectionnée. Les totaux restent séparés par devise. Les anciens montants sans devise et les tarifs historiques sont considérés en USD. Les intrants sont affichés en USD, séparément des travaux sur l’ensemble de la campagne.

Les campagnes clôturées ou annulées refusent les nouvelles saisies et corrections. Une annulation motivée reste possible avec la permission de suppression, conserve toutes les lignes et les exclut des totaux. Les corrections exigent un motif et une version à jour. La clé de soumission évite un second enregistrement lorsque la même requête est renvoyée.

## Déploiement

Appliquer une fois `database/033_improve_agricultural_works.sql` avant d’utiliser les nouvelles vues. La migration a été appliquée à la base locale le 12 septembre 2026 après sauvegarde des trois tables concernées et tests sur copies isolées. La migration suivante `database/034_agricultural_work_exchange_rates.sql` attribue USD et le taux 1 aux anciens travaux sans devise. Elle conserve les CDF existants avec un taux à renseigner par correction ; aucun taux historique n’est inventé. Une sauvegarde des travaux est effectuée avant application locale.

SweetAlert2 11.26.25 est livré localement dans `public/assets/vendor/sweetalert2` avec sa licence MIT.

## Validation effectuée

- 34 contrôles MySQL propres aux travaux : calculs, USD/CDF, droits, site, ressources actives, dates, doublons, transactions, versions concurrentes, historique et annulations.
- Parcours Chrome sur une copie isolée : vraie création AJAX, confirmation annulée, correction, annulation avec motif obligatoire, détail, total et filtres conservés, CSRF invalide refusé, affichage mobile et fermeture au clavier. Aucun rechargement pendant les soumissions.
- 115 contrôles d’interface existants réussis.
- 18/18 scénarios de la suite globale réussis sur une copie avec clés étrangères et déclencheurs SQL.
- Contrôle de syntaxe PHP/JavaScript et de la cohérence du diff.

Le test global a conduit à adapter des fixtures anciennes (créateur non administrateur pour tester la séparation des tâches, parcours de transport agricole, assertions de menus et isolation des références). Il a aussi révélé un défaut réel dans `Waste::processWaste` : MySQL réévaluait le stock après sa décrémentation pour décider de son statut. Le statut est désormais calculé avant la décrémentation, pour conserver un reliquat disponible.

## Rejouer les tests

Utiliser exclusivement une copie MySQL dont le nom finit par `_test` ou `_testing`, contenant données de référence, rôles, permissions, clés étrangères et triggers, avec les migrations 033 et 034 appliquées. Ne pas utiliser `CREATE TABLE LIKE` seul pour cette copie : il ne reproduit pas les clés étrangères ni les triggers.

```sh
DAGRIL_DB_DATABASE=dagril_works_verified_test php tests/AgriculturalWorkIntegrationTest.php
DAGRIL_DB_DATABASE=dagril_works_verified_test php scripts/run_dagril_integration_suite.php
php scripts/test_ui_experience.php
```

Pour le navigateur, démarrer ce serveur uniquement en local. Le routeur authentifie un administrateur de la base de test ; il refuse une base sans suffixe de test et les clients non locaux.

```sh
DAGRIL_DB_DATABASE=dagril_works_verified_test php -S 127.0.0.1:8099 -t public tests/integration/WorksBrowserRouter.php
```

Dans un autre terminal, avec Playwright disponible (ou `DAGRIL_PLAYWRIGHT_MODULE` indiquant son chemin) :

```sh
DAGRIL_DB_DATABASE=dagril_works_verified_test node tests/browser/AgriculturalWorksBrowserTest.cjs
DAGRIL_DB_DATABASE=dagril_works_verified_test node tests/browser/AgriculturalWorkCurrencyBrowserTest.cjs
```

Le test navigateur utilise les fixtures « Test travaux » créées par le test MySQL. `DAGRIL_CHROME` permet de choisir l’exécutable Chrome. Les captures sont écrites dans le dossier temporaire du système. Les tests métiers modifient la copie de test ; ne jamais les exécuter sur la base de travail.

## Correction de la saisie des dates

Le champ Date et heure recalcule ses limites après chaque changement de contexte et à chaque ouverture du formulaire. L’heure proposée et la validation utilisent le fuseau de l’application (Africa/Lubumbashi), même si le navigateur utilise un autre fuseau. Les erreurs affichées sous le champ distinguent une saisie incomplète, une date hors campagne, une heure future et une campagne non commencée. Le serveur fournit également les dates autorisées dans ses messages.

Validation complémentaire : 39 contrôles métier, 115 contrôles d’interface et parcours AJAX complet réussis. `tests/browser/AgriculturalWorkDateBrowserTest.cjs` couvre les périodes passées/courantes/futures, le fuseau différent du navigateur, la remise à zéro des contraintes et les messages sur mobile ; il utilise des périodes fixes injectées uniquement dans la réponse de test.

# Référentiel des groupes porcins

Les groupes d’âge des porcs ne sont plus codés en dur. Ils se gèrent dans **Élevage › Porcs › Groupes d’âge** (`/livestock/pigs/groups`). La migration reprend les règles historiques à l’identique :

| Groupe | Libellé | Âge d’entrée | Capacités |
|---|---|---|---|
| A | Porcelets | 0 mois | Naissances |
| B | Porcs en croissance | 2 mois | — |
| C | Porcs adultes | 3 mois | Reproduction, abattage |

## Règles

- **Tranches d’âge.** Chaque groupe déclare seulement son âge d’entrée, en mois calendaires (un porc né le 31 décembre a 2 mois le 28 février). Un groupe s’arrête là où commence le suivant. Le dernier groupe n’a pas de limite d’âge. Il ne peut donc y avoir ni trou ni chevauchement.
- **Capacités.** Le code ne teste plus les lettres A, B ou C, mais trois capacités :
  - *Naissances* : groupe dans lequel une naissance à la ferme peut être enregistrée.
  - *Reproduction* : sélection des reproducteurs, réorientation, et groupe exigé pour le lot des mères.
  - *Abattage* : lots abattables à la ferme, y compris le contrôle de l’âge à la date d’abattage.
- **Validation terrain inchangée.** Modifier le référentiel change le groupe *attendu* des lots, jamais leur groupe *confirmé*. Les lots concernés apparaissent dans « Passages à vérifier » et sont reclassés par la validation terrain habituelle. Avant d’enregistrer, l’éditeur affiche le nombre de lots et de porcs concernés.
- **Contrôles de cohérence**, vérifiés côté serveur et prévisualisés à la saisie :
  - un groupe actif commence à 0 mois et accueille les naissances ;
  - deux groupes actifs ne commencent jamais au même âge ;
  - au moins un groupe actif permet la reproduction, et au moins un permet l’abattage ;
  - 12 groupes actifs au plus, âge d’entrée entre 0 et 120 mois.
- **Codes.**
  - À la création, le code est proposé automatiquement : la lettre qui suit la plus haute déjà utilisée, groupes désactivés compris (A, B, C → D). Une fois Z atteint, les lettres libres sont réutilisées, puis viennent G1, G2…
  - La proposition reste modifiable : 1 à 10 caractères, lettres, chiffres ou tirets. Le bouton « Revenir à D » restaure la proposition.
  - Si le champ est laissé vide, le serveur attribue le code au moment de l’enregistrement, sous verrou : deux créations simultanées ne peuvent pas recevoir le même code.
  - Le code ne change plus après la création, car il identifie le groupe dans l’historique.
- **Désactivation et suppression.**
  - Un groupe qui a servi ne peut plus être supprimé. Il peut seulement être désactivé : il ne classe alors plus aucun lot, et ses lots restants sont signalés pour être reclassés.
  - Un groupe jamais utilisé peut être supprimé.
  - Les boutons qui rendraient le référentiel incohérent sont désactivés, avec la raison affichée.
- **Droits.**
  - Toute personne qui a le droit de lecture sur l’élevage consulte le référentiel.
  - La modification exige `livestock / administer` sur chacune des deux fermes (MUTALA et DIKAPA), car les règles s’appliquent aux deux.
  - Les modifications concurrentes sont refusées grâce à une version optimiste ; il faut alors actualiser la page.
- **Audit.** Chaque création, modification, désactivation, réactivation et suppression est inscrite dans `activity_logs` (`entity_type = livestock_pig_groups`), avec l’impact calculé. Les huit dernières s’affichent sur la page.

Tant que la migration n’est pas appliquée, l’application conserve les règles historiques et la page s’affiche en lecture seule, avec un message d’activation.

## Déploiement

Appliquer une fois `database/041_add_pig_group_referential.sql`, après une sauvegarde de `livestock_pig_profiles` et de `livestock_pig_events`. La migration :

- crée `livestock_pig_groups` et y insère A, B et C ;
- convertit les colonnes `ENUM('A','B','C')` des profils et des événements en codes reliés au référentiel par clé étrangère.

Les données existantes sont conservées.

## Vérifications

- `php tests/PigGroupReferentialTest.php` : 59 contrôles sur SQLite, sans base MySQL. Ils couvrent :
  - le classement avec un référentiel personnalisé, les contrôles de cohérence et la proposition automatique des codes ;
  - les droits, les versions périmées et le code non modifiable ;
  - l’impact sur les lots, les naissances, l’abattage et la reproduction pilotés par les capacités ;
  - la désactivation, la suppression, l’audit et l’annulation complète en cas d’erreur.
- `php tests/PigLifecycleTest.php` : les 48 contrôles historiques passent sans le référentiel (repli sur A, B, C).
- `php tests/PigExportTest.php` : filtres d’export issus du référentiel.
- Sur une base jetable créée par `scripts/setup_porcine_flow_test.php`, qui applique désormais la migration 041 :
  - `tests/PorcineFlowIntegrationTest.php` (46 contrôles) ;
  - `tests/PigLifecycleIntegrationTest.php` (12 contrôles).
- `tests/browser/PigGroupReferentialBrowserTest.cjs` : parcours complet dans Chrome.
  - Il couvre le code proposé, modifié puis rétabli, l’aperçu en direct, la détection d’un chevauchement, la création AJAX confirmée, la frise actualisée, la désactivation, la suppression, l’historique, les filtres de la page des effectifs et l’affichage mobile à 390 et 320 px.
  - Le test supprime le groupe qu’il crée, ce qui permet de le relancer.
  - Les tests existants `PigLifecycleBrowserTest`, `SlaughterLotPickerBrowserTest` et `FarmKpiDetailsBrowserTest` passent aussi.

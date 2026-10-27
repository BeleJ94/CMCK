# Pisciculture par étang et double pesée ferme / boucherie

## Pisciculture

Écrans : **Élevage › Poissons › Étangs et lots** (`/livestock/fish`) et **Pêches et envois** (`/livestock/fish/harvests`).

- **Étangs et parcelles.**
  - Un étang (créé dans « Bâtiments et étangs ») comporte une ou plusieurs parcelles : P1, P2…, avec un code attribué automatiquement.
  - La surface totale des parcelles actives ne peut pas dépasser celle de l’étang.
  - Les étangs existants ont reçu une parcelle P1 couvrant toute leur surface, et leurs lots de poissons y ont été rattachés.
- **Mise en charge.**
  - Une parcelle libre reçoit un lot d’alevins à une date : espèce, nombre, poids facultatif.
  - Une parcelle n’accueille qu’un lot à la fois, jusqu’à sa pêche finale.
  - Une parcelle en élevage ne peut pas être désactivée.
- **Pêches.**
  - Les survivants ne sont connus qu’à la pêche : on compte et on pèse les poissons pêchés.
  - La pesée est saisie en poids net, ou en brut moins tare (glace, caisses), avec la nature de la tare.
  - La DLC est proposée selon la durée de conservation de l’article « Poisson frais ».
  - Une pêche partielle laisse le lot en élevage.
  - La **pêche finale** vide la parcelle : à sa validation, les poissons non pêchés sont comptés comme morts, le lot est clôturé et la parcelle redevient libre.
- **Validation.**
  - Elle est faite par un autre utilisateur autorisé, sauf droit d’auto-validation.
  - Elle retire les poissons du lot et crée le stock pesé à la ferme.
  - Une pêche en attente peut être annulée, avec un motif.
- **Bilan des lots terminés** : alevins mis, pêchés, morts, survie (%), kg récoltés, kg/m² et durée.
- **Envois.** Ils reprennent le circuit des porcs : préparation, approbation, expédition, puis bon de transfert BTR-POIS.
- **Parcours générique.** Une fois la migration appliquée, la page de l’espèce poisson redirige vers ces écrans, et l’ancien parcours générique refuse de créer des lots, des pêches, des conversions ou des transferts de poisson.

## Double pesée ferme / boucherie

Elle s’applique au porc et au poisson. Les bovins ne sont pas concernés pour l’instant : le sous-module est en pause.

- **Ferme.**
  - Le poids est saisi à l’abattage (porc) ou à la pêche (poisson), en net ou en brut moins tare.
  - Le BTR porte ce poids.
- **Arrivée à la boucherie.**
  - Le bouton **« Peser à l’arrivée »** remplace l’ancien enregistrement d’arrivée.
  - Le réceptionnaire saisit le poids reçu, en net ou en brut moins tare.
  - L’écran affiche en direct l’écart avec le poids de la ferme (perte ou excédent, en kg et en %) et sa position par rapport à la tolérance.
- **Stock de la boucherie.** Le lot de chambre froide est créé avec le **poids reçu**, et non plus avec le poids envoyé. L’écart reste tracé sur la réception, jusqu’au lot d’origine.
- **Écart hors tolérance.**
  - Le motif est obligatoire dès la pesée.
  - Au contrôle sanitaire, l’entrée au froid n’est possible qu’après validation explicite de l’écart.
  - Si un rôle est paramétré, seuls ce rôle et les administrateurs peuvent valider ; les autres voient la réception verrouillée.
  - Un écart supérieur à 50 % est refusé : la pesée est à vérifier.
- **Paramétrage**, dans **Boucherie › Écarts de pesée** (`/butchery/weighing-gaps`).
  - On y règle la tolérance par espèce (0 à 50 %) et le rôle chargé de valider un dépassement (par défaut, tout validateur de la boucherie).
  - Sans tolérance paramétrée, tout écart doit être justifié et validé.
  - Le paramétrage est réservé à `butchery / administer` et inscrit dans l’audit.
- **Rapport**, sur la même page.
  - Filtres : période, espèce, état (dans la tolérance, hors tolérance, non pesé).
  - Indicateurs : poids envoyé, poids reçu, écart total, dépassements.
  - Synthèse par espèce, détail par réception (brut, tare, motif, auteurs), export Excel.
  - Les réceptions antérieures à la migration apparaissent comme « non pesées à l’arrivée ».

## Déploiement

Appliquer une fois `database/042_add_fish_ponds_and_transit_weighing.sql`, après une sauvegarde de `livestock_batches`, `fish_catches`, `livestock_conversions` et `butchery_receipts`. La migration :

- crée les parcelles et rattache les lots de poissons existants à la parcelle P1 de leur étang ;
- complète les pêches (article, DLC, pêche finale, nature de la tare) et les réceptions (poids envoyé, brut, tare, écart, tolérance, motif, validation) ;
- crée la table des tolérances, vide au départ.

Ensuite :

- **Paramétrer les tolérances** du porc et du poisson dans « Écarts de pesée ». Sinon, tout écart demande une justification.
- **Préparer la réception.** Elle exige désormais une pesée : les envois déjà en route au moment du déploiement seront pesés à leur arrivée.

## Vérifications

- `php tests/TransitWeighingTest.php` : 17 contrôles sans base de données, sur la saisie net ou brut − tare, l’écart, la tolérance et les libellés.
- Sur une base jetable créée par `scripts/setup_porcine_flow_test.php`, qui applique désormais la migration 042 :
  - `tests/FishFlowIntegrationTest.php` : 44 contrôles MySQL, du parcours complet jusqu’au rapport et à l’export.
  - `tests/PorcineFlowIntegrationTest.php` et `tests/PigLifecycleIntegrationTest.php`, adaptés à la pesée d’arrivée.
- `tests/browser/FishFlowBrowserTest.cjs` : parcours Chrome complet, rejouable.
  - Il enchaîne mise en charge, pêche finale, validation, envoi, pesée d’arrivée avec écart, contrôle et rapport.
  - Il vérifie aussi l’absence de débordement à 390 et 320 px.
  - La fixture `tests/integration/FishBrowserFixture.php` crée un étang dédié.
- `scripts/test_livestock.php` : vérifie désormais que le parcours générique refuse les lots de poissons lorsque la migration 042 est appliquée.

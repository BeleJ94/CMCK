# Parcours bovin : du fournisseur à la boucherie

Écrans : **Élevage › Bœufs › Réceptions et lots** (`/livestock/cattle`) et **Abattages et envois** (`/livestock/cattle/slaughters`).

## Étapes

1. **Réception fournisseur.**
   - Les bœufs livrés par un fournisseur (module Fournisseurs) arrivent dans un bâtiment d’élevage de MUTALA ou DIKAPA.
   - La saisie comporte : nombre de bovins, date et heure, bon de livraison et observations (facultatifs).
   - Le poids vif est facultatif : en net, ou en brut moins tare sur le pont-bascule (camion, bétaillère). Il donne le poids vif moyen par tête.
   - La capacité du bâtiment est contrôlée.
   - Chaque réception crée un lot.
2. **Abattage à la ferme.**
   - On choisit le lot, le nombre de bovins et la date. Les carcasses sont pesées, en net ou en brut moins tare (crochets, bacs).
   - La DLC est proposée selon l’article « Carcasse de bœuf ».
   - Les bovins déjà engagés dans un abattage en attente ne sont pas disponibles.
   - L’abattage ne peut pas précéder l’arrivée du lot.
   - Un autre utilisateur autorisé valide l’abattage, sauf droit d’auto-validation. La validation retire les bovins du lot et crée le stock de carcasses. Un abattage en attente peut être annulé, avec un motif.
3. **Envoi.** On prépare, approuve puis expédie l’envoi, avec un bon de transfert BTR-BOEUF consultable en aperçu.
4. **Boucherie.**
   - Les carcasses sont pesées à l’arrivée. L’écart avec la ferme est contrôlé selon la tolérance « Bovin » de Boucherie › Écarts de pesée.
   - Viennent ensuite le contrôle sanitaire et l’entrée en chambre froide au poids reçu (lot CARC-BOEUF), puis la transformation (recette « Découpe de bœuf ») et les comptoirs, comme pour les porcs.

## Suivi

- **Indicateurs des lots** : bovins vivants, réceptions du mois, disponibles à l’abattage, abattages à valider.
- **Fiche d’un lot**, ouverte par le menu « ⋯ » :
  - la réception : fournisseur, bon de livraison, poids vif brut, tare et net, auteur ;
  - les effectifs (reçus, vivants, abattus, autres sorties) et les abattages ;
  - les kg de carcasses et le **rendement carcasse**, c’est-à-dire les kg de carcasses rapportés au poids vif des bovins abattus.
- **Abattages et envois** : les deux poids (ferme et boucherie), l’écart de transport et l’aperçu du BTR.
- **Lots antérieurs** : un lot créé avant ce parcours, sans réception fournisseur, apparaît comme « Lot antérieur ». Ses sorties passées, par exemple des transferts vivants, figurent dans « Autres sorties ».
- **Parcours générique** : une fois la migration appliquée, la page de l’espèce Bovin redirige vers ces écrans, et l’ancien parcours générique refuse les lots, conversions et transferts de bovins.
- **Suivi quotidien inchangé** : l’alimentation et les pesées passent toujours par « Suivi quotidien ».

## Déploiement

Appliquer une fois `database/043_add_cattle_flow.sql`. La migration crée `cattle_receipts` et `cattle_slaughter_details`, sans modifier les tables existantes.

Ensuite :

- paramétrer la tolérance « Bovin » dans Boucherie › Écarts de pesée ;
- vérifier que les fournisseurs de bovins sont actifs.

## Vérifications

- `tests/CattleFlowIntegrationTest.php` : 27 contrôles MySQL sur une base jetable créée par `scripts/setup_porcine_flow_test.php`. Ils couvrent :
  - la réception (capacité, fournisseur, date, poids vif facultatif) ;
  - le refus du parcours générique ;
  - l’abattage et les animaux disponibles ;
  - la validation, le rendement carcasse et l’annulation ;
  - l’envoi BTR-BOEUF, la pesée d’arrivée avec tolérance et la chambre froide au poids reçu ;
  - le rapport des écarts.
- `tests/browser/CattleFlowBrowserTest.cjs` : parcours Chrome complet, de la réception fournisseur à la fiche du lot, avec l’affichage mobile à 390 et 320 px. La fixture est `tests/integration/CattleBrowserFixture.php`.
- `scripts/test_livestock.php` : il crée désormais son lot bovin par une réception fournisseur, et teste les conversions génériques sur des volailles.

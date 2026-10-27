# Porcs : carcasses et envois

Écran : **Élevage › Porcs › Carcasses et envois** (`/livestock/pigs/shipments`). Il suit la même présentation que la page bovine « Abattages et envois ».

## Indicateurs

- **Carcasses à envoyer** : kilos disponibles à la ferme, hors réservations et hors DLC dépassée, avec le nombre de stocks et les kilos réservés.
- **Envois à traiter** : envois à approuver ou à expédier.
- **En route** : envois partis, en attente de la pesée d’arrivée à la boucherie.
- **Écart ferme → boucherie** : perte de poids cumulée des envois pesés à l’arrivée, en kilos et en pourcentage.

Un bandeau signale les stocks dont la DLC est dépassée : ils ne peuvent plus être envoyés.

## Carcasses à la ferme

Un stock par abattage validé, avec :

- la date et le numéro de l’abattage ;
- le lot, son bâtiment et sa ferme ;
- les porcs abattus ;
- le stock pesé et la part réservée ;
- les kilos à envoyer ;
- la DLC, avec le nombre de jours restants ;
- l’état : disponible, réservé ou DLC dépassée.

Actions :

- **Envoyer** ouvre « Préparer un envoi » avec le stock déjà choisi.
- **Consulter le stock** (menu « ⋯ ») ouvre une fiche avec :
  - l’origine ;
  - la pesée des carcasses et le poids moyen par carcasse ;
  - le stock et la DLC ;
  - la liste des envois de ce stock.

## Envois à la boucherie

Chaque envoi indique :

- son bon de transfert (BTR-PORC) ;
- l’origine et le transport ;
- le poids pesé à la ferme et le poids pesé à la boucherie ;
- l’écart de poids et son respect de la tolérance ;
- son état : à approuver, à expédier, en route, reçu ou annulé.

Actions :

- **Approuver**, qui réserve les carcasses, puis **Expédier**, directement sur la ligne.
- **Consulter l’envoi** (menu « ⋯ ») : marchandise, double pesée (ferme puis boucherie, tare, écart, tolérance, motif déclaré), et suivi (préparé, approuvé, expédié par, réception et pesée d’arrivée). Pour un envoi en route, un lien mène au suivi de la réception à la boucherie.
- **Bon de transfert** : aperçu imprimable, ouverture dans un nouvel onglet et téléchargement en PDF. Échap ferme l’aperçu, même quand le document a le focus.
- **Annuler l’envoi** : un motif est obligatoire. Un envoi à approuver ou à expédier libère alors sa réservation.

Chaque tableau a sa recherche, son tri par colonne et sa pagination.

## Vérifications

- `tests/browser/PigShipmentsBrowserTest.cjs` : parcours Chrome complet sur une base jetable, avec la fixture `tests/integration/PigShipmentsBrowserFixture.php` (un abattage validé de 150 kg). Il couvre :
  - le stock et l’envoi préparé depuis la ligne ;
  - le contrôle de quantité, l’approbation et la réservation ;
  - le bon de transfert (impression, PDF, Échap) ;
  - les fiches et l’annulation motivée ;
  - l’affichage mobile à 390 et 320 px.

  Ce test remplace `ShipmentTableBrowserTest.cjs` et `ShipmentBtrBrowserTest.cjs`, écrits pour l’ancien tableau.
- `tests/browser/PorcineFlowBrowserTest.cjs` passe par cette page : préparation, approbation et expédition d’un envoi, bon de transfert à chaque étape.

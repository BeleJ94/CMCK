# Alimentations des machines

La page `/machine-feeds` propose un historique filtrable et paginé, un formulaire latéral et la confirmation AJAX commune à l’application. L’ancien lien `/machine-feeds/create` ouvre ce même formulaire.

En vue consolidée, la création utilise le site de la machine choisie, après vérification de l’accès au site et du droit de création. Lorsqu’un site précis est sélectionné, une machine d’un autre site reste interdite. Le silo source peut appartenir à un autre site accessible, conformément au circuit silo → minoterie.

Le formulaire affiche le produit, le stock disponible, le site destinataire et le solde prévisionnel. Les erreurs serveur restent visibles dans le formulaire sans perdre la saisie. Les quantités sont positives, bornées et arrondies au kilogramme avec trois décimales ; les dates doivent être valides et chronologiques. Le stock est revérifié sous verrou lors de l’écriture. La quantité autorisée reste distincte de la quantité chargée : seule cette dernière diminue le stock.

Une transaction crée la sortie silo, le BSS validé, l’alimentation et le lot en production. Aucune migration n’est nécessaire.

## Vérifications

- `tests/MachineFeedIntegrationTest.php` : 14 contrôles, création consolidée, déduction exacte, BSS/lot et refus sans effet partiel.
- `tests/browser/MachineFeedBrowserTest.cjs` : filtres, panneau latéral, contexte, validations, annulation de confirmation, erreur serveur avec saisie conservée, création AJAX, formulaire réinitialisé, mobile 390/320 px, ancien lien de création.
- `scripts/run_dagril_integration_suite.php` : 18 scénarios réussis sur `dagril_works_verified_test`.
- `scripts/test_ui_experience.php` : 115 vérifications réussies.

Le scénario d’annulation prépare désormais sa propre distribution pour rester répétable après les exécutions précédentes. Les tests d’écriture utilisent exclusivement la base de test.

Le bouton « Consulter » ouvre un panneau latéral de lecture avec parcours silo/machine, quantités, BSS, lot, dates, agent, site et observation. Les données proviennent de la liste déjà filtrée par les droits serveur. La liste et ses filtres restent en place ; Échap et les boutons ferment le panneau et rendent le focus au lien d’origine. Le lien conserve son adresse pour une ouverture volontaire dans un nouvel onglet. Vérification : `tests/browser/MachineFeedDetailBrowserTest.cjs` (ordinateur, mobile 390/320 px, absence de navigation, focus et filtres conservés).

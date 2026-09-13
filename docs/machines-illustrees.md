# Machines illustrées

La page `/machines` présente neuf cartes par page, avec illustration SVG différente selon le type, nom, site, statut, quantités alimentées, production validée et nombre d’opérations ouvertes. Le statut actif n’indique pas un fonctionnement en temps réel. Les capacités inconnues restent nulles et sont affichées « Non renseignée ».

Les filtres recherche, site, type et état sont conservés par périmètre dans la session du navigateur. La période (mois courant, année courante ou historique complet) se recharge en AJAX. Les chiffres concernent les opérations démarrées pendant cette période ; les opérations ouvertes sont comptées sur tout l’historique.

Le détail latéral affiche la capacité, le rendement, les quantités et les dix dernières opérations de la période. Création et modification utilisent un formulaire latéral et la confirmation AJAX commune. En vue consolidée, un site doit être choisi à la création ; le site reste immuable à la modification. Les écritures sont contrôlées par site et permission, puis enregistrées dans une transaction avec leur audit.

Le calcul exclut les alimentations et traitements annulés ou supprimés. La production et le rendement ne prennent en compte que les résultats validés. Le rendement global est le rapport entre la production totale et les quantités réellement chargées des opérations validées ; il ne s’agit pas d’une moyenne des pourcentages des lots. Les traitements liés à une machine de déchets sont inclus depuis `waste_processings`.

Vérifications sur `dagril_works_verified_test` :
- `tests/MachinePerformanceTest.php` : huit contrôles, fixtures annulées par rollback.
- `tests/browser/MachinesBrowserTest.cjs` : illustrations, filtres, période AJAX, détails, création, modification, activation, annulation de confirmation, conservation des filtres, mobile 390/320 px, aucune erreur JavaScript.
- Suite globale : 18/18 scénarios réussis.
- Contrôles d’interface : 115 vérifications réussies.

Aucune migration ni modification des données de production n’est nécessaire.

Correction du suivi des opérations anciennes encore ouvertes : la page ouvre désormais tout l’historique par défaut. Les cartes précisent la période des quantités et indiquent le poids chargé dans les opérations ouvertes. Le panneau commence par toutes les opérations en cours, indépendamment de la période et sans limite aux dix dernières opérations ; l’historique de période reste distinct. Régression vérifiée par `MachinePerformanceTest.php` (période sans alimentation avec opération ouverte et quantité conservées) et `tests/browser/MachineOpenOperationsBrowserTest.cjs`.

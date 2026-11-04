# Sorties de stock par quantité globale

Pour envoyer une quantité, l’utilisateur ne choisit plus un lot : il voit **le stock global d’un produit sur un site** et saisit **seulement la quantité**. Le système répartit automatiquement la quantité sur les lots et enregistre le détail lot par lot pour la traçabilité et les annulations. Il n’y a pas de choix manuel de lot.

## Règle de prélèvement

- **Lots les plus anciens d’abord** (FIFO) : farine, issues, grains, produits finis.
- **DLC la plus proche d’abord** (FEFO) : boucherie, en excluant les lots périmés ou bloqués.

Le service commun `app/services/StockAllocationService.php` reçoit les lots déjà triés et verrouillés (`SELECT … FOR UPDATE`) par le module, dans sa transaction, et renvoie la part prélevée sur chaque lot. Il refuse une quantité nulle ou supérieure au total disponible, avec un message indiquant le total. Le calcul est refait sous verrou à l’enregistrement : deux saisies simultanées ne peuvent pas consommer le même kilo.

L’alimentation silo → machine est déjà globale (stock du silo) et reste inchangée. L’élevage n’est pas concerné : un lot d’animaux est une identité sanitaire.

## Avancement

| Module | État |
|---|---|
| Emballage de la farine en vrac | **Fait** |
| Transferts inter-sites | **Fait** |
| Livraisons clients (commandes) | **Déjà global** : quantités par produit, lots réservés à la commande |
| Sorties sans commande | **Fait** |
| Transport ferme → silo | **Fait** |
| Issues → aliment bétail | **Fait** |
| Boucherie (DLC) | **Fait** |

## Emballage de la farine en vrac

Écran : Conditionnement (`/packaging`).

- L’onglet « Farine disponible » liste le stock en vrac **par produit et par site** : nombre de lots, date du plus ancien lot, quantité disponible.
- Le formulaire propose « Farine en vrac » (produit · site — kg disponibles), puis le format de sac et le nombre de sacs.
- La farine est prélevée sur les lots validés les plus anciens (date de fin de production). Un emballage peut couvrir plusieurs lots : 5 sacs de 10 kg avec 30 kg sur le lot A et 40 kg sur le lot B prélèvent 30 kg de A puis 20 kg de B.
- Chaque lot prélevé crée une ligne `packaging_lines` et un mouvement de vrac. Le journal d’audit liste les lots prélevés.
- L’historique affiche tous les lots d’un emballage avec leur quantité.
- `packaging.production_batch_id` reste renseigné avec le premier lot prélevé, pour les écrans historiques.
- **Annulation d’un emballage** : chaque lot retrouve exactement sa quantité.
- **Annulation d’une production** : bloquée dès qu’un emballage actif a prélevé sa farine, y compris via une ligne.
- **Traçabilité** : un emballage apparaît sous chaque lot dont il a prélevé de la farine.

Les règles de sacs vides (atelier), de compatibilité format/produit et d’affectation aux commandes clients sont inchangées.

### Déploiement

Appliquer `database/052_add_packaging_lines.sql`. La migration crée `packaging_lines` et reprend chaque ancien emballage comme une ligne sur son lot unique. Avant la migration, l’historique reste consultable mais un nouvel emballage est refusé avec un message demandant la migration.

### Vérifications

- `tests/PackagingGlobalStockIntegrationTest.php` (16 contrôles) : répartition pure, stock global, prélèvement sur deux lots, statut « depleted », mouvements par lot, refus sans effet partiel, produit invalide, blocage de l’annulation de production pour un lot prélevé par ligne, annulation rendant à chaque lot sa quantité. Produit de test isolé, données supprimées en fin de test.
- `scripts/test_business_rules.php`, `scripts/test_empty_packaging.php`, `scripts/test_cancellation_integration.php`, `scripts/test_traceability.php`, `scripts/test_roof_production.php` : conformes. Les deux scripts d’emballage rendent leur lot de test prioritaire, car les bases de test contiennent des lots réels plus anciens.

## Transferts inter-sites

Écran : Transferts (`/transfers`), formulaire « Nouvelle demande ».

- Après le site de départ, on choisit le **produit et le format** (« Farine de maïs · Sac 50 kg — 120 sacs disponibles »), puis le nombre de sacs. Les disponibilités affichées sont le stock libre (réservations déduites) de tous les lots du site.
- À la création, les sacs sont répartis sur les lots libres les plus anciens : une ligne `stock_transfer_items` par lot, chacune avec son lot source et son poids. Aucune réservation n’est faite à ce stade.
- **À la validation (ou à la réservation)** : si un lot n’a plus assez de sacs libres, par exemple parce qu’il a été réservé ou expédié entre-temps, la quantité du produit est répartie à nouveau sur les lots encore libres, puis réservée lot par lot. Si le stock global ne suffit plus, la validation est refusée sans effet.
- Le formulaire montre, sous le stock disponible, **les lots qui seront prélevés** et ce qui restera sur chacun. Cette répartition est indicative : elle est recalculée à la validation.
- Expédition, réception, non-conformités, retours et clôture fonctionnent ligne par ligne, comme avant. Les écrans d’expédition et de réception regroupent déjà les lignes par produit.
- Les envois générés par les commandes clients gardent leurs lots déjà affectés à la commande. Ils ne sont ni regroupés ni répartis à nouveau.

Aucune migration : les lignes par lot existaient déjà.

### Vérifications

- `tests/TransferGlobalStockIntegrationTest.php` (12 contrôles) : répartition 8 + 7 avec un lot partiellement réservé, poids par ligne, absence de réservation à la création, refus au-delà du stock global sans transfert partiel, nouvelle répartition 10 + 5 à la validation après consommation d’un lot, réservations par lot, refus de validation sans effet, ligne sur lot explicite conservée. Produit et format de test isolés, supprimés en fin de test.
- `scripts/test_inter_site_transfers.php`, `tests/TransferDirectoryViewTest.php`, `tests/TransferDossierViewTest.php`, `scripts/test_fuel_distribution_integration.php` : conformes.

## Livraisons clients et sorties sans commande

Écran : Distribution · sorties clients (`/distributions`).

**Livraison d’une commande** : rien ne change. Le parcours était déjà global : on choisit d’où partent les sacs (bureau ou site de production) et combien de sacs de chaque produit commandé. Les lots sont ceux réservés pour la commande.

**Sortie sans commande** (vente au comptoir, don, usage interne) : on ne choisit plus le lot.

1. **À qui ?** Un client enregistré ou un destinataire libre.
2. **Quoi ?** Le produit et le format, regroupés par site, avec le stock libre de tous les lots (« Farine de maïs · Sac 25 kg — 178 sac(s) libres (4 lots) »). Puis le nombre de sacs ; « Tout » reprend tout le stock libre du produit.
3. Pendant la saisie, l’encadré **« Sacs prélevés automatiquement »** liste les lots qui partiront, des plus anciens aux plus récents, avec ce qui restera sur chacun. Une quantité trop élevée est signalée immédiatement, avec le maximum disponible.
4. La **confirmation** reprend le produit, la quantité, le poids et le détail des lots.

Côté serveur :

- Les lots libres du site, du produit et du format sont verrouillés, du plus ancien au plus récent. Seuls les sacs entiers non réservés sont prélevés.
- Un seul bon de sortie (BS) est créé, avec la quantité totale. Chaque lot prélevé reçoit un mouvement de stock « out » : ces mouvements constituent le détail de la sortie.
- La fiche, le bon imprimé et le panneau de l’historique affichent les lots prélevés.
- **Annulation** : chaque lot récupère exactement ses sacs, avec un mouvement d’ajustement par lot.
- **Annulation d’un emballage** : bloquée si un de ses sacs est sorti, y compris via un mouvement par lot.
- **Traçabilité** : une sortie apparaît sous chaque lot de production dont elle a pris des sacs.
- Un lot explicite (`finished_stock_id`) reste accepté pour les scripts existants.

Aucune migration : les mouvements de stock portent déjà le lot et la sortie.

### Vérifications

- `tests/DistributionGlobalStockIntegrationTest.php` (12 contrôles) : stock global (24 sacs libres sur 3 lots), prélèvement 4 + 5 avec un lot partiellement réservé, poids par lot, lots réservés intacts, détail du bon et de l’historique, refus au-delà du stock libre sans effet partiel, quantité nulle, lot explicite, annulation lot par lot. Format de sac de test isolé, données supprimées en fin de test.
- `tests/DistributionViewTest.php`, `tests/TransferDirectoryViewTest.php` : données mises au nouveau format (produits regroupés).
- `tests/browser/DistributionBrowserTest.cjs`, `tests/browser/TransferCreateBrowserTest.cjs` : sélection du produit (`stock_key`) au lieu du lot. À exécuter avec Playwright.
- `scripts/test_cancellation_integration.php`, `scripts/test_business_rules.php`, `scripts/test_traceability.php`, `scripts/test_fuel_distribution_integration.php` : conformes.

## Transport ferme → silo

Écrans : Agriculture › Transports (« Créer un bon de transport ») et Agriculture › Stocks (dépôts).

- On choisit **la ferme, le produit et la variété** (« Maïs brut · Maïs local — 48 680 kg disponibles (3 récoltes) »), puis la quantité. On ne choisit plus la récolte. « Tout » reprend tout le disponible.
- Le disponible déduit les réservations **et les BT en brouillon** : une même quantité ne peut pas être promise deux fois.
- Sous la quantité, l’encadré **« Récoltes chargées automatiquement »** liste les récoltes qui partiront, de la plus ancienne à la plus récente, avec ce qui restera sur chacune.
- Dans les dépôts, le bouton « Transporter » d’une récolte ouvre le formulaire sur son produit et sa variété.
- **Règle modifiée** : une récolte peut figurer dans plusieurs BT en brouillon, tant qu’il reste de la quantité libre. Avant, un seul brouillon par récolte était autorisé.

Côté serveur :

- **Création (brouillon)** : la quantité est répartie sur les récoltes validées libres les plus anciennes, avec une ligne `agricultural_transport_lines` par récolte. Rien n’est réservé.
- **Validation** : si une récolte n’a plus assez de quantité libre, la quantité est répartie à nouveau sur les récoltes libres du même produit et de la même variété, en respectant les autres brouillons. Elle est ensuite réservée récolte par récolte. Si le stock ne suffit plus, la validation est refusée sans effet.
- **Expédition** : sortie physique récolte par récolte, avec un mouvement par récolte, et un seul transport au pont-bascule pour la quantité totale. Le numéro de BT officiel est conservé.
- La liste des BT affiche toutes les récoltes chargées. La correction d’une récolte tient compte des brouillons ligne par ligne.
- **Traçabilité** : un BT, son camion, sa pesée et son entrée silo apparaissent sous chaque campagne dont une récolte a été chargée.
- `agricultural_transports.stock_id` garde la récolte principale (première ligne). Une récolte explicite (`stock_id`) reste acceptée pour les scripts existants.

### Déploiement

Appliquer `database/053_add_agricultural_transport_lines.sql`. La migration crée la table des lignes et reprend chaque BT existant comme une ligne. Avant la migration, seul le chemin par récolte explicite fonctionne : le formulaire global est refusé avec un message demandant la migration.

### Vérifications

- `tests/FarmTransportGlobalStockIntegrationTest.php` (14 contrôles) : disponible global, prélèvement 2 000 + 3 000 kg sur une récolte partiellement réservée, brouillon sans réservation, second brouillon démarrant après le premier, disponible affiché brouillons déduits, refus au-delà du disponible sans BT partiel, nouvelle répartition 3 000 + 2 000 kg à la validation, réservations par récolte, expédition lot par lot avec un seul camion, mouvements par récolte. Récoltes de test isolées, supprimées en fin de test.
- `tests/FarmTransportFormTest.php` (réécrit) : produits regroupés par ferme, disponible global, ordre des récoltes de l’aperçu, bouton « Tout », état vide, trajet initial.
- `tests/FarmTransportBridgeIntegrationTest.php` (64 vérifications) et `tests/FarmDepotViewTest.php` : adaptés à la nouvelle règle des brouillons. La table des lignes est ajoutée aux tables temporaires du test pont-bascule.
- `scripts/test_agriculture.php` (nettoyage des lignes), `scripts/test_agriculture_to_silo_integration.php`, `scripts/test_weighbridge_transport.php`, `scripts/test_traceability.php`, `tests/AgriculturalWorkIntegrationTest.php` : conformes.

## Issues → aliment bétail

Écran : Aliment bétail (`/pelletization`).

**Transfert d’issues vers PELL (BTI)**

- On choisit le **type d’issue** du site sélectionné (« MINO · Son — 1 200 kg disponibles (3 lots) »), puis la quantité.
- L’encadré « Lots prélevés automatiquement » liste les lots, des plus anciens aux plus récents.
- Le disponible déduit les BTI en brouillon et les fabrications en cours.
- À l’expédition, si un lot a été consommé entre-temps, le BTI est réparti à nouveau sur les lots libres du même type.
- À la réception, un stock d’issues est créé à PELL **par lot reçu** : le lot de production d’origine reste traçable.

**Ordre de fabrication (OF)**

- On choisit le **type d’issue** disponible à PELL au lieu d’un lot.
- La part d’issues prévue par la recette est répartie sur les lots les plus anciens. L’aperçu des lots s’affiche avec la vérification des matières.
- **Saisie des résultats** : la consommation réelle va d’abord sur les lots prévus par l’OF. Si elle dépasse ce qu’ils contiennent, le complément est pris sur les lots suivants, les plus anciens d’abord. Si le stock ne suffit pas, la saisie est refusée.
- **Validation** : déduction lot par lot, avec un mouvement par lot. Le coût de revient additionne les issues de tous les lots.
- **Annulation** : chaque lot récupère sa part.

Un lot explicite (`waste_stock_id`) reste accepté pour les scripts existants. Aucune migration : BTI et OF avaient déjà des lignes par lot.

### Vérifications

- `tests/IssuesGlobalStockIntegrationTest.php` (18 contrôles) : issues regroupées par site et type, BTI 300 + 200 kg, BTI en brouillon déduit, refus sans BTI partiel, nouvelle répartition 100 + 400 kg à l’expédition, réception d’un stock PELL par lot, OF 100 + 300 kg prévus, fabrication en cours déduite, consommation réelle sur les lots prévus d’abord puis complément, validation et mouvements lot par lot, coût, annulation lot par lot. Type d’issue de test isolé, données supprimées en fin de test.
- `scripts/test_pelletization.php`, `scripts/test_cancellation_integration.php`, `scripts/test_traceability.php`, `scripts/test_business_rules.php` : conformes. `scripts/test_uniform_cancellations.php` garde son échec existant (« routes protégées »), identique sans ces changements.

## Boucherie (DLC)

Circuit actuel : chambre froide → comptoir → caisse (`PorcineFlowService`). Les anciens écrans de boucherie redirigent déjà vers ce circuit.

**Déjà conforme avant ce lot** : la **caisse** encaisse par article, et la **transformation** en mode automatique prélève sur la DLC la plus proche, lots périmés exclus.

**Ce qui change**

- **Approvisionner un comptoir** : on choisit le **produit** (« Côtes de porc — 14,000 kg en chambre froide (3 lots) ») et la quantité ou le nombre de pièces. Les lots valides sont pris dans l’ordre de la DLC la plus proche ; les lots à DLC dépassée ne sont jamais proposés ni pris. Le bouton « Approvisionner un comptoir » de la chambre froide présélectionne le produit.
- **Remettre au froid** : par produit et par comptoir. Les lots à **DLC la plus lointaine** repartent d’abord, pour que le comptoir garde ce qui doit être vendu en premier.
- **Vente simple au comptoir** (sans caisse) : on choisit « comptoir · produit » et la quantité. Le stock est pris sur la DLC la plus proche, avec une vente par lot. Les articles suivis en pièces restent encaissés depuis la caisse.
- **Transformation** : le choix manuel des lots est retiré, de l’écran comme du serveur. Le prélèvement est toujours automatique, DLC la plus proche en premier. La liste des lots reste consultable.
- Chaque formulaire affiche **« Lots prélevés automatiquement »** : lot, DLC, quantité prise et reste.
- Un lot explicite (`finished_lot_id`, `counter_stock_id`) reste accepté pour les scripts existants.

Aucune migration.

### Vérifications

- `tests/ButcheryGlobalStockIntegrationTest.php` (9 contrôles, base jetable `setup_porcine_flow_test.php`) : approvisionnement 10 + 5 kg avec lot périmé exclu, refus au-delà du stock valide sans effet partiel, vente 10 + 2 kg avec une ligne par lot, lot vendu épuisé, refus au-delà du comptoir, retour au froid sur la DLC la plus lointaine, lot explicite conservé, choix manuel refusé en transformation.
- `ColdRoomViewIntegrationTest` (38 contrôles), `ButcheryHomeIntegrationTest`, `ButcheryRecipeAccessIntegrationTest`, `ManagerSelfValidationIntegrationTest`, `FarmManagerSelfValidationIntegrationTest`, `ButcheryNavigationTest` : conformes.
- `PorcineFlowIntegrationTest` échoue avant la boucherie (« Aucun groupe actif ne couvre cet âge » : référentiel des groupes porcins de la base de test), de façon identique sans ces changements. Les tests qui dépendent de sa fixture (`PorcineFlowGuardrailsTest`, `ButcheryPosIntegrationTest`, `TransformationSourceIntegrationTest`…) n’ont donc pas pu tourner.

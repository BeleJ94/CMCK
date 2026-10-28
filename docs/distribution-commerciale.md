# Distribution commerciale

La distribution des farines et de l’aliment bétail se réorganise en trois circuits, bien séparés :

- **clients entreprises** : commande, réservation à la minoterie ou à la pelletisation, transfert vers le bureau principal, puis livraisons partielles et relevés ;
- **distribution interne** : demandes des sites de l’entreprise (fermes, boucherie…), sans prix, jamais comptées dans les ventes ;
- **particuliers** : vente au comptoir du bureau principal, en USD et en CDF au taux du jour.

Le **bureau principal** regroupe les dépôts DEVCO et DECO. Son stock se divise en stock libre (comptoir), réservé par client et réservé interne.

## Lots

| Lot | Contenu | État |
|---|---|---|
| 1 | Bureau principal, fiches clients, taux du jour, tarifs | Livré |
| 2 | Commandes clients, réservation au site de production, transferts affectés, réception au bureau | Livré |
| 3 | Livraisons (depuis le bureau ou le site de production, enlèvement ou camion), bon de livraison, clôture des commandes ; relevés de commande et client | Livraisons livrées ; relevés à venir |
| 4 | Comptoir particuliers : sessions de caisse, tickets USD/CDF, clôture validée par un autre utilisateur | À venir |
| 5 | Distribution interne | À venir |
| 6 | Paiements clients, acomptes, solde, plafond de crédit ; stock d’aliment des fermes | Option |

Décisions déjà prises :

- **Commandes** : elles portent les quantités et les prix ; les paiements viendront au lot 6.
- **Produits** : farine de maïs et aliment bétail.
- **Stock insuffisant** : on réserve ce qui existe, et le reste apparaît « à produire ».
- **Livraisons** : depuis le bureau principal ou directement depuis le site de production, par enlèvement du client ou par camion de l’entreprise.
- **Clôture de caisse** : elle est validée par un autre utilisateur.

## Lot 1

### Clients

Écran : **Distribution & logistique › Clients** (`/customers`).

**Type de fiche**

- **Entreprise** : raison sociale, nom commercial, RCCM, Id. Nat. et NIF, personne de contact et sa fonction.
- **Particulier** : un particulier régulier qui souhaite un compte. Les ventes ponctuelles se font au comptoir, sans fiche.

**Contenu commun** : téléphone (obligatoire), e-mail, adresse, ville, devise de facturation (USD ou CDF) et observations.

**Code** : il est attribué automatiquement (CLI-0001, CLI-0002…).

**Contrôles**

- Un nom ne peut appartenir qu’à un seul client, sans tenir compte des majuscules.
- Chaque identifiant légal ne peut appartenir qu’à un seul client. Les identifiants sont enregistrés en majuscules.
- Une fiche modifiée entre-temps par quelqu’un d’autre doit être actualisée avant d’être enregistrée.

**Blocage** : il exige un motif. Un client bloqué garde sa fiche et son historique, mais ne pourra plus passer de nouvelle commande. Le déblocage efface le motif.

**Traçabilité** : chaque création, modification, blocage ou déblocage est enregistré dans l’audit et listé en bas de page.

### Tarifs et taux

Écran : **Distribution & logistique › Tarifs et taux** (`/sales/settings`).

**Taux du jour**

- Il s’exprime en CDF pour 1 USD.
- Un nouveau taux remplace le précédent ; l’historique est conservé.
- Une variation de plus de 10 % doit être confirmée.
- Les opérations des lots suivants garderont le taux appliqué.

**Tarifs par sac**

- Un prix en USD par produit, par format et par canal :
  - **Entreprises** : proposé à la commande, puis figé sur celle-ci ;
  - **Comptoir** : appliqué aux particuliers.
- L’équivalent en CDF est calculé au taux du jour.
- La grille reprend les formats prévus pour chaque produit (emballages vides) et ceux déjà emballés.
- Un tarif laissé vide n’est pas modifié, et seul un tarif changé entre dans l’historique.

**Bureau principal** : la liste des dépôts qui le composent, avec leur stock libre.

### Droits

Les écrans utilisent le composant `distributions`.

| Action | Droit | Rôles |
|---|---|---|
| Consulter | lecture | administrateur, direction, agent de distribution… |
| Créer une fiche client | création | administrateur, agent de distribution… |
| Modifier une fiche | modification | administrateur… |
| Bloquer ou débloquer un client | validation | administrateur, direction… |
| Fixer le taux du jour et les tarifs | validation | administrateur, direction… |
| Choisir les dépôts du bureau principal | administration | administrateur |

### Déploiement

Appliquer une fois `database/047_add_customers_and_sales_settings.sql`. La migration :

- ajoute la colonne `sites.main_office` et y place DEVCO et DECO ;
- crée les tables `customers`, `exchange_rates` et `sale_prices`.

Ensuite, depuis **Tarifs et taux** : saisir le premier taux du jour, puis les tarifs de chaque format.

### Vérifications

- `tests/CustomerSalesSettingsIntegrationTest.php` : 47 contrôles MySQL sur une base jetable créée par `scripts/setup_porcine_flow_test.php`. Ce script copie désormais aussi les types de sites, les produits, les formats de sacs et les emballages vides. Le test couvre :
  - les fiches clients, leurs doublons, les versions concurrentes, le blocage et l’audit ;
  - le taux et ses variations ;
  - les tarifs et leur historique ;
  - le bureau principal ;
  - les droits de l’agent de distribution et de la direction ;
  - les pages et le menu.
- `tests/browser/CustomerSalesSettingsBrowserTest.cjs` : parcours Chrome, avec l’affichage mobile à 390 et 320 px. Il couvre :
  - la fiche entreprise ou particulier ;
  - le doublon de NIF refusé sans perte de saisie ;
  - la modification, le blocage et le déblocage ;
  - le taux avec une variation confirmée ;
  - les tarifs avec leur équivalent CDF.

## Lot 2 : commandes clients

Écran : **Distribution & logistique › Commandes clients** (`/sales/orders`).

### Cycle d’une commande

1. **Brouillon**
   - Saisi par l’agent de distribution : client actif, dépôt du bureau principal (DEVCO ou DECO), date de livraison souhaitée et observations.
   - Une ligne par produit et format. Le formulaire affiche en direct le montant de chaque ligne, le total en USD (et en CDF pour un client facturé en CDF) et les sacs libres au site de production.
   - Le prix vient du tarif entreprises au moment de l’enregistrement, et reste figé. Pour un client facturé en CDF, le taux du jour est figé aussi.
   - Un brouillon se modifie, par son auteur ou par un utilisateur habilité ; il ne réserve encore rien.
2. **Validation**
   - Elle est faite par un autre utilisateur autorisé : direction ou administrateur.
   - Les sacs libres au site de production sont réservés, les lots les plus anciens d’abord. La farine est réservée à la minoterie, l’aliment bétail à la pelletisation.
   - Le reste apparaît **« à produire »**.
3. **Réservation automatique**
   - Chaque nouvel emballage (minoterie) ou ensachage (pelletisation) va d’abord aux commandes validées qui attendent ce produit et ce format, de la plus ancienne à la plus récente.
   - Les sacs revenus à la minoterie après un refus en réception sont réservés de la même façon.
   - Le menu « Réserver le disponible » relance la réservation à la main.
4. **Envoi au bureau**
   - On indique le nombre de sacs par ligne : tous les sacs réservés sont proposés par défaut.
   - Un transfert est créé par site de production et soumis pour approbation. Il est rattaché à la commande et prend les lots réservés, les plus anciens d’abord.
   - Il se traite ensuite dans **Transferts inter-sites**, comme tout transfert : approbation, expédition (éventuellement en plusieurs fois), réception avec sacs acceptés ou refusés, retours, clôture. La page du transfert indique la commande et le client.
5. **Au bureau principal**
   - Les sacs acceptés restent **réservés au client** dans le dépôt de destination.
   - Le comptoir et les autres sorties ne peuvent pas les prendre ; ils attendent la livraison (lot 3, ci-dessous).

### Avancement d’une ligne

Chaque sac commandé est dans un seul état à la fois :

| État | Signification |
|---|---|
| Réservé au site | Bloqué sur un lot de la minoterie ou de la pelletisation. Une partie peut être « en préparation d’envoi » dans un transfert non expédié. |
| En route | Expédié, pas encore réceptionné. |
| Au bureau | Accepté au bureau principal, réservé au client. |
| Livré | Remis au client par un bon de livraison (lot 3). |
| À produire | Ni réservé, ni en route, ni au bureau : faute de stock, ou après un refus en réception. |

La liste des commandes montre cet avancement en barre. La fiche d’une commande le détaille par ligne, avec :

- ses envois, avec un lien vers chaque transfert ;
- le **journal des sacs** : réservé, confié à un envoi approuvé, revenu en réservation, arrivé au bureau, refusé à la réception, libéré.

La fiche client liste aussi les commandes du client.

### Règles

- **Réservations** : les sacs réservés pour un client utilisent la réservation des stocks finis. La sortie directe, les autres transferts et le futur comptoir ne les voient plus comme disponibles.
- **Approbation d’un envoi** : la réservation de la commande devient celle du transfert, sans changer la réservation des lots. Si les sacs ne sont plus réservés pour la commande, l’approbation est refusée.
- **Clôture ou annulation d’un envoi** (module Annulations & corrections) : les sacs non expédiés reviennent à la réservation de la commande.
- **Refus à la réception** : les sacs refusés repartent vers le site de production par le retour habituel, puis sont réservés de nouveau pour la commande.
- **Annulation d’une commande**
  - Elle exige un motif et un utilisateur habilité à valider.
  - Elle est refusée tant qu’un envoi approuvé n’est pas réceptionné et clôturé.
  - Les envois non approuvés sont annulés.
  - Toutes les réservations sont libérées : à la minoterie, et au bureau, où les sacs deviennent libres pour le comptoir.
- **Traçabilité** : chaque création, modification, validation, envoi et annulation est enregistré dans l’audit.

### Droits (composant `distributions`)

| Action | Droit |
|---|---|
| Consulter | lecture |
| Saisir, réserver le disponible, préparer un envoi | création ; l’envoi demande aussi la création de transferts |
| Valider, annuler | validation |

### Déploiement

Appliquer une fois `database/048_add_customer_orders.sql`, après la 047. La migration :

- crée les tables `customer_orders`, `customer_order_lines`, `customer_order_allocations` et `customer_order_movements` ;
- ajoute le rattachement des transferts à une commande (`stock_transfers.customer_order_id`, `stock_transfer_items.customer_order_line_id`).

### Vérifications

- `tests/CustomerOrderIntegrationTest.php` : 37 contrôles sur une base jetable. Ils couvrent :
  - le brouillon et ses refus, et le taux figé ;
  - la validation et la réservation par lots les plus anciens, puis la protection contre la sortie directe ;
  - la réservation automatique à l’emballage ;
  - l’envoi et le refus de dépasser la réservation ;
  - l’approbation, l’expédition partielle, la réception avec refus et le retour réservé de nouveau ;
  - la clôture, puis l’annulation d’un envoi approuvé ;
  - l’annulation de la commande ;
  - la page et le menu.
- `tests/browser/CustomerOrderBrowserTest.cjs` : parcours Chrome, avec la fixture `tests/integration/CustomerOrderBrowserFixture.php` et l’affichage mobile à 390 et 320 px. Il couvre :
  - la commande à deux lignes avec son total en direct ;
  - la validation et la réservation ;
  - l’envoi affecté, visible sur la page du transfert ;
  - l’annulation, qui libère les sacs et annule le transfert non approuvé.
- `scripts/test_inter_site_transfers.php`, `test_pelletization.php`, `test_business_rules.php` (emballage), `test_cancellation_integration.php`, `test_uniform_cancellations.php` et `test_empty_packaging.php` passent sur une copie complète de la base avec la migration 048.

## Lot 3 : livraisons et sorties clients

Écran : **Distribution & logistique › Distribution clients** (`/distributions`). La page réunit tout ce qui sort vers les clients :

- en haut, quatre indicateurs : sacs prêts à livrer, reste à livrer sur les commandes en cours, sorties du jour (bons et poids, hors annulations), stock libre ;
- **Commandes à livrer** : une carte par commande en cours, avec le client, l’avancement par produit (« 15 / 30 livrés »), et l’endroit où les sacs sont prêts (bureau principal ou site de production). Une commande sans sac prêt indique ce qu’elle attend : en route, en préparation d’envoi, à produire ;
- **Historique des sorties** : les bons de livraison (BL) et les bons de sortie sans commande (BS) dans une seule liste, filtrable par type, statut et période, avec le détail, l’impression et l’annulation.

### Livrer une commande

Le bouton **Livrer** d’une carte ouvre le formulaire, en trois étapes :

1. **D’où partent les sacs ?** Le bureau principal est proposé en premier. Le site de production apparaît aussi quand des sacs y sont réservés et ne sont pas prévus dans un envoi : c’est la livraison directe depuis la minoterie ou la pelletisation.
2. **Sacs à remettre** : pour chaque produit, les sacs prêts à cet endroit et la quantité à livrer, proposée au maximum. Les boutons Tout et 0 aident à ajuster. Le total en sacs, en kg et en montant (USD, et CDF au taux de la commande) se met à jour en direct.
3. **Remise au client** : enlèvement par le client, ou camion de l’entreprise (camion et chauffeur obligatoires), nom et téléphone de la personne qui reçoit, date et heure de remise, observations.

« Vérifier et livrer » ouvre un récapitulatif sobre (provenance, produits, total, transport, réceptionnaire) avec **Modifier** ou **Confirmer la livraison**. Après l’enregistrement, la page affiche le message et un bouton **Voir le bon de livraison**.

On peut aussi arriver directement sur le formulaire depuis **Commandes clients** : le bouton **Livrer** de la ligne (quand des sacs sont au bureau) ou de la fiche de la commande.

### Effets d’une livraison

- Les sacs sortent du stock, lot par lot (les plus anciens d’abord), et de la réservation du client. Un lot vide est désactivé.
- Chaque lot donne un mouvement de stock « sortie » rattaché à la livraison et une ligne « Livré au client » au journal de la commande.
- Un bon de livraison est enregistré au registre des documents (`BL/<site>/<année>/<n°>`), avec une référence interne `BL-<année>-<n°>`. Il n’existe qu’en PDF : l’aperçu, l’impression et le téléchargement sont le même document. Il contient : client, réceptionnaire, transport, produits, sacs, poids, prix figés et montant (en CDF au taux de la commande pour un client facturé en CDF), et trois signatures.
- Quand tous les sacs sont livrés, la commande est **soldée** automatiquement (« Entièrement livrée ») et quitte les commandes à livrer.

### Aperçu des bons

Chaque bon (BL ou BS) s’ouvre dans un modal d’aperçu sur la page : depuis le bandeau après l’enregistrement, le menu de l’historique ou le détail d’une sortie. Le modal affiche le PDF lui-même, avec **Imprimer**, **Télécharger le PDF** et **Nouvel onglet**.

- `distributions/deliveries/{id}/print` affiche le bon de livraison en PDF ; avec `?export=pdf`, il le télécharge.
- `distributions/{id}/print?export=pdf&preview=1` affiche le bon de sortie en PDF ; sans `preview=1`, il le télécharge.

### Annuler une livraison ou clôturer une commande

- **Annuler une livraison** (menu de l’historique ou détail, droit de validation, motif obligatoire) : les sacs reviennent au stock du même endroit, de nouveau réservés à la commande ; le bon de livraison est annulé et le journal l’indique. Une commande soldée par cette livraison est rouverte. L’annulation est refusée si la commande a été clôturée à la main ou annulée.
- **Clôturer une commande** (Commandes clients, menu de la ligne, droit de validation, motif obligatoire) : pour une commande livrée en partie dont le reste n’est plus attendu. Les réservations restantes sont libérées (au site de production et au bureau, où les sacs deviennent libres), les envois non approuvés sont annulés. Elle est refusée tant qu’un envoi approuvé n’est pas réceptionné et clôturé.
- Une commande déjà livrée en partie ne s’annule plus : on la clôture.

### Sortie sans commande

Le bouton **Sortie sans commande** remplace l’ancienne « Nouvelle sortie » (vente au comptoir en attendant le lot 4, don, usage interne) :

1. **À qui ?** Un client enregistré (son nom est repris sur le bon) ou un autre destinataire.
2. **Quoi ?** Un lot de stock libre, présenté par site, puis par produit, les plus anciens d’abord, avec les sacs libres ; la quantité (bouton Tout), la date. Le poids et le reste du lot s’affichent en direct.
3. **Transport**, facultatif.

Un récapitulatif demande confirmation, puis la page affiche un bouton **Voir le bon de sortie**. Seul le stock libre peut sortir : les sacs réservés aux commandes n’apparaissent pas.

### Règles

- Seule une commande en cours (validée) se livre ; un client bloqué ne peut pas être livré tant que sa fiche n’est pas débloquée.
- On ne livre jamais plus que le reste de la commande, ni plus que les sacs prêts à l’endroit choisi.
- La date de remise ne peut être ni future, ni antérieure à la validation de la commande.
- Chaque livraison, annulation et clôture est enregistrée dans l’audit.

### Droits (composant `distributions`)

| Action | Droit |
|---|---|
| Consulter, imprimer un bon | lecture |
| Livrer, sortie sans commande | création |
| Annuler une livraison, clôturer une commande | validation |

Le site de départ doit faire partie des sites de l’utilisateur.

### Déploiement

Appliquer une fois `database/049_add_customer_deliveries.sql`, après la 048. La migration :

- crée les tables `customer_deliveries` et `customer_delivery_lines` ;
- ajoute au journal des commandes les mouvements « livré » et « livraison annulée », et à la commande sa clôture (`closed_by`, `closed_at`, `close_reason`) ;
- rattache les mouvements de stock à une livraison (`stock_movements.customer_delivery_id`) et une sortie sans commande à un client (`distributions.customer_id`).

### Vérifications

- `tests/CustomerDeliveryIntegrationTest.php` : 41 contrôles sur une base jetable. Ils couvrent :
  - les commandes à livrer et l’ordre des provenances ;
  - les refus, tous annulés sans trace : provenance, camion, chauffeur, réceptionnaire, date future, quantités ;
  - la livraison partielle depuis le bureau, puis directe depuis la minoterie, et la dernière qui solde la commande ;
  - l’annulation, qui rouvre la commande et remet les sacs en réservation ;
  - la clôture d’une commande livrée en partie ;
  - le client bloqué ;
  - le bon imprimable en CDF ;
  - la sortie sans commande à un client enregistré ;
  - la page.
- `tests/DistributionViewTest.php` : rendu de la page sans base (totaux du jour hors annulations, historique BL et BS, échappement, client bloqué).
- `tests/browser/CustomerDeliveryBrowserTest.cjs` : parcours Chrome, avec la fixture `tests/integration/CustomerDeliveryBrowserFixture.php` et l’affichage mobile à 390 et 320 px. Il couvre :
  - la provenance et le camion ;
  - le refus conservé, puis la confirmation et le bon à imprimer ;
  - l’annulation ;
  - le lien depuis les commandes ;
  - la clôture ;
  - la sortie sans commande.
- `tests/browser/DistributionBrowserTest.cjs` : filtres de l’historique, détails, formulaires et mobile, sur le rendu de `DistributionViewTest`.
- Sur une copie complète de la base avec la migration 049, ces scripts passent :
  - `scripts/test_business_rules.php` ;
  - `test_cancellation_integration.php` ;
  - `test_document_registry.php` ;
  - `test_inter_site_transfers.php` ;
  - `test_pelletization.php` ;
  - `test_empty_packaging.php`.

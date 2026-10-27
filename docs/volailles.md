# Volailles : de la ferme au comptoir

Écrans :

- **Élevage › Volailles › Lots et suivi** (`/livestock/poultry`) ;
- **Élevage › Volailles › Abattages et envois** (`/livestock/poultry/slaughters`) ;
- à la boucherie : **Réceptions**, **Transformation** (section « Poulets vivants »), **Comptoirs et ventes** (caisse et tarifs).

Deux types de volailles sont suivis :

- les **poulets de chair**, abattus à la ferme ou vendus sur pied (vivants) au comptoir de la boucherie ;
- les **pondeuses**, dont les œufs servent uniquement à la reproduction. Les poussins éclos deviennent des poulets de chair. Les poules sont abattues à la ferme, comme les porcs.

Toutes les ventes se font au comptoir de la boucherie. La boucherie suit les **pièces** (carcasses, poulets entiers, poulets vivants) en plus des kilos.

## À la ferme

1. **Réception fournisseur.**
   - On choisit le type (chair ou pondeuses), le fournisseur, le poulailler et le nombre d’oiseaux.
   - L’âge à l’arrivée est facultatif, par exemple 1 jour pour des poussins. Le tableau affiche ensuite l’âge du lot (J+51).
   - Le poids vif est facultatif : net, ou brut moins la tare des cages.
   - La capacité du poulailler est contrôlée. Chaque réception crée un lot.
2. **Éclosion.** Depuis un lot de pondeuses (menu « ⋯ » ou bouton « Enregistrer une éclosion »), on déclare les poussins éclos et leur poulailler, sur la même ferme. Ils forment un nouveau lot de poulets de chair, rattaché à leurs pondeuses. L’incubation n’est pas suivie.
3. **Saisie du jour.**
   - Bouton « Saisir » d’un lot, ou « Saisie du jour » : œufs ramassés (pondeuses uniquement) et oiseaux morts.
   - Plusieurs saisies par jour sont possibles : elles s’additionnent.
   - Les oiseaux morts quittent le lot immédiatement. Le journal des saisies garde chaque saisie.
4. **Abattage à la ferme.**
   - On compte les carcasses et on les pèse, en net ou brut moins tare (bacs, caisses).
   - Les oiseaux trouvés morts lors de l’abattage sont déclarés à part : la mortalité est donc suivie chaque jour et à l’abattage.
   - L’article est choisi selon le lot : « Carcasse de poulet » pour la chair, « Carcasse de poule » pour les pondeuses. La DLC est proposée selon cet article.
   - Un autre utilisateur autorisé valide l’abattage, sauf droit d’auto-validation. La validation retire les oiseaux du lot, morts compris, et crée le stock de carcasses à la ferme.
5. **Envoi de carcasses.**
   - On indique le nombre de carcasses ; le poids est proposé d’après la pesée de l’abattage.
   - Les dernières carcasses d’un stock emportent tout son poids restant, et inversement.
   - Le bon de transfert est numéroté BTR-VOL. On prépare, approuve puis expédie l’envoi, comme pour les porcs.
6. **Envoi de poulets vivants.**
   - Réservé aux poulets de chair. On compte les poulets et on les pèse dans leurs cages (brut moins tare).
   - Le bon de transfert est numéroté BTR-VOL-VIF.
   - Les poulets restent dans le lot jusqu’à l’expédition, mais ils ne sont plus disponibles pour un autre abattage ou envoi.

## À la boucherie

1. **Arrivée comptée et pesée.**
   - Les carcasses et les poulets vivants sont comptés puis pesés.
   - Une pièce manquante demande un motif, par exemple la mortalité pendant le transport.
   - L’écart de poids est contrôlé selon la tolérance « Poulet » de Boucherie › Écarts de pesée.
2. **Contrôle.**
   - Les carcasses entrent en chambre froide au poids et au nombre reçus (lot CARC-VOL).
   - Les poulets vivants forment un lot vendable « Poulet vif » (lot VIF-VOL). Ils ne sont pas en chambre froide : on les retrouve dans la page Transformation, section « Poulets vivants ». Leur date « à vendre avant » vient de la durée de l’article « Poulet vif », 30 jours par défaut.
3. **Abattage en boucherie.**
   - Depuis la section « Poulets vivants », on abat des poulets du lot, hors ceux déjà placés au comptoir, et on déclare les poulets trouvés morts.
   - On pèse les carcasses : elles entrent en chambre froide (lot CARC-VOL-BOU) et l’écran affiche le rendement sur le poids vif prélevé.
4. **Transformation.**
   - Pour des carcasses de volaille, on saisit le nombre de pièces sorties ; le poids est calculé à partir des lots, par DLC la plus proche.
   - Les produits comptés, comme « Poulet entier » ou « Poule entière », demandent le nombre de pièces obtenues et le nombre envoyé au comptoir.
   - La découpe, par exemple « Morceaux de volaille », reste au poids.
   - Fiches de découpe : « Découpe de volaille » pour les poulets, « Découpe de poule » pour les poules.
5. **Comptoir et caisse.**
   - Les produits comptés sont approvisionnés et remis au froid par pièces.
   - Dans **Tarifs**, chaque article suivi en pièces choisit son mode de vente : **au kilo** ou **à la pièce**. Les autres articles se vendent au kilo.
   - Au moment de l’encaissement :
     - un article à la pièce est compté ;
     - un article au kilo suivi en pièces est pesé et compté ;
     - les autres articles sont pesés.
   - Le reçu indique les pièces, les kilos et l’unité du prix.
   - Changer le mode de vente d’un article rend ses tarifs de l’autre mode inutilisables, jusqu’à leur nouvelle saisie dans chaque devise.

### Pièces et kilos

- Pour un stock suivi en pièces, ce sont les pièces qui sortent : les kilos suivent proportionnellement, et les dernières pièces emportent les kilos restants.
- Le poids pesé à la caisse sert au prix ; le poids retiré du stock est celui des pièces vendues. L’écart entre les deux reste visible dans les ventes.
- Le rapport de ventes ajoute les pièces vendues par article.

## Suivi

- **Indicateurs des lots** : poulets de chair et pondeuses vivants, œufs ramassés et mortalité du jour.
- **Fiche d’un lot**, ouverte par le menu « ⋯ » :
  - l’origine (fournisseur, éclosion ou lot antérieur), l’âge et le poids vif ;
  - les effectifs : reçus, vivants, morts, abattus, envoyés vivants, poussins éclos ;
  - les dernières saisies et les abattages.
- **Abattages et envois** : carcasses à la ferme (pièces et kilos), double comptage et double pesée de chaque envoi, écart de transport et aperçu du bon de transfert.
- **Lots antérieurs** : les lots de poulets créés avant ce parcours deviennent des lots antérieurs, de type « Ponte » si leur objectif était la ponte, « Chair » sinon.
- **Parcours générique** : une fois la migration appliquée, la page de l’espèce Poulet redirige vers ces écrans. L’ancien parcours générique refuse alors les lots, conversions, transferts et suivis avicoles de poulets. Le « Suivi quotidien » renvoie vers la saisie du jour de Volailles et affiche ces saisies dans son historique.

## Déploiement

Appliquer une fois `database/044_add_poultry_flow.sql`.

La migration crée :

- les profils de lots (`poultry_lot_profiles`) ;
- les saisies journalières (`poultry_daily_records`) ;
- les détails d’abattage (`poultry_slaughter_details`) ;
- les envois vivants (`poultry_live_shipments`) ;
- les abattages en boucherie (`poultry_butchery_slaughters`).

Elle ajoute les pièces aux stocks, mouvements, réceptions, ventes et lignes de caisse de la boucherie. Elle ajoute aussi le mode de vente aux articles et aux tarifs.

Articles :

- « Volaille » devient « Carcasse de poulet » ;
- création de « Carcasse de poule », « Poulet vif », « Poulet entier » et « Poule entière », avec la fiche « Découpe de poule ».

Ensuite :

- paramétrer la tolérance « Poulet » dans Boucherie › Écarts de pesée ;
- saisir les tarifs des articles de volaille, au kilo ou à la pièce, dans Comptoirs et ventes › Tarifs ;
- vérifier que les poulaillers et les fournisseurs de poussins et de pondeuses sont actifs.

## Vérifications

- `tests/PoultryFlowIntegrationTest.php` : 69 contrôles MySQL sur une base jetable créée par `scripts/setup_porcine_flow_test.php`. Ils couvrent :
  - les réceptions, l’éclosion et la saisie du jour ;
  - le refus du parcours générique ;
  - l’abattage avec morts, sa validation et son annulation ;
  - les envois de carcasses en pièces et les envois vivants ;
  - l’arrivée comptée et pesée, le lot vivant et l’abattage en boucherie ;
  - l’approvisionnement en pièces et la transformation en pièces ;
  - la caisse à la pièce et au kilo, l’annulation de ticket et le rapport des écarts.
- `tests/browser/PoultryFlowBrowserTest.cjs` : parcours Chrome complet, avec l’affichage mobile à 390 et 320 px. La fixture est `tests/integration/PoultryBrowserFixture.php`. Le parcours va de la réception fournisseur à l’abattage en boucherie, en passant par :
  - la saisie du jour, l’éclosion et l’abattage ;
  - l’envoi vivant et son bon de transfert ;
  - l’arrivée comptée et le contrôle.
- `scripts/test_livestock.php` : vérifie le refus du parcours générique pour les poulets. Les conversions génériques sont testées sur une espèce temporaire.

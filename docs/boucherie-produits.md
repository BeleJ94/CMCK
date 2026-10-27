# Produits de transformation

Écran : **Boucherie › Produits** (`/butchery/products`).

Le référentiel indique, pour chaque matière (carcasse de porc, de bœuf, de poulet, de poule, poisson frais), les produits que l’on peut en obtenir. Le formulaire de transformation ne propose que les produits de la matière choisie.

## Le référentiel

- **Matières et produits** : une fiche par matière, avec son stock disponible en chambre froide et ses produits. Une matière sans produit est signalée : sa transformation est impossible.
- **Choisir les produits** : depuis la fiche d’une matière, on coche les produits qu’elle donne.
- **Catalogue des produits** : chaque produit, son type, ses matières d’origine, son suivi (kilos, ou pièces et kilos), sa DLC conseillée et le nombre de transformations qui l’ont produit. On peut filtrer par nom, code ou matière, et afficher les produits non proposés.
- **Ajouter un produit** :
  - nom, type (produit fini ou coproduit) et au moins une matière d’origine ;
  - le code est facultatif : laissé vide, il est déduit du nom (« Côtes de porc » donne COTES-DE-PORC, puis COTES-DE-PORC-2 s’il est pris) ;
  - « Compté en pièces » sert aux produits entiers, comme un poulet entier : la transformation demande le nombre de pièces, et la caisse peut le vendre à la pièce ;
  - la DLC conseillée, facultative, est proposée lors de la transformation.
- **Modifier un produit** : nom, type, matières et DLC conseillée. Le code et le suivi en pièces ne changent plus après la création, car les stocks en dépendent. Décocher toutes les matières retire le produit de la transformation, sans toucher à son stock ni à sa vente au comptoir.
- Chaque modification est enregistrée dans l’audit et listée en bas de page. Si deux personnes modifient le même produit ou la même matière, la seconde doit actualiser la page.
- Le paramétrage est réservé aux administrateurs de la boucherie ; les autres utilisateurs consultent le référentiel.

## Dans la transformation

1. On choisit d’abord le produit source (la matière). Avant ce choix, aucun produit n’est proposé.
2. La liste « Produit obtenu » ne contient que les produits de cette matière. Changer de matière retire les produits qui ne lui appartiennent pas.
3. Un bandeau indique le nombre de produits paramétrés, avec un lien vers le référentiel. Si la matière n’a aucun produit, l’enregistrement est impossible.
4. Le serveur refuse aussi un produit qui n’appartient pas à la matière, avec un message qui renvoie vers Boucherie › Produits.
5. Si le produit a une DLC conseillée, sa DLC est proposée à partir de la date de transformation, tant qu’on ne l’a pas saisie soi-même.

## Déploiement

Appliquer une fois `database/046_add_butchery_product_sources.sql`. La migration crée la table `butchery_product_sources` et la préremplit :

| Matière | Produits |
|---|---|
| Carcasse de porc | Morceaux de porc, Côtelettes de porc, Makoso, Abats |
| Carcasse de bœuf | Morceaux de bœuf, Abats |
| Carcasse de poulet | Poulet entier, Morceaux de volaille |
| Carcasse de poule | Poule entière, Morceaux de volaille |
| Poisson frais | Poisson préparé |

Elle ajoute aussi chaque couple matière / produit déjà présent dans les transformations passées. Par exemple, la « Viande découpée » reste proposée pour une matière dont elle a déjà été tirée.

Ensuite, vérifier les produits de chaque matière dans Boucherie › Produits. Avant la migration, l’écran l’indique et tous les produits restent proposés pour chaque matière.

## Vérifications

- `tests/ButcheryProductReferentialIntegrationTest.php` : 44 contrôles MySQL sur une base jetable créée par `scripts/setup_porcine_flow_test.php`. Ils couvrent :
  - le préremplissage de la migration ;
  - la création, le code déduit du nom et ses suffixes, et les refus (matière, type, code, nom, DLC) ;
  - la modification, le retrait de la transformation, les versions concurrentes et l’audit ;
  - le choix des produits d’une matière ;
  - le refus serveur d’un produit hors matière, sans réservation ;
  - la consultation sans droit d’administration et le menu.
- `tests/browser/ButcheryProductsBrowserTest.cjs` : parcours Chrome du référentiel et du formulaire de transformation filtré, avec la DLC proposée et l’affichage mobile à 390 et 320 px. La fixture est `tests/integration/ButcheryProductsBrowserFixture.php`.

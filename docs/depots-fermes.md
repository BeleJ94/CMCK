# Dépôts illustrés par ferme

La page agriculture/stocks représente chaque ferme autorisée par un dépôt SVG local. Les sacs sont indicatifs ; aucun taux de remplissage ni capacité fictive n’est affiché. Les quantités physiques, réservées et disponibles sont calculées sur les lots de récoltes validées, hors stocks annulés. Les dépôts vides restent visibles. Le contexte de site est respecté.

Le panneau latéral donne accès aux lots, à la campagne/parcelle d’origine et aux transports associés. Recherche par récolte, produit, variété, campagne ou parcelle ; filtres de disponibilité, tous les lots ou épuisés ; pagination de huit lots. Les quantités déjà expédiées figurent dans les transports associés, séparément du stock physique restant.

Créer un transport préremplit le stock et le trajet. Le bouton n’est proposé qu’avec un stock utilisable, sans BT en brouillon et avec le droit de création. Le circuit serveur habituel vérifie de nouveau ces règles. Après création AJAX, le répertoire et le formulaire sont actualisés sur la page Stocks ; le lot avec brouillon ne propose plus une nouvelle création.

Aucune migration ni nouvel objet de stockage : un dépôt est une représentation des stocks d’une ferme.

Validation : sept contrôles de vue/agrégation (`php tests/FarmDepotViewTest.php`), 115 contrôles d’interface, parcours Chrome sur la base isolée (filtres, lots, préremplissage, création réelle AJAX de BT, actualisation du bouton, retour Stocks, mobile sans débordement). Le test navigateur s’exécute avec le serveur isolé décrit dans travaux-agricoles-validation.md :

```sh
DAGRIL_DB_DATABASE=dagril_works_verified_test node tests/browser/FarmDepotBrowserTest.cjs
```

Les cartes privilégient désormais une petite illustration neutre, les produits en stock, le total en dépôt et une barre disponible/réservé calculée sur le stock physique. Les quantités et pourcentages accompagnent les couleurs ; cette barre ne représente pas la capacité du dépôt. Les campagnes, parcelles et transports restent accessibles dans le panneau des lots.

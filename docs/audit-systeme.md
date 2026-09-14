# Audit du système

Accès : Administration → Audit du système (`/activity-logs`). Le contrôleur réserve la consultation, les exports et les examens au rôle `administrateur`, en plus de la permission de lecture du journal. Les administrateurs consultent explicitement tous les sites, y compris les événements sans site. Les autres rôles restent refusés même s'ils possèdent une permission de lecture.

## Parcours

- Activité : tous les événements enregistrés, triés du plus récent au plus ancien, 50 par page, pagination et filtrage SQL.
- Sécurité : connexions, déconnexions, connexions refusées et accès refusés.
- Points à examiner : modifications, annulations, suppressions, changements de permissions, erreurs/refus et événements disposant d'un état antérieur. Il s'agit d'une sélection de contrôle, pas d'une détection de fraude.
- Filtres : dates inclusives, utilisateur, module, site, action réellement présente dans le journal, résultat, examen et recherche sur description/utilisateur/entité.
- Détail latéral : données avant/après disponibles, contexte, métadonnées, examens précédents. Une note d'examen ajoute un événement `audit_review` référant à l'original ; elle ne le remplace pas.
- Excel XLSX et PDF : événements filtrés, limite explicite de 2 000 lignes. Les exports sont eux-mêmes journalisés. Le détail JSON reste dans l'application.

## Collecte et limites

Les anciennes traces `activity_logs` sont conservées. Le journal n'invente pas de valeurs, de rôle historique ni de résultat manquant. Les appels à `Model::logActivity` enregistrent désormais un identifiant de requête et le rôle de l'auteur ; les champs sensibles structurés (mots de passe, secrets, clés, cookies et jetons) sont masqués récursivement avant écriture. Le lecteur masque également ces clés dans les anciennes valeurs JSON sans réécrire l'historique.

La collecte HTTP en fin de requête couvre les POST et demandes d'export atteignant `public/index.php`. Elle ne lit pas les corps POST, les cookies ni les paramètres d'URL. Les requêtes sont corrélées aux événements métier par leur identifiant. Un code HTTP sans erreur ne prouve pas un succès métier : l'interface affiche « Non précisé ». Les erreurs HTTP sont identifiées séparément. Une demande de téléchargement ne prouve pas la réception du fichier par l'utilisateur.

Les connexions réussies existaient déjà ; les déconnexions, identifiants refusés et refus du routeur/contrôleur d'audit sont désormais tracés explicitement. Les identifiants tentés à la connexion ne sont pas conservés. Les opérations automatiques utilisant la journalisation métier restent visibles ; les scripts ou écritures SQL externes qui ne l'appellent pas ne sont pas capturés. Les traces SQL directes existantes n'ont pas systématiquement les nouvelles métadonnées. Les pages consultées sans action ne sont pas toutes enregistrées.

Aucune purge automatique n'est ajoutée. Le journal affiche sa première trace disponible. Aucune route de modification ou suppression des événements n'est exposée. Cela ne constitue pas une garantie cryptographique contre une modification directe par un administrateur de base de données. Un échec du collecteur HTTP est signalé dans le journal PHP, sans transformer une opération déjà terminée en échec utilisateur.

Aucune migration de schéma n'est nécessaire : la table `activity_logs` et ses colonnes de détail/multisite existantes sont réutilisées.

## Vérification

`tests/SystemAuditIntegrationTest.php` : base terminant par `_test`/`_testing`, données dans une transaction annulée ; redaction, métadonnées, filtres, avant/après, notes append-only et refus de la direction.

`tests/browser/SystemAuditBrowserTest.cjs` : base locale de test via `WorksBrowserRouter.php`, navigation AJAX, espaces, modals, affichage mobile et téléchargements Excel/PDF. Les téléchargements produisent des traces dans la base de test.

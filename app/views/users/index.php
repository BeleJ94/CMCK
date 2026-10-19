<section class="page-heading users-heading">
    <div><p class="page-kicker">Administration · Sécurité</p><h2>Utilisateurs</h2><p>Créez un compte et configurez ses sites et son rôle dans le même formulaire.</p></div>
    <div class="hero-actions"><button type="button" class="btn-primary" data-user-create><i class="bi bi-person-plus"></i> Nouvel utilisateur</button></div>
</section>

<?php if($success):?><div class="app-alert app-alert-success"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="app-alert app-alert-error"><?=e($error)?></div><?php endif;?>

<section class="user-stat-grid" aria-label="Synthèse des utilisateurs">
    <article><span class="user-stat-icon is-blue"><i class="bi bi-people"></i></span><div><strong><?=e($stats['total'])?></strong><span>Comptes enregistrés</span></div></article>
    <article><span class="user-stat-icon is-green"><i class="bi bi-person-check"></i></span><div><strong><?=e($stats['active'])?></strong><span>Utilisateurs actifs</span></div></article>
    <article><span class="user-stat-icon is-orange"><i class="bi bi-hourglass-split"></i></span><div><strong><?=e($stats['pending'])?></strong><span>Comptes en attente</span></div></article>
    <article><span class="user-stat-icon is-slate"><i class="bi bi-shield-exclamation"></i></span><div><strong><?=e($stats['without_role'])?></strong><span>Sans rôle actif</span></div></article>
</section>

<section class="table-panel users-table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-person-gear"></i></span><div><h3>Répertoire des utilisateurs</h3><p>La recherche, le tri et la pagination sont disponibles dans le tableau.</p></div><a class="panel-action" href="<?=e(base_url('access-control'))?>"><i class="bi bi-shield-lock"></i> Gérer les rôles</a></div>
    <div class="table-responsive"><table class="users-directory-table" data-search-placeholder="Rechercher un nom, e-mail, site ou statut"><thead><tr><th>Utilisateur</th><th>Contact</th><th>Sites autorisés</th><th>Rôles et accès</th><th>Dernière connexion</th><th>Statut</th><th data-sortable="false">Actions</th></tr></thead><tbody>
    <?php foreach($users as$account):
        $siteLabels=array_filter(explode('||',(string)$account['site_names']));
        $roleGroups=[];foreach($account['assignments'] as$assignment){$roleGroups[$assignment['role_name']][]=$assignment;}
        $payload=['assignments'=>$account['assignments'],'id'=>(int)$account['id'],'name'=>$account['name'],'email'=>$account['email'],'phone'=>$account['phone'],'status'=>$account['status'],'site_ids'=>array_values(array_filter(array_map('intval',explode(',',(string)$account['site_ids']))))];
    ?>
        <tr>
            <td><div class="user-cell"><span><?=e(strtoupper(substr($account['name'],0,1)))?></span><div><strong><?=e($account['name'])?></strong><small>Créé le <?=e(date('d/m/Y',strtotime($account['created_at'])))?></small></div></div></td>
            <td><a href="mailto:<?=e($account['email'])?>"><?=e($account['email'])?></a><small class="table-subline"><?=e($account['phone']?:'Téléphone non renseigné')?></small></td>
            <td class="user-sites-cell"><div class="site-chip-list"><?php if(!$siteLabels):?><span class="site-chip is-empty">Aucun site</span><?php else:foreach(array_slice($siteLabels,0,2) as$siteLabel):?><span class="site-chip"><?=e(explode(' — ',$siteLabel)[0])?></span><?php endforeach;endif;?></div><?php if(count($siteLabels)>2):?><details class="user-access-details"><summary>+ <?=count($siteLabels)-2?> autres sites</summary><ul><?php foreach($siteLabels as$siteLabel):?><li><?=e($siteLabel)?></li><?php endforeach;?></ul></details><?php endif;?></td>
            <td class="user-roles-cell"><?php if(!$roleGroups):?><span class="ui-status ui-status--warning">Accès à configurer</span><?php else:?><div class="user-role-summary"><?php foreach(array_slice(array_keys($roleGroups),0,2) as$roleName):?><strong><?=e($roleName)?></strong><?php endforeach;?><?php if(count($roleGroups)>2):?><span>+ <?=count($roleGroups)-2?> autres rôles</span><?php endif;?></div><?php $pendingRoles=count(array_filter($account['assignments'],static fn($a)=>$a['approval_status']==='pending'));if($pendingRoles):?><span class="ui-status ui-status--warning"><?=$pendingRoles?> à approuver</span><?php endif;?><details class="user-access-details"><summary>Voir les accès · <?=count($account['assignments'])?> attribution(s)</summary><ul><?php foreach($roleGroups as$roleName=>$assignments):?><li><strong><?=e($roleName)?></strong><?php foreach($assignments as$assignment):?><small><?=e($assignment['site_name'])?> · <?=$assignment['approval_status']==='pending'?'À approuver':(strtotime($assignment['starts_at'])>time()?'Planifié':'Approuvé')?></small><?php endforeach;?></li><?php endforeach;?></ul></details><?php endif;?></td>
            <td><?=e($account['last_login_at']?date('d/m/Y H:i',strtotime($account['last_login_at'])):'Jamais')?></td>
            <td><span class="status-badge status-<?=e(in_array($account['status'],['active','validated'],true)?'active':($account['status']==='pending'?'pending':'inactive'))?>"><?=e(['active'=>'Actif','inactive'=>'Inactif','pending'=>'En attente','validated'=>'Validé','cancelled'=>'Annulé'][$account['status']]??$account['status'])?></span></td>
            <td><button type="button" class="btn-secondary user-edit-action" title="Modifier l’utilisateur" aria-label="Modifier l’utilisateur" data-user-edit data-user='<?=e(json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>'><i class="bi bi-pencil-square" aria-hidden="true"></i> Modifier</button></td>
        </tr>
    <?php endforeach;?>
    <?php if(!$users):?><tr><td colspan="7">Aucun utilisateur enregistré.</td></tr><?php endif;?>
    </tbody></table></div>
</section>

<div class="entity-modal-backdrop" data-user-modal-close></div>
<section class="entity-modal user-account-modal" role="dialog" aria-modal="true" aria-labelledby="userModalTitle" aria-hidden="true" data-user-modal data-create-action="<?=e(base_url('users'))?>" data-update-pattern="<?=e(base_url('users/__id__/update'))?>">
    <header><span class="entity-modal-icon"><i class="bi bi-person-plus" data-user-modal-icon></i></span><div><p class="section-label">ADMINISTRATION DU COMPTE</p><h2 id="userModalTitle" data-user-modal-title>Nouvel utilisateur</h2><p data-user-modal-subtitle>Identité, sites et rôle : configurez les accès avant d’enregistrer.</p></div><button type="button" class="modal-close" data-user-modal-close aria-label="Fermer"><i class="bi bi-x-lg"></i></button></header>
    <form method="post" action="<?=e(base_url('users'))?>" data-user-form data-confirm-title="Enregistrer l’utilisateur" data-confirm-message="Vérifiez l’identité, le statut, les sites et le rôle avant de continuer.">
        <?=csrf_field()?>
        <div class="entity-modal-body">
            <section class="modal-form-section"><div class="modal-form-section-head"><i class="bi bi-person-vcard"></i><div><strong>1 · Identité et connexion</strong><span>Informations utilisées pour la connexion et les notifications.</span></div></div><div class="form-grid">
                <label><span>Nom complet *</span><input name="name" maxlength="150" autocomplete="name" required></label>
                <label><span>Adresse e-mail *</span><input name="email" type="email" maxlength="190" autocomplete="email" required></label>
                <label><span>Téléphone</span><input name="phone" maxlength="50" autocomplete="tel"></label>
                <label><span>Statut du compte *</span><select name="status" required><option value="active">Actif</option><option value="pending">En attente</option><option value="inactive">Inactif</option><option value="validated">Validé</option><option value="cancelled">Annulé</option></select></label>
                <label class="field-wide"><span data-user-password-label>Mot de passe temporaire *</span><input name="password" type="password" minlength="8" autocomplete="new-password" data-user-password><small data-user-password-help>Au moins 8 caractères.</small></label>
            </div></section>
            <section class="modal-form-section"><div class="modal-form-section-head"><i class="bi bi-geo-alt"></i><div><strong>2 · Sites accessibles</strong><span>Cochez les sites où cette personne travaille. Le rôle sera attribué sur l’un de ces sites.</span></div></div><div class="site-choice-grid">
                <?php foreach($sites as$site):?><label><input type="checkbox" name="site_ids[]" value="<?=e($site['id'])?>"><span><strong><?=e($site['code'])?></strong><small><?=e($site['name'])?></small></span></label><?php endforeach;?>
            </div></section>
            <section class="modal-form-section"><div class="modal-form-section-head"><i class="bi bi-shield-check" aria-hidden="true"></i><div><strong>3 · Rôle et permissions</strong><span>Un rôle définit ce que la personne peut consulter, saisir et valider.</span></div></div>
                <div data-user-current-access hidden><strong>Accès déjà attribués</strong><ul data-user-current-roles></ul></div>
                <?php if(Auth::can('rbac','administer')):?><div class="form-grid">
                <label class="field-wide"><span>Configurer les accès</span><select name="access_mode" data-user-access-mode><option value="now">Attribuer un rôle maintenant</option><option value="later">Ne pas ajouter de rôle pour le moment</option></select></label>
                <label data-user-role-field><span>Rôle à attribuer *</span><select name="account_role_id" data-user-role><option value="">Choisir un rôle…</option><?php foreach($roles as$role):?><option value="<?=e($role['id'])?>" data-sensitive="<?=(int)$role['is_sensitive']?>"><?=e($role['name'])?><?=$role['is_sensitive']?' · Seconde approbation requise':''?></option><?php endforeach;?></select></label>
                <label data-user-role-field><span>Site de ce rôle *</span><select name="account_role_site_id" data-user-role-site><option value="">Cochez d’abord un site ci-dessus</option><?php foreach($sites as$site):?><option value="<?=e($site['id'])?>"><?=e($site['code'].' · '.$site['name'])?></option><?php endforeach;?></select></label>
                </div><?php else:?><p class="permission-notice">Vous pouvez gérer le compte et ses sites. Un administrateur des rôles doit configurer ses permissions.</p><?php endif;?>
                <p class="app-alert" data-user-access-preview role="status"></p>
                <?php if(Auth::can('rbac','administer')):?><details class="user-advanced-access" data-user-advanced-access><summary>Gérer les approbations et les accès avancés</summary>
                <p>Les changements ci-dessous seront appliqués avec « Enregistrer les modifications ». Les rôles sensibles doivent être approuvés par une autre personne.</p>
                <div data-user-access-decisions></div>
                <fieldset data-user-role-field><legend>Options du rôle à attribuer</legend><div class="form-grid">
                <label><span>Nature de l’accès</span><select name="account_assignment_type"><option value="direct">Accès permanent</option><option value="delegation">Délégation temporaire</option></select></label>
                <label><span>Début de l’accès</span><input type="datetime-local" name="account_starts_at"><small>Vide : dès l’enregistrement.</small></label>
                <label><span>Expiration</span><input type="datetime-local" name="account_expires_at"></label>
                <label><span>Motif de l’attribution</span><input name="account_role_reason" maxlength="255"></label>
                </div></fieldset></details><?php endif;?>
            </section>
        </div>
        <footer><button type="button" class="btn-secondary" data-user-modal-close>Annuler</button><button class="btn-primary"><i class="bi bi-check2"></i><span data-user-submit-label>Créer l’utilisateur</span></button></footer>
    </form>
</section>

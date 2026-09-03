<section class="page-heading users-heading">
    <div><p class="page-kicker">Administration · Sécurité</p><h2>Utilisateurs</h2><p>Créez les comptes, affectez leurs sites puis attribuez les rôles dans le contrôle d’accès.</p></div>
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
    <div class="table-responsive"><table data-search-placeholder="Rechercher un nom, e-mail, site ou statut"><thead><tr><th>Utilisateur</th><th>Contact</th><th>Sites autorisés</th><th>Rôles actifs</th><th>Dernière connexion</th><th>Statut</th><th data-sortable="false">Actions</th></tr></thead><tbody>
    <?php foreach($users as$account):
        $siteLabels=array_filter(explode('||',(string)$account['site_names']));
        $payload=['id'=>(int)$account['id'],'name'=>$account['name'],'email'=>$account['email'],'phone'=>$account['phone'],'status'=>$account['status'],'site_ids'=>array_values(array_filter(array_map('intval',explode(',',(string)$account['site_ids']))))];
    ?>
        <tr>
            <td><div class="user-cell"><span><?=e(strtoupper(substr($account['name'],0,1)))?></span><div><strong><?=e($account['name'])?></strong><small>Créé le <?=e(date('d/m/Y',strtotime($account['created_at'])))?></small></div></div></td>
            <td><a href="mailto:<?=e($account['email'])?>"><?=e($account['email'])?></a><small class="table-subline"><?=e($account['phone']?:'Téléphone non renseigné')?></small></td>
            <td><div class="site-chip-list"><?php if(!$siteLabels):?><span class="site-chip is-empty">Aucun site</span><?php else:foreach($siteLabels as$siteLabel):?><span class="site-chip"><?=e(explode(' — ',$siteLabel)[0])?></span><?php endforeach;endif;?></div></td>
            <td><strong><?=e($account['active_roles'])?></strong><small class="table-subline"><?=e($account['legacy_role_name']?:'Aucun rôle historique')?></small></td>
            <td><?=e($account['last_login_at']?date('d/m/Y H:i',strtotime($account['last_login_at'])):'Jamais')?></td>
            <td><span class="status-badge status-<?=e(in_array($account['status'],['active','validated'],true)?'active':($account['status']==='pending'?'pending':'inactive'))?>"><?=e(['active'=>'Actif','inactive'=>'Inactif','pending'=>'En attente','validated'=>'Validé','cancelled'=>'Annulé'][$account['status']]??$account['status'])?></span></td>
            <td><button type="button" class="icon-button" title="Modifier l’utilisateur" data-user-edit data-user='<?=e(json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>'><i class="bi bi-pencil-square"></i></button></td>
        </tr>
    <?php endforeach;?>
    <?php if(!$users):?><tr><td colspan="7">Aucun utilisateur enregistré.</td></tr><?php endif;?>
    </tbody></table></div>
</section>

<div class="entity-modal-backdrop" data-user-modal-close></div>
<section class="entity-modal user-account-modal" role="dialog" aria-modal="true" aria-labelledby="userModalTitle" aria-hidden="true" data-user-modal data-create-action="<?=e(base_url('users'))?>" data-update-pattern="<?=e(base_url('users/__id__/update'))?>">
    <header><span class="entity-modal-icon"><i class="bi bi-person-plus" data-user-modal-icon></i></span><div><p class="section-label">ADMINISTRATION DU COMPTE</p><h2 id="userModalTitle" data-user-modal-title>Nouvel utilisateur</h2><p data-user-modal-subtitle>Le compte sera créé sans permission métier.</p></div><button type="button" class="modal-close" data-user-modal-close aria-label="Fermer"><i class="bi bi-x-lg"></i></button></header>
    <form method="post" action="<?=e(base_url('users'))?>" data-user-form data-confirm-title="Enregistrer l’utilisateur" data-confirm-message="Vérifiez l’identité, le statut et les sites avant de continuer.">
        <?=csrf_field()?>
        <div class="entity-modal-body">
            <section class="modal-form-section"><div class="modal-form-section-head"><i class="bi bi-person-vcard"></i><div><strong>Identité et accès</strong><span>Informations utilisées pour la connexion et les notifications.</span></div></div><div class="form-grid">
                <label><span>Nom complet *</span><input name="name" maxlength="150" autocomplete="name" required></label>
                <label><span>Adresse e-mail *</span><input name="email" type="email" maxlength="190" autocomplete="email" required></label>
                <label><span>Téléphone</span><input name="phone" maxlength="50" autocomplete="tel"></label>
                <label><span>Statut du compte *</span><select name="status" required><option value="pending">En attente</option><option value="active">Actif</option><option value="inactive">Inactif</option><option value="validated">Validé</option><option value="cancelled">Annulé</option></select></label>
                <label class="field-wide"><span data-user-password-label>Mot de passe temporaire *</span><input name="password" type="password" minlength="8" autocomplete="new-password" data-user-password><small data-user-password-help>Au moins 8 caractères. Aucun rôle métier ne sera attribué automatiquement.</small></label>
            </div></section>
            <section class="modal-form-section"><div class="modal-form-section-head"><i class="bi bi-geo-alt"></i><div><strong>Sites accessibles</strong><span>Cette affectation définit le périmètre, pas les permissions métier.</span></div></div><div class="site-choice-grid">
                <?php foreach($sites as$site):?><label><input type="checkbox" name="site_ids[]" value="<?=e($site['id'])?>"><span><strong><?=e($site['code'])?></strong><small><?=e($site['name'])?></small></span></label><?php endforeach;?>
            </div></section>
            <div class="permission-notice"><i class="bi bi-shield-lock"></i><div><strong>Permissions séparées</strong><span>Après création, utilisez « Gérer les rôles » pour accorder une fonction, une période et un site.</span></div></div>
        </div>
        <footer><button type="button" class="btn-secondary" data-user-modal-close>Annuler</button><button class="btn-primary"><i class="bi bi-check2"></i><span data-user-submit-label>Créer l’utilisateur</span></button></footer>
    </form>
</section>

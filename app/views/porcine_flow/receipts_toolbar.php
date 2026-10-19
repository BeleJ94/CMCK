<section class="slaughter-register-tools" aria-label="Recherche et gestion des réceptions">
<div class="ui-filterbar slaughter-register-filters receipts-register-filters" role="search" aria-label="Filtrer les réceptions">
<label class="ui-filterbar__search"><span>Rechercher une réception</span><span class="slaughter-register-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" data-flow-search placeholder="Référence, ferme, lot ou transport…"></span></label>
<label><span>Étape</span><select data-flow-state><option value="">Toutes les étapes</option><option value="incoming">À réceptionner</option><option value="pending">Contrôle à effectuer</option></select></label>
<label><span>Lignes par page</span><select data-flow-page-size><option value="10" selected>10</option><option value="25">25</option><option value="50">50</option></select></label>
<button type="button" class="btn-secondary" data-flow-reset><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Réinitialiser</button>
<a class="btn-secondary" href="<?=e(base_url('butchery/history/receipts'))?>"><i class="bi bi-clock-history" aria-hidden="true"></i> Autres livraisons et historique</a>
</div>
<div class="slaughter-register-context"><div class="ui-filterbar__summary"><strong data-flow-count aria-live="polite"></strong><span aria-hidden="true">·</span><small data-flow-filter-count></small></div><p><i class="bi bi-info-circle" aria-hidden="true"></i> Arrivée → contrôle sanitaire → entrée en chambre froide.</p></div>
</section>
<p class="flow-empty" data-flow-filter-empty role="status" hidden>Aucune réception pour ces filtres. Modifiez la recherche ou réinitialisez les filtres.</p>
<p class="app-alert alert-info" data-flow-filter-update role="status" hidden><i class="bi bi-info-circle" aria-hidden="true"></i> Des réceptions nouvelles ou modifiées sont masquées par vos filtres. <button type="button" class="btn-secondary" data-flow-reveal-results>Afficher tous les résultats</button></p>

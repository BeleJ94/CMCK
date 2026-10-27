<?php // Secondary row actions in a native popover menu: expects $menuId, $menuLabel and $menuItems [label, icon, modal id or URL, tone, is link, optional data-* attributes]. ?>
<button type="button" class="row-menu-toggle" id="<?=e($menuId)?>-toggle" popovertarget="<?=e($menuId)?>" aria-haspopup="menu" aria-expanded="false" aria-label="<?=e('Plus d’actions · '.$menuLabel)?>" title="Plus d’actions"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
<div id="<?=e($menuId)?>" class="row-menu" popover="auto" role="menu" data-row-menu data-toggle-id="<?=e($menuId)?>-toggle" aria-label="<?=e('Actions · '.$menuLabel)?>">
<?php foreach($menuItems as $item):[$label,$icon,$target,$tone,$link]=$item;$attributes='';foreach($item[5]??[] as $name=>$value)$attributes.=' data-'.e($name).'="'.e($value).'"'; ?>
<?php if($link): ?><a role="menuitem" href="<?=e($target)?>" target="_blank" rel="noopener" data-ajax="false"<?=$tone?' class="is-'.e($tone).'"':''?><?=$attributes?>><i class="bi <?=e($icon)?>" aria-hidden="true"></i><span><?=e($label)?></span></a>
<?php else: ?><button type="button" role="menuitem" data-workspace-modal-open="<?=e($target)?>"<?=$tone?' class="is-'.e($tone).'"':''?><?=$attributes?>><i class="bi <?=e($icon)?>" aria-hidden="true"></i><span><?=e($label)?></span></button><?php endif; ?>
<?php endforeach; ?>
</div>

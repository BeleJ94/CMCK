<svg class="finished-palette" viewBox="0 0 260 190" aria-hidden="true" focusable="false">
<ellipse cx="130" cy="166" rx="113" ry="14" fill="#18374a" opacity=".07"/>
<path d="M28 140 203 132 238 147 64 158Z" fill="#d3ad77"/>
<path d="M28 140v13l36 17v-12Z" fill="#a77e50"/>
<path d="m64 158 174-11v13L64 171Z" fill="#bb915e"/>
<path d="m73 159 21-1v10l-21 1zm63-4 21-1v10l-21 1zm64-4 21-1v10l-21 1z" fill="#785b3d"/>
<path d="m50 139 36 16m-2-18 36 16m-2-18 36 16m-2-18 36 16m-2-18 36 16" stroke="#af8655" stroke-width="3"/>
<?php if($state!=='empty'): ?>
<?php foreach([[48,91],[104,88],[160,85],[75,43],[132,40]] as [$x,$y]): ?>
<g transform="translate(<?=$x?> <?=$y?>)">
<path d="M9 6Q28 1 46 6l-3 7q12 16 8 34-1 8-24 9Q3 56 2 47-2 29 12 13Z" fill="var(--sack-fill)" stroke="var(--sack-line)" stroke-width="1.8"/>
<path d="m10 10 34 0M8 46q17 6 37 0" fill="none" stroke="var(--sack-line)" opacity=".5" stroke-width="2"/>
<rect x="13" y="24" width="28" height="17" rx="3" fill="var(--stock-accent)" opacity=".85"/>
<?php if(isset($paletteLabel)): ?><text x="27" y="35" text-anchor="middle" fill="white" font-size="7" font-family="Arial,sans-serif" font-weight="700"><?=e($paletteLabel)?></text><?php else: ?><path d="M21 29h12m-12 6h8" stroke="white" stroke-width="2" stroke-linecap="round"/><?php endif; ?>
</g>
<?php endforeach; endif; ?>
</svg>

<?php
$svgId = 'silo-visual-' . (int) $silo['id'];
$level = max(0, min(100, $silo['occupation'] ?? 0));
$fillHeight = 170 * $level / 100;
$fillTop = 242 - $fillHeight;
?>
<svg class="silo-illustration" viewBox="0 0 280 310" role="img" aria-labelledby="<?= e($svgId) ?>-title <?= e($svgId) ?>-description">
    <title id="<?= e($svgId) ?>-title"><?= e($silo['name']) ?> : <?= e($percent($silo['occupation'])) ?></title>
    <desc id="<?= e($svgId) ?>-description"><?= e($silo['health_label']) ?>. Stock : <?= e($quantity($silo['current_stock_kg'])) ?> <?= e($unit) ?>. Capacité : <?= e($quantity($silo['capacity_kg'])) ?> <?= e($unit) ?>. <?= $silo['occupation'] === null ? 'Capacité non renseignée.' : 'Le niveau représente le pourcentage de la capacité ; les dépassements restent affichés en chiffres.' ?></desc>
    <defs>
        <linearGradient id="<?= e($svgId) ?>-metal" x1="0" x2="1"><stop offset="0" stop-color="#d2dfe4"/><stop offset=".32" stop-color="#f8fbfc"/><stop offset=".65" stop-color="#edf3f5"/><stop offset="1" stop-color="#bbcdd5"/></linearGradient>
        <linearGradient id="<?= e($svgId) ?>-roof" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#f6fafb"/><stop offset="1" stop-color="#b6cbd4"/></linearGradient>
        <clipPath id="<?= e($svgId) ?>-chamber"><rect x="68" y="72" width="144" height="170" rx="3"/></clipPath>
    </defs>
    <ellipse cx="140" cy="293" rx="97" ry="9" fill="#d6e1e6" opacity=".55"/>
    <path d="M74 244V286M206 244V286" stroke="#879ea9" stroke-width="9"/>
    <path d="M73 283H87M193 283H207" stroke="#718c99" stroke-width="5" stroke-linecap="round"/>
    <path d="M103 247L140 274L177 247" fill="url(#<?= e($svgId) ?>-metal)" stroke="#93abb6" stroke-width="1.5"/>
    <path d="M140 273V284H154" fill="none" stroke="#839da9" stroke-width="7" stroke-linejoin="round"/>
    <path d="M61 70H219V239Q219 255 140 255Q61 255 61 239Z" fill="url(#<?= e($svgId) ?>-metal)" stroke="#9ab0ba" stroke-width="1.5"/>
    <rect x="68" y="72" width="144" height="170" rx="3" fill="#f3f7f9" opacity=".8"/>
    <g clip-path="url(#<?= e($svgId) ?>-chamber)">
        <rect class="silo-grain" x="68" y="<?= e($fillTop) ?>" width="144" height="<?= e($fillHeight) ?>" fill="currentColor" opacity=".8"/>
        <?php if ($level > 0): ?><path d="M68 <?= e($fillTop) ?>H212" stroke="currentColor" stroke-width="3"/><?php endif; ?>
        <path d="M68 106H212M68 140H212M68 174H212M68 208H212" stroke="#567482" stroke-opacity=".13"/>
        <path d="M87 72V242" stroke="#fff" stroke-width="10" opacity=".25"/>
    </g>
    <path d="M58 70L140 27L222 70Q140 84 58 70Z" fill="url(#<?= e($svgId) ?>-roof)" stroke="#9ab0ba" stroke-width="1.5"/>
    <path d="M140 29L99 73M140 29L181 73" stroke="#a7bcc6" stroke-width="1"/>
    <rect x="127" y="20" width="26" height="10" rx="3" fill="#93acb8"/>
    <path d="M230 83V266M240 83V266M230 98H240M230 117H240M230 136H240M230 155H240M230 174H240M230 193H240M230 212H240M230 231H240M230 250H240" stroke="#9bb0bb" stroke-width="2"/>
    <path d="M40 72V242M36 72H45M36 157H45M36 242H45" stroke="#bcccd4" stroke-width="1.5"/>
    <text x="29" y="76" text-anchor="end" class="silo-scale">100</text><text x="29" y="161" text-anchor="end" class="silo-scale">50</text><text x="29" y="246" text-anchor="end" class="silo-scale">0</text>
    <rect x="81" y="141" width="118" height="47" rx="8" fill="#fff" fill-opacity=".95" stroke="#dbe5e9"/>
    <text x="140" y="163" text-anchor="middle" class="silo-svg-percentage"><?= e($percent($silo['occupation'])) ?></text>
    <text x="140" y="178" text-anchor="middle" class="silo-svg-caption"><?= $silo['occupation'] === null ? 'capacité inconnue' : 'occupation' ?></text>
</svg>

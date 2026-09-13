<svg class="depot-illustration" viewBox="0 0 520 245" aria-hidden="true" focusable="false">
<rect width="520" height="245" fill="#edf4f5"/><circle cx="444" cy="45" r="23" fill="#f2d995"/>
<path d="M0 183Q130 119 265 174T520 152V245H0Z" fill="#d5e5d8"/><ellipse cx="265" cy="224" rx="202" ry="13" fill="#bdcfc5"/>
<path d="M91 104L252 40L416 104V220H91Z" fill="#e0e8e5" stroke="#718e84" stroke-width="2"/>
<path d="M252 40L416 104V220H350V105Z" fill="#c9d8d2"/>
<path d="M76 104L252 29L431 103L419 117L252 48L88 117Z" fill="#365e56"/>
<rect x="140" y="112" width="173" height="108" rx="3" fill="#476158"/>
<path d="M143 217V116H310V217" fill="none" stroke="#a9bdb4" stroke-width="5"/>
<rect x="329" y="130" width="46" height="43" rx="3" fill="#eef6f4" stroke="#90a99d" stroke-width="3"/><path d="M352 131V173M330 152H374" stroke="#90a99d" stroke-width="2"/>
<rect x="174" y="74" width="106" height="25" rx="4" fill="#fff"/><text x="227" y="91" text-anchor="middle" fill="#365e56" font-family="sans-serif" font-size="12" font-weight="600">DÉPÔT AGRICOLE</text>
<?php for($row=0;$row<2;$row++):for($col=0;$col<4;$col++):$bagColor=$depot['physical']<=0?'#b8c2ba':'#c7ba99';?>
<g transform="translate(<?=155+$col*37?> <?=151+$row*32?>)"><path d="M5 0H22L19 7Q32 29 14 30Q-4 29 8 7Z" fill="<?=$bagColor?>" stroke="#e5eee7" stroke-width="1"/><path d="M8 7H20M14 13V23" stroke="#56765f" stroke-width="1.5"/></g>
<?php endfor;endfor;?>
<path d="M142 220H314" stroke="#9c8056" stroke-width="6"/><path d="M65 197V222M56 213H74" stroke="#71866a" stroke-width="4"/><circle cx="65" cy="188" r="16" fill="#84a88a"/>
</svg>

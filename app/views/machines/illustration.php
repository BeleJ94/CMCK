<?php $machineTone=in_array($machine['status'],['active','validated'],true)?'#347d70':'#85949c'; ?>
<svg class="machine-illustration" viewBox="0 0 400 190" aria-hidden="true" focusable="false">
<rect width="400" height="190" fill="#edf4f5"/><path d="M30 166H370" stroke="#ccdadd" stroke-width="3"/><ellipse cx="205" cy="166" rx="135" ry="9" fill="#d5e2e1"/>
<?php if($machine['machine_type']==='waste'):?>
<path d="M100 38H197L181 83H118Z" fill="#c7d9d4" stroke="<?=$machineTone?>" stroke-width="3"/><rect x="110" y="82" width="190" height="65" rx="12" fill="<?=$machineTone?>"/><circle cx="150" cy="114" r="24" fill="#d5e5df"/><circle cx="150" cy="114" r="11" fill="#6a8c82"/><path d="M278 98H330V143H310V116H278" fill="#93b7ac"/><path d="M120 147V165M282 147V165" stroke="#536d6c" stroke-width="10"/><g fill="#c7a66a"><ellipse cx="319" cy="152" rx="6" ry="3"/><ellipse cx="333" cy="160" rx="6" ry="3"/><ellipse cx="308" cy="162" rx="6" ry="3"/></g>
<?php else:?>
<path d="M130 26H259L243 64H148Z" fill="#d3dfd9" stroke="<?=$machineTone?>" stroke-width="3"/><rect x="125" y="63" width="140" height="82" rx="8" fill="<?=$machineTone?>"/><rect x="146" y="80" width="98" height="42" rx="5" fill="#e5edeb"/><path d="M153 91H237M153 102H237M153 113H237" stroke="#8ba69c" stroke-width="4"/><rect x="162" y="145" width="65" height="13" fill="#91ada2"/><path d="M135 145V167M255 145V167" stroke="#536d6c" stroke-width="9"/><rect x="76" y="111" width="49" height="27" rx="8" fill="#adc3bc"/><path d="M285 124H311L305 135Q330 170 297 170Q268 170 291 135Z" fill="#cbb589"/><path d="M292 135H305" stroke="#8a7857" stroke-width="3"/>
<?php endif;?>
</svg>

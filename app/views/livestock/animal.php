<?php
$animalName=mb_strtolower($b['species_name']);
$animal=$b['category']==='fish'?'fish':($b['category']==='poultry'?'bird':(preg_match('/porc|cochon|pig/',$animalName)?'pig':(preg_match('/bovin|vache|boeuf|bœuf|cattle/',$animalName)?'cow':'other')));
?>
<svg class="lot-animal" viewBox="0 0 220 150" fill="currentColor" aria-hidden="true" focusable="false">
<?php if($animal==='cow'): ?>
<ellipse cx="111" cy="131" rx="88" ry="4" fill="#ded8c9" opacity=".65"/>
<!-- Far legs, with natural dark hooves. -->
<path d="M53 80 48 103 43 122 54 122 61 103 72 84M126 81 125 99 133 123 143 123 138 96 141 81" fill="#70594b"/>
<path d="m43 120-2 7h14l-1-7m79 0-1 7h13l-2-7" fill="#413c35"/>
<!-- Body and near legs. -->
<path d="M30 59Q34 48 53 47L113 49Q134 49 145 40L157 42 166 53 157 78 151 94 151 118 157 122 156 126 142 126 139 96Q101 104 68 92L58 105 56 121 60 126 45 126 44 104 48 84 34 78Z" fill="#b29b7e"/>
<path d="M37 53Q53 46 71 50L78 66 67 81 72 94 60 91 50 83 35 77ZM97 50 114 51 130 47 134 60 123 77 107 82 95 72Z" fill="#eee6d7"/>
<path d="M73 93Q106 99 139 90L141 96Q104 104 68 92Z" fill="#91785f"/>
<path d="M34 57Q25 62 24 77L21 101" stroke="#80674f" stroke-width="3" fill="none"/><path d="m21 97-4 12 6 1 2-12" fill="#50483b"/>
<!-- Neck, profile and ear; no expressive facial treatment. -->
<path d="M140 43 154 38 170 44 182 52 200 65 200 75 188 80 172 67 158 66 151 83 144 80Z" fill="#987b5f"/>
<path d="m160 43-3-14 10 11 8 5" fill="#c7b99c"/><path d="m158 47-14-4 8 12 11 1" fill="#745c47"/>
<path d="M170 47 178 51 187 62 193 68 187 72 176 61Z" fill="#eee6d7"/>
<path d="m191 65 10 1 2 8-13 7-7-8" fill="#a79180"/>
<circle cx="177" cy="57" r="1.5" fill="#352f29"/><path d="m197 72-3 1" stroke="#5d4b3d" stroke-width="1.5" stroke-linecap="round"/>
<path d="m45 121-1 6h16l-4-6m86-1v7h16l-2-5" fill="#494139"/>
<?php elseif($animal==='pig'): ?>
<ellipse cx="110" cy="130" rx="85" ry="4" fill="#dcd3cd" opacity=".65"/>
<path d="M32 64Q39 46 75 45L128 47Q143 49 155 60L167 44 168 63 181 69 196 76 203 75 205 90 187 94 174 91 157 96 153 115 159 123 145 123 143 103 132 101 128 117 133 123 119 123 120 103 76 105 64 100 58 116 63 123 49 123 48 105 43 97 39 115 43 123 30 123 32 100 30 89Q23 76 32 64Z" fill="#b49a94"/>
<path d="M39 62Q63 47 98 51L128 54Q143 57 148 69 118 61 99 66 69 72 38 78Z" fill="#cbb7af"/>
<path d="M40 88Q88 107 148 87L159 92 151 101 133 102 120 104 75 105 59 97Z" fill="#9b7e78"/>
<path d="m155 60 12-16 1 19-8 10Z" fill="#94736e"/>
<path d="m185 77 17-2 3 15-18 4-7-7Z" fill="#a17d76"/>
<circle cx="174" cy="75" r="1.5" fill="#403632"/><path d="m198 82-1 4" stroke="#71534e" stroke-width="2" stroke-linecap="round"/>
<path d="M32 68Q12 58 17 48Q21 40 27 49Q29 57 21 56" fill="none" stroke="#9b7e78" stroke-width="3" stroke-linecap="round"/>
<path d="m31 119-1 5h14l-5-5m10 0v5h15l-6-5m61 0v5h14l-5-5m17 0v5h14l-6-5" fill="#66534c"/>
<?php elseif($animal==='bird'): ?>
<ellipse cx="111" cy="133" rx="55" ry="4" fill="#ddd5c5" opacity=".65"/>
<path d="M54 86 32 50 52 59 46 40 72 62Q92 55 111 66 127 72 136 58L141 42Q141 30 153 28L156 21 161 26 167 21 170 29Q181 32 180 41L196 46 180 50 173 59 168 79Q162 98 139 106L129 108 128 123 142 127 122 128 120 110 108 109 101 123 110 127 89 127 95 108Q68 104 54 86Z" fill="#b79866"/>
<path d="m54 86-22-36 20 9-6-19 26 22 10 14Z" fill="#68594a"/>
<path d="M81 73Q105 66 135 85 117 105 89 95L73 86Z" fill="#92774f"/>
<path d="M86 81q18 0 34 7m-28 0 19 6" stroke="#c6ae83" stroke-width="2" fill="none"/>
<path d="M135 60 141 42Q141 30 153 28L170 29Q181 32 180 41L173 59 168 79 154 76Z" fill="#d0b98b"/>
<path d="m153 28 3-7 5 5 6-5 3 8Z" fill="#965951"/><path d="M174 49q5 12-2 14l-3-9Z" fill="#965951"/>
<path d="m180 41 16 5-16 4Z" fill="#95763e"/><circle cx="171" cy="39" r="1.5" fill="#3d3730"/>
<path d="m108 109-7 14 9 4H89l6-19m34 0-1 15 14 4-20 1-2-18" fill="#927341"/>
<?php elseif($animal==='fish'): ?>
<path d="M75 50 85 39 88 43 96 34 100 40 107 31 124 48 130 53M93 100 107 122 123 104M159 73 194 46 190 76 195 105 160 82" fill="#647f86"/>
<path d="M24 77Q45 51 77 48 128 41 166 69L166 83Q146 101 123 104L75 105Q43 100 24 77Z" fill="#90a6a8"/>
<path d="M24 77Q45 51 77 48 128 41 166 69L157 74Q103 49 54 73Z" fill="#6a8589"/>
<path d="M32 84Q86 102 159 84 137 106 83 103 54 100 32 84Z" fill="#c2cecb"/>
<path d="M62 57Q80 78 64 96" stroke="#647e82" stroke-width="2" fill="none"/>
<path d="m82 76 32 4-20 18Z" fill="#708c91"/>
<path d="m173 70 15-14m-14 21h13m-14 6 15 14M90 55l-2 13m13-14-2 14m13-12-2 13m13-10-2 13" stroke="#526e76" stroke-width="1" opacity=".65"/>
<circle cx="47" cy="73" r="2" fill="#344d54"/>
<?php else: ?>
<ellipse cx="110" cy="131" rx="83" ry="4" fill="#dcd6c9" opacity=".65"/>
<path d="M31 63Q43 53 68 56L131 57 148 40 155 25 165 31 162 43 181 51 198 61 193 72 178 72 163 64 153 78 146 91 145 119 152 124 138 124 135 94 123 98 117 119 123 124 108 124 109 98 63 96 54 113 53 120 59 124 44 124 44 105 48 89 39 83 34 105 29 122 19 122 25 98 27 75 18 97 14 96 24 66Z" fill="#a39175"/>
<path d="M36 65Q60 56 91 63L125 64 121 80 98 87 53 82Z" fill="#c9bda3"/><path d="m155 25 10 6-3 12-8-4Z" fill="#7c6952"/><circle cx="180" cy="55" r="1.5" fill="#423a30"/>
<?php endif; ?>
</svg>

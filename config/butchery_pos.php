<?php
return [
    'currency' => getenv('DAGRIL_POS_CURRENCY') ?: 'USD',
    'currencies' => ['USD', 'CDF'],
    'payment_methods' => ['cash'=>'Espèces','mobile_money'=>'Mobile Money','card'=>'Carte bancaire','bank_transfer'=>'Virement'],
];

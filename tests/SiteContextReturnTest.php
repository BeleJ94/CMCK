<?php
require __DIR__.'/../app/helpers/functions.php';
$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['HTTP_HOST']='localhost';
foreach([
 '/butchery/outgoing'=>'butchery/outgoing',
 '/butchery/production?status=validated#history'=>'butchery/production?status=validated#history',
 '/suppliers?q=porc%20frais'=>'suppliers?q=porc%20frais',
 'weighings/entry'=>'weighings/entry',
 'https://example.org'=>'dashboard','//example.org'=>'dashboard',
 '/%2fexample.org'=>'dashboard','/butchery/../logout'=>'dashboard',
 '/context/site'=>'dashboard',"/butchery%0d%0aLocation:evil"=>'dashboard',
] as $value=>$expected){if(site_context_return_path($value,'dashboard')!==$expected)throw new RuntimeException('Retour incorrect : '.$value);}
$_SERVER['SCRIPT_NAME']='/dagril/public/index.php';
if(site_context_return_path('/dagril/public/butchery/stocks?q=lot','dashboard')!=='butchery/stocks?q=lot')throw new RuntimeException('Sous-répertoire incorrect');
if(site_context_return_path([], 'dashboard')!=='dashboard')throw new RuntimeException('Type incorrect');
echo "OK : page courante, paramètres, ancre, sous-répertoire et refus des redirections externes.\n";

<?php
$root=dirname(__DIR__);$db=getenv('DAGRIL_DB_DATABASE')?:'';
if(!preg_match('/_(test|testing)$/i',$db)||strcasecmp($db,'cmck_milltrack')===0){fwrite(STDERR,"GARDE-FOU: DAGRIL_DB_DATABASE doit finir par _test ou _testing.\n");exit(2);}
$scenarios=require $root.'/tests/integration/scenarios.php';$executed=[];$results=[];
foreach($scenarios as$n=>$scenario){foreach($scenario[1]as$script)$executed[$script]=true;}
foreach(array_keys($executed)as$script){$path=$script==='guardrails.php'?$root.'/tests/integration/'.$script:$root.'/scripts/'.$script;if(!is_file($path)){$results[$script]=['code'=>127,'output'=>'Script absent'];continue;}$cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg($path).' 2>&1';$lines=[];$code=0;exec($cmd,$lines,$code);$results[$script]=['code'=>$code,'output'=>implode("\n",$lines)];echo PHP_EOL.'===== '.$script.' ====='.PHP_EOL.$results[$script]['output'].PHP_EOL;}
$failed=0;echo PHP_EOL.'===== MATRICE DES 18 SCÉNARIOS ====='.PHP_EOL;foreach($scenarios as$n=>$s){$ok=true;foreach($s[1]as$script)$ok=$ok&&isset($results[$script])&&$results[$script]['code']===0;echo sprintf('%s %02d. %s%s', $ok?'[OK]':'[ECHEC]',$n,$s[0],PHP_EOL);if(!$ok)$failed++;}
echo PHP_EOL.(18-$failed).'/18 scénario(s) réussi(s).'.PHP_EOL;exit($failed?1:0);

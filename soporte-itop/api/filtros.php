<?php
require_once __DIR__.'/../includes/datos.php'; require_once __DIR__.'/../includes/json.php';
$all=cargarTodo(); $t=$all['tickets'];
$vals=function($campo)use($t){$a=array_keys(contarPor($t,$campo));sort($a,SORT_NATURAL|SORT_FLAG_CASE);return $a;};
$personas=['reportado'=>[],'creador'=>[],'analista'=>[],'admin'=>[]];
foreach($t as $x){foreach(['reportado'=>'reportado_por','creador'=>'creador','analista'=>'analista','admin'=>'admin_local'] as $k=>$c){if(trim($x[$c])!=='')$personas[$k][$x[$c]]=true;}}
foreach($personas as $k=>$v){$personas[$k]=array_keys($v);sort($personas[$k],SORT_NATURAL|SORT_FLAG_CASE);}
responderJson(['ok'=>true,'data'=>['ministerios'=>$vals('ministerio'),'organismos'=>$vals('organismo'),'estados'=>$vals('estado'),'servicios'=>$vals('servicio'),'personas'=>$personas]]);

<?php
require_once __DIR__.'/../includes/datos.php'; require_once __DIR__.'/../includes/json.php';
$all=cargarTodo(); $f=filtrosRequest(); if(isset($_GET['solo_tipo'])&&$_GET['solo_tipo']!=='')$f['tipo']=$_GET['solo_tipo'];
$t=aplicarFiltros($all['tickets'],$f); $r=resumenNumerico($t);
$r['por_ministerio']=contarPor($t,'ministerio',12);$r['por_organismo']=contarPor($t,'organismo',12);$r['por_admin']=contarPor(array_values(array_filter($t,fn($x)=>$x['es_admin_local'])),'admin_local',12);$r['por_reportante']=contarPor($t,'reportado_por',12);$r['por_servicio']=contarPor($t,'servicio',12);$r['por_estado']=contarPor($t,'estado');
$r['tickets']=array_slice(array_values(array_reverse($t)),0,200);
responderJson(['ok'=>true,'data'=>$r]);

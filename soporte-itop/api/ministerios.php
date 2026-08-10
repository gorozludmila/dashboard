<?php
require_once __DIR__.'/../includes/datos.php'; require_once __DIR__.'/../includes/json.php';
$all=cargarTodo();$f=filtrosRequest();$t=aplicarFiltros($all['tickets'],$f);$res=[];
foreach($t as $x){$m=$x['ministerio'];$res[$m]??=['ministerio'=>$m,'total'=>0,'incidentes'=>0,'requerimientos'=>0,'abiertos'=>0,'cerrados'=>0];$res[$m]['total']++;$x['tipo']==='Incidente'?$res[$m]['incidentes']++:$res[$m]['requerimientos']++;$x['estado_grupo']==='Cerrado'?$res[$m]['cerrados']++:$res[$m]['abiertos']++;}
usort($res,fn($a,$b)=>$b['total']<=>$a['total']);
$detail=['organismos'=>contarPor($t,'organismo',30),'admins_incidentes'=>contarPor(array_values(array_filter($t,fn($x)=>$x['tipo']==='Incidente'&&$x['es_admin_local'])),'admin_local',30),'admins_requerimientos'=>contarPor(array_values(array_filter($t,fn($x)=>$x['tipo']==='Requerimiento'&&$x['es_admin_local'])),'admin_local',30),'resumen'=>resumenNumerico($t)];
responderJson(['ok'=>true,'data'=>['ministerios'=>$res,'detalle'=>$detail]]);

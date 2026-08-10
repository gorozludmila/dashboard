<?php
require_once __DIR__.'/../includes/datos.php'; require_once __DIR__.'/../includes/json.php';
try {
    $all=cargarTodo(); $f=filtrosRequest(); $tickets=aplicarFiltros($all['tickets'],$f); $r=resumenNumerico($tickets);
    $r['tiempo_promedio_horas']=round(tiempoPromedioHoras($tickets),1);
    $r['ministerios']=contarPor($tickets,'ministerio',12); $r['estados']=contarPor($tickets,'estado'); $r['servicios']=contarPor($tickets,'servicio',10); $r['evolucion']=evolucion($tickets);
    $admin=count(array_filter($tickets,fn($t)=>$t['es_admin_local'])); $r['origen']=['administradores'=>$admin,'otros'=>count($tickets)-$admin];
    responderJson(['ok'=>true,'data'=>$r]);
} catch(Throwable $e){responderJson(['ok'=>false,'error'=>$e->getMessage()],500);}

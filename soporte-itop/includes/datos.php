<?php
require_once __DIR__ . '/config.php';

function sinBOM(string $s): string {
    return preg_replace('/^\xEF\xBB\xBF/', '', $s) ?? $s;
}

function normalizarTexto(string $s): string {
    $s = trim($s);
    $convLower = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = strtolower($convLower !== false ? $convLower : $s);
    $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($conv !== false) $s = $conv;
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? $s;
    return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
}

function leerCsv(string $ruta): array {
    if (!is_file($ruta)) return [];
    $fh = fopen($ruta, 'r');
    if (!$fh) return [];
    $header = fgetcsv($fh);
    if (!$header) { fclose($fh); return []; }
    $header[0] = sinBOM((string)$header[0]);
    $rows = [];
    while (($row = fgetcsv($fh)) !== false) {
        if (count($row) !== count($header)) continue;
        $rows[] = array_combine($header, $row);
    }
    fclose($fh);
    return $rows;
}

function excelColIndex(string $letters): int {
    $n = 0;
    foreach (str_split($letters) as $c) $n = $n * 26 + (ord($c) - 64);
    return $n - 1;
}

function xmlText(string $s): string {
    return html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

// Lector XLSX liviano sin Composer. Requiere la extensión PHP ZipArchive (php8.3-zip en Ubuntu).
function leerXlsxPrimeraHoja(string $ruta): array {
    if (!is_file($ruta) || !class_exists('ZipArchive')) return [];
    $zip = new ZipArchive();
    if ($zip->open($ruta) !== true) return [];

    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        if (preg_match_all('/<si\b[^>]*>(.*?)<\/si>/s', $sharedXml, $sis)) {
            foreach ($sis[1] as $si) {
                $parts = [];
                if (preg_match_all('/<t\b[^>]*>(.*?)<\/t>/s', $si, $ts)) {
                    foreach ($ts[1] as $t) $parts[] = xmlText($t);
                }
                $shared[] = implode('', $parts);
            }
        }
    }

    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheet === false) return [];

    $matrix = [];
    if (preg_match_all('/<row\b[^>]*r="(\d+)"[^>]*>(.*?)<\/row>/s', $sheet, $rows, PREG_SET_ORDER)) {
        foreach ($rows as $r) {
            $cells = [];
            if (preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/s', $r[2], $cs, PREG_SET_ORDER)) {
                foreach ($cs as $c) {
                    $attrs = $c[1]; $body = $c[2];
                    if (!preg_match('/\br="([A-Z]+)\d+"/', $attrs, $ref)) continue;
                    $idx = excelColIndex($ref[1]);
                    $type = preg_match('/\bt="([^"]+)"/', $attrs, $tm) ? $tm[1] : '';
                    $val = '';
                    if ($type === 'inlineStr') {
                        if (preg_match_all('/<t\b[^>]*>(.*?)<\/t>/s', $body, $tm2)) $val = implode('', array_map('xmlText', $tm2[1]));
                    } elseif (preg_match('/<v>(.*?)<\/v>/s', $body, $vm)) {
                        $raw = xmlText($vm[1]);
                        $val = ($type === 's' && isset($shared[(int)$raw])) ? $shared[(int)$raw] : $raw;
                    }
                    $cells[$idx] = $val;
                }
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $line = array_fill(0, $max + 1, '');
                foreach ($cells as $i => $v) $line[$i] = $v;
                $matrix[] = $line;
            }
        }
    }
    if (!$matrix) return [];
    $header = $matrix[0];
    $out = [];
    foreach (array_slice($matrix, 1) as $line) {
        $row = [];
        foreach ($header as $i => $name) {
            $name = trim((string)$name);
            if ($name === '' || isset($row[$name])) continue;
            $row[$name] = (string)($line[$i] ?? '');
        }
        if ($row) $out[] = $row;
    }
    return $out;
}

function campo(array $row, array $names): string {
    foreach ($names as $n) if (isset($row[$n]) && trim((string)$row[$n]) !== '') return trim((string)$row[$n]);
    return '';
}

function ministerioDeOrg(string $org): string {
    if ($org === '') return 'Sin identificar';
    $codigo = trim(explode('#', $org)[0]);
    return MINISTERIOS[$codigo] ?? $codigo;
}

function organismoDeOrg(string $org): string {
    if ($org === '') return 'Sin identificar';
    $parts = array_values(array_filter(array_map('trim', explode('#', $org)), fn($x) => $x !== ''));
    return $parts ? end($parts) : $org;
}

function fechaObj(string $s): ?DateTime {
    $s = trim($s); if ($s === '') return null;
    foreach (['Y-m-d H:i:s','Y-m-d H:i','d/m/Y H:i:s','d/m/Y H:i','d/m/Y'] as $f) {
        $d = DateTime::createFromFormat($f, $s); if ($d instanceof DateTime) return $d;
    }
    try { return new DateTime($s); } catch (Throwable $e) { return null; }
}

function estadoGrupo(string $estado): string {
    $e = normalizarTexto($estado);
    foreach (['cerrad','resuelt','solucionad','closed','resolved'] as $k) if (str_contains($e, $k)) return 'Cerrado';
    return 'Abierto';
}

function cargarAdministradores(): array {
    global $DATA_DIR;
    $rows = leerXlsxPrimeraHoja($DATA_DIR . '/Administradores.xlsx');
    $admins = [];
    foreach ($rows as $r) {
        if (normalizarTexto(campo($r, ['Timbo Admin Local'])) !== 'si') continue;
        if (normalizarTexto(campo($r, ['Estatus'])) === 'inactivo') continue;
        $nombre = campo($r, ['Nombre']); $apellido = campo($r, ['Apellidos']);
        $full = trim($nombre . ' ' . $apellido);
        $org = campo($r, ['Organización->Nombre común']);
        $email = strtolower(campo($r, ['Correo Electrónico']));
        $admins[] = [
            'nombre' => $full,
            'apellido' => $apellido,
            'email' => $email,
            'organizacion' => $org,
            'ministerio' => ministerioDeOrg($org),
            'organismo' => organismoDeOrg($org),
            'keys' => array_values(array_unique(array_filter([
                normalizarTexto($full), normalizarTexto(trim($apellido.' '.$nombre)), $email
            ])))
        ];
    }
    return $admins;
}

function indiceAdministradores(array $admins): array {
    $idx = [];
    foreach ($admins as $i => $a) foreach ($a['keys'] as $k) if ($k !== '') $idx[$k] = $i;
    return $idx;
}

function normalizarTicket(array $r, string $tipo, array $admins, array $idxAdmins): array {
    $p = $tipo === 'Incidente' ? 'Incident' : 'UserRequest';
    $org = campo($r, ["$p.Organización->Nombre común"]);
    $reportado = campo($r, ["$p.Reportado por->Nombre común"]);
    $creador = campo($r, ["$p.Creador->Nombre común"]);
    $creadorEmail = strtolower(campo($r, ["$p.Creador->Correo Electrónico"]));
    $admin = null;
    $kRep = normalizarTexto($reportado);
    if ($kRep !== '' && isset($idxAdmins[$kRep])) $admin = $admins[$idxAdmins[$kRep]];
    elseif ($creadorEmail !== '' && isset($idxAdmins[$creadorEmail]) && normalizarTexto($reportado) === normalizarTexto($creador)) $admin = $admins[$idxAdmins[$creadorEmail]];
    $estado = campo($r, ["$p.Estatus"]);
    return [
        'ref' => campo($r, ["$p.Ref"]),
        'tipo' => $tipo,
        'asunto' => campo($r, ["$p.Asunto"]),
        'organizacion' => $org,
        'ministerio' => ministerioDeOrg($org),
        'organismo' => organismoDeOrg($org),
        'reportado_por' => $reportado,
        'creador' => $creador,
        'creador_email' => $creadorEmail,
        'analista' => campo($r, ["$p.Analista->Nombre común"]),
        'servicio' => campo($r, ['Service.Nombre']),
        'estado' => $estado,
        'estado_grupo' => estadoGrupo($estado),
        'fecha_inicio' => campo($r, ["$p.Fecha de Inicio"]),
        'fecha_solucion' => campo($r, ["$p.Fecha de Solución"]),
        'fecha_cierre' => campo($r, ["$p.Fecha de Cierre"]),
        'es_admin_local' => $admin !== null,
        'admin_local' => $admin['nombre'] ?? '',
        'admin_ministerio' => $admin['ministerio'] ?? '',
        'admin_organismo' => $admin['organismo'] ?? ''
    ];
}

function cargarTodo(): array {
    global $DATA_DIR;
    $admins = cargarAdministradores();
    $idx = indiceAdministradores($admins);
    $tickets = [];
    foreach (leerCsv($DATA_DIR . '/Incidente.csv') as $r) {
        $t = normalizarTicket($r, 'Incidente', $admins, $idx); if ($t['ref']) $tickets[$t['ref']] = $t;
    }
    foreach (leerCsv($DATA_DIR . '/Requerimiento.csv') as $r) {
        $t = normalizarTicket($r, 'Requerimiento', $admins, $idx); if ($t['ref']) $tickets[$t['ref']] = $t;
    }
    return ['tickets' => array_values($tickets), 'administradores' => $admins];
}

function filtrosRequest(): array {
    return [
        'desde' => $_GET['desde'] ?? '', 'hasta' => $_GET['hasta'] ?? '', 'ministerio' => $_GET['ministerio'] ?? '',
        'organismo' => $_GET['organismo'] ?? '', 'tipo' => $_GET['tipo'] ?? '', 'estado' => $_GET['estado'] ?? '',
        'persona_tipo' => $_GET['persona_tipo'] ?? '', 'persona' => $_GET['persona'] ?? '', 'servicio' => $_GET['servicio'] ?? ''
    ];
}

function aplicarFiltros(array $tickets, array $f): array {
    return array_values(array_filter($tickets, function($t) use($f) {
        foreach (['ministerio','organismo','tipo','estado','servicio'] as $k) if (($f[$k] ?? '') !== '' && $t[$k] !== $f[$k]) return false;
        $pt = $f['persona_tipo'] ?? ''; $pv = $f['persona'] ?? '';
        if ($pv !== '') {
            $campo = match($pt) {'admin'=>'admin_local','creador'=>'creador','analista'=>'analista',default=>'reportado_por'};
            if ($t[$campo] !== $pv) return false;
        }
        $d = fechaObj($t['fecha_inicio']);
        if (($f['desde'] ?? '') !== '' && $d && $d < new DateTime($f['desde'].' 00:00:00')) return false;
        if (($f['hasta'] ?? '') !== '' && $d && $d > new DateTime($f['hasta'].' 23:59:59')) return false;
        return true;
    }));
}

function contarPor(array $tickets, string $campo, int $limit=0): array {
    $out=[]; foreach($tickets as $t){$v=trim((string)($t[$campo]??'')); if($v==='')$v='Sin identificar'; $out[$v]=($out[$v]??0)+1;}
    arsort($out); if($limit>0)$out=array_slice($out,0,$limit,true); return $out;
}

function resumenNumerico(array $tickets): array {
    $total=count($tickets); $inc=0;$req=0;$ab=0;$ce=0;
    foreach($tickets as $t){$t['tipo']==='Incidente'?$inc++:$req++; $t['estado_grupo']==='Cerrado'?$ce++:$ab++;}
    return ['total'=>$total,'incidentes'=>$inc,'requerimientos'=>$req,'abiertos'=>$ab,'cerrados'=>$ce,'porcentaje_resolucion'=>$total?round($ce*100/$total,1):0];
}

function evolucion(array $tickets): array {
    $r=[]; foreach($tickets as $t){$d=fechaObj($t['fecha_inicio']); if(!$d)continue; $k=$d->format('Y-m'); $r[$k]??=['recibidos'=>0,'cerrados'=>0]; $r[$k]['recibidos']++; if($t['estado_grupo']==='Cerrado')$r[$k]['cerrados']++;}
    ksort($r); return ['labels'=>array_keys($r),'recibidos'=>array_column($r,'recibidos'),'cerrados'=>array_column($r,'cerrados')];
}

function tiempoPromedioHoras(array $tickets): float {
    $sum=0;$n=0; foreach($tickets as $t){$a=fechaObj($t['fecha_inicio']);$b=fechaObj($t['fecha_solucion'])??fechaObj($t['fecha_cierre']); if(!$a||!$b)continue;$sec=$b->getTimestamp()-$a->getTimestamp();if($sec<0)continue;$sum+=$sec/3600;$n++;} return $n?$sum/$n:0;
}

function actividadAdmins(array $tickets): array {
    $a=[]; foreach($tickets as $t){if(!$t['es_admin_local']||$t['admin_local']==='')continue;$k=$t['admin_local'];$a[$k]??=['administrador'=>$k,'ministerio'=>$t['admin_ministerio']?:$t['ministerio'],'organismo'=>$t['admin_organismo']?:$t['organismo'],'incidentes'=>0,'requerimientos'=>0,'total'=>0,'ultimo_ticket'=>''];$t['tipo']==='Incidente'?$a[$k]['incidentes']++:$a[$k]['requerimientos']++;$a[$k]['total']++;$d=fechaObj($t['fecha_inicio']);if($d&&($a[$k]['ultimo_ticket']===''||$d>new DateTime($a[$k]['ultimo_ticket'])))$a[$k]['ultimo_ticket']=$d->format('Y-m-d');}
    uasort($a,fn($x,$y)=>$y['total']<=>$x['total']); return array_values($a);
}

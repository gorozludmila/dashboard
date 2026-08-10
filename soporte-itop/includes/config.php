<?php
// Si ITOP_DATA_DIR está definido, se usa esa carpeta (por ejemplo un Drive montado).
// Si no, se usa la carpeta data del proyecto.
$DATA_DIR = getenv('ITOP_DATA_DIR') ?: __DIR__ . '/../data';

const MINISTERIOS = [
    'MGeIP' => 'Ministerio de Gobierno e Innovación Pública',
    'MTEySS' => 'Ministerio de Trabajo, Empleo y Seguridad Social',
    'MDP' => 'Ministerio de Desarrollo Productivo',
    'MS' => 'Ministerio de Salud',
    'MEC' => 'Ministerio de Economía',
    'ME' => 'Ministerio de Economía',
    'MIyDH' => 'Ministerio de Igualdad y Desarrollo Humano',
    'MOP' => 'Ministerio de Obras Públicas',
    'MED' => 'Ministerio de Educación',
    'MAyCC' => 'Ministerio de Ambiente y Cambio Climático',
    'MC' => 'Ministerio de Cultura',
    'MJyS' => 'Ministerio de Justicia y Seguridad',
    'OD' => 'Organismos Descentralizados',
    'DPVyU' => 'Dirección Provincial de Vivienda y Urbanismo',
    'DPV' => 'Dirección Provincial de Vialidad',
    'API' => 'Administración Provincial de Impuestos',
    'IAPOS' => 'IAPOS',
    'FE' => 'Fiscalía de Estado',
    'LIF' => 'Laboratorio Industrial Farmacéutico',
    'LOTERIA' => 'Lotería de Santa Fe',
    'TC' => 'Tribunal de Cuentas'
];

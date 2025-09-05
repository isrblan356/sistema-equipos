<?php

// Este archivo contiene únicamente la configuración de los tipos de hardware.
// Aquí es donde pegarás el nuevo código PHP generado por la herramienta "Crear Nueva Vista".

return [
    'portatiles' => [
        'singular' => 'Portátil',
        'plural' => 'Portátiles',
        'tabla' => 'hardware_portatiles',
        'icono' => 'fas fa-laptop',
        'campos' => [
            'nombre_equipo' => ['label' => 'Nombre del Equipo', 'type' => 'text', 'required' => true, 'placeholder' => 'Ej: MKT-01-HP'],
            'usuario_asignado' => ['label' => 'Usuario Asignado', 'type' => 'text', 'required' => true],
            'marca' => ['label' => 'Marca', 'type' => 'text'],
            'modelo' => ['label' => 'Modelo', 'type' => 'text'],
            'numero_serie' => ['label' => 'Número de Serie', 'type' => 'text', 'required' => true, 'unique' => true],
            'cpu' => ['label' => 'CPU', 'type' => 'text', 'placeholder' => 'Ej: Core i7-1165G7'],
            'ram_gb' => ['label' => 'RAM (GB)', 'type' => 'number'],
            'almacenamiento_gb' => ['label' => 'Almacenamiento (GB)', 'type' => 'number'],
            'fecha_adquisicion' => ['label' => 'Fecha de Adquisición', 'type' => 'date'],
        ]
    ],
    'celulares' => [
        'singular' => 'Celular',
        'plural' => 'Celulares',
        'tabla' => 'hardware_celulares',
        'icono' => 'fas fa-mobile-alt',
        'campos' => [
            'nombre_equipo' => ['label' => 'Nombre del Equipo', 'type' => 'text', 'required' => true, 'placeholder' => 'Ej: VENTAS-S22-01'],
            'usuario_asignado' => ['label' => 'Usuario Asignado', 'type' => 'text', 'required' => true],
            'marca' => ['label' => 'Marca', 'type' => 'text'],
            'modelo' => ['label' => 'Modelo', 'type' => 'text'],
            'imei' => ['label' => 'IMEI', 'type' => 'text', 'required' => true, 'unique' => true],
            'numero_linea' => ['label' => 'Número de Línea', 'type' => 'text'],
            'fecha_adquisicion' => ['label' => 'Fecha de Adquisición', 'type' => 'date'],
        ]
    ],
    'impresoras' => [
        'singular' => 'Impresora',
        'plural' => 'Impresoras',
        'tabla' => 'hardware_impresoras',
        'icono' => 'fas fa-print',
        'campos' => [
            'nombre_equipo' => ['label' => 'Nombre del Equipo', 'type' => 'text', 'required' => true, 'placeholder' => 'Ej: RECEPCION-LASER'],
            'ubicacion' => ['label' => 'Ubicación', 'type' => 'text', 'required' => true],
            'marca' => ['label' => 'Marca', 'type' => 'text'],
            'modelo' => ['label' => 'Modelo', 'type' => 'text'],
            'numero_serie' => ['label' => 'Número de Serie', 'type' => 'text', 'required' => true, 'unique' => true],
            'tipo_impresora' => ['label' => 'Tipo', 'type' => 'text', 'placeholder' => 'Láser, Tinta, Térmica...'],
            'ip_address' => ['label' => 'Dirección IP', 'type' => 'text'],
            'fecha_adquisicion' => ['label' => 'Fecha de Adquisición', 'type' => 'date'],
        ]
    ],
    'monitores' => [
        'singular' => 'Monitor',
        'plural' => 'Monitores',
        'tabla' => 'hardware_monitores',
        'icono' => 'fas fa-desktop',
        'campos' => [
            'nombre_equipo' => ['label' => 'Código/Nombre', 'type' => 'text', 'required' => true, 'placeholder' => 'Ej: DIS-01-DELL'],
            'usuario_asignado' => ['label' => 'Usuario Asignado', 'type' => 'text'],
            'marca' => ['label' => 'Marca', 'type' => 'text'],
            'modelo' => ['label' => 'Modelo', 'type' => 'text'],
            'numero_serie' => ['label' => 'Número de Serie', 'type' => 'text', 'required' => true, 'unique' => true],
            'tamano_pulgadas' => ['label' => 'Tamaño (Pulgadas)', 'type' => 'number'],
            'resolucion' => ['label' => 'Resolución', 'type' => 'text', 'placeholder' => 'Ej: 1920x1080'],
            'fecha_adquisicion' => ['label' => 'Fecha de Adquisición', 'type' => 'date'],
        ]
    ],
    'telefonos_voip' => [
        'singular' => 'Teléfono VoIP',
        'plural' => 'Teléfonos VoIP',
        'tabla' => 'hardware_telefonos_voip',
        'icono' => 'fas fa-phone-alt',
        'campos' => [
            'nombre_equipo' => ['label' => 'Nombre del Equipo', 'type' => 'text', 'required' => true, 'placeholder' => 'Ej: CONT-EXT101'],
            'usuario_asignado' => ['label' => 'Usuario Asignado', 'type' => 'text'],
            'marca' => ['label' => 'Marca', 'type' => 'text'],
            'modelo' => ['label' => 'Modelo', 'type' => 'text'],
            'numero_serie' => ['label' => 'Número de Serie', 'type' => 'text', 'required' => true, 'unique' => true],
            'mac_address' => ['label' => 'Dirección MAC', 'type' => 'text', 'required' => true, 'unique' => true],
            'ip_address' => ['label' => 'Dirección IP', 'type' => 'text'],
            'extension' => ['label' => 'Extensión', 'type' => 'text'],
            'fecha_adquisicion' => ['label' => 'Fecha de Adquisición', 'type' => 'date'],
        ]
    ],
];
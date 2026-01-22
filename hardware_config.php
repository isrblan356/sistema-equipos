<?php
return array (
  'portatiles' => 
  array (
    'singular' => 'Portátil',
    'plural' => 'Portátiles',
    'tabla' => 'hardware_portatiles',
    'icono' => 'fas fa-laptop',
    'campos' => 
    array (
      'nombre_equipo' => 
      array (
        'label' => 'Nombre del Equipo',
        'type' => 'text',
        'required' => true,
        'placeholder' => 'Ej: MKT-01-HP',
      ),
      'usuario_asignado' => 
      array (
        'label' => 'Usuario Asignado',
        'type' => 'text',
        'required' => true,
      ),
      'marca' => 
      array (
        'label' => 'Marca',
        'type' => 'text',
      ),
      'modelo' => 
      array (
        'label' => 'Modelo',
        'type' => 'text',
      ),
      'numero_serie' => 
      array (
        'label' => 'Número de Serie',
        'type' => 'text',
        'required' => true,
        'unique' => true,
      ),
      'cpu' => 
      array (
        'label' => 'CPU',
        'type' => 'text',
        'placeholder' => 'Ej: Core i7-1165G7',
      ),
      'ram_gb' => 
      array (
        'label' => 'RAM (GB)',
        'type' => 'number',
      ),
      'almacenamiento_gb' => 
      array (
        'label' => 'Almacenamiento (GB)',
        'type' => 'number',
      ),
      'fecha_adquisicion' => 
      array (
        'label' => 'Fecha de Adquisición',
        'type' => 'date',
      ),
    ),
  ),
  'celulares' => 
  array (
    'singular' => 'Celular',
    'plural' => 'Celulares',
    'tabla' => 'hardware_celulares',
    'icono' => 'fas fa-mobile-alt',
    'campos' => 
    array (
      'nombre_equipo' => 
      array (
        'label' => 'Nombre del Equipo',
        'type' => 'text',
        'required' => true,
        'placeholder' => 'Ej: VENTAS-S22-01',
      ),
      'usuario_asignado' => 
      array (
        'label' => 'Usuario Asignado',
        'type' => 'text',
        'required' => true,
      ),
      'marca' => 
      array (
        'label' => 'Marca',
        'type' => 'text',
      ),
      'modelo' => 
      array (
        'label' => 'Modelo',
        'type' => 'text',
      ),
      'imei' => 
      array (
        'label' => 'IMEI',
        'type' => 'text',
        'required' => true,
        'unique' => true,
      ),
      'numero_linea' => 
      array (
        'label' => 'Número de Línea',
        'type' => 'text',
      ),
      'fecha_adquisicion' => 
      array (
        'label' => 'Fecha de Adquisición',
        'type' => 'date',
      ),
    ),
  ),
  'impresoras' => 
  array (
    'singular' => 'Impresora',
    'plural' => 'Impresoras',
    'tabla' => 'hardware_impresoras',
    'icono' => 'fas fa-print',
    'campos' => 
    array (
      'nombre_equipo' => 
      array (
        'label' => 'Nombre del Equipo',
        'type' => 'text',
        'required' => true,
        'placeholder' => 'Ej: RECEPCION-LASER',
      ),
      'ubicacion' => 
      array (
        'label' => 'Ubicación',
        'type' => 'text',
        'required' => true,
      ),
      'marca' => 
      array (
        'label' => 'Marca',
        'type' => 'text',
      ),
      'modelo' => 
      array (
        'label' => 'Modelo',
        'type' => 'text',
      ),
      'numero_serie' => 
      array (
        'label' => 'Número de Serie',
        'type' => 'text',
        'required' => true,
        'unique' => true,
      ),
      'tipo_impresora' => 
      array (
        'label' => 'Tipo',
        'type' => 'text',
        'placeholder' => 'Láser, Tinta, Térmica...',
      ),
      'ip_address' => 
      array (
        'label' => 'Dirección IP',
        'type' => 'text',
      ),
      'fecha_adquisicion' => 
      array (
        'label' => 'Fecha de Adquisición',
        'type' => 'date',
      ),
    ),
  ),
  'monitores' => 
  array (
    'singular' => 'Monitor',
    'plural' => 'Monitores',
    'tabla' => 'hardware_monitores',
    'icono' => 'fas fa-desktop',
    'campos' => 
    array (
      'nombre_equipo' => 
      array (
        'label' => 'Código/Nombre',
        'type' => 'text',
        'required' => true,
        'placeholder' => 'Ej: DIS-01-DELL',
      ),
      'usuario_asignado' => 
      array (
        'label' => 'Usuario Asignado',
        'type' => 'text',
      ),
      'marca' => 
      array (
        'label' => 'Marca',
        'type' => 'text',
      ),
      'modelo' => 
      array (
        'label' => 'Modelo',
        'type' => 'text',
      ),
      'numero_serie' => 
      array (
        'label' => 'Número de Serie',
        'type' => 'text',
        'required' => true,
        'unique' => true,
      ),
      'tamano_pulgadas' => 
      array (
        'label' => 'Tamaño (Pulgadas)',
        'type' => 'number',
      ),
      'resolucion' => 
      array (
        'label' => 'Resolución',
        'type' => 'text',
        'placeholder' => 'Ej: 1920x1080',
      ),
      'fecha_adquisicion' => 
      array (
        'label' => 'Fecha de Adquisición',
        'type' => 'date',
      ),
    ),
  ),
  'telefonos_voip' => 
  array (
    'singular' => 'Teléfono VoIP',
    'plural' => 'Teléfonos VoIP',
    'tabla' => 'hardware_telefonos_voip',
    'icono' => 'fas fa-phone-alt',
    'campos' => 
    array (
      'nombre_equipo' => 
      array (
        'label' => 'Nombre del Equipo',
        'type' => 'text',
        'required' => true,
        'placeholder' => 'Ej: CONT-EXT101',
      ),
      'usuario_asignado' => 
      array (
        'label' => 'Usuario Asignado',
        'type' => 'text',
      ),
      'marca' => 
      array (
        'label' => 'Marca',
        'type' => 'text',
      ),
      'modelo' => 
      array (
        'label' => 'Modelo',
        'type' => 'text',
      ),
      'numero_serie' => 
      array (
        'label' => 'Número de Serie',
        'type' => 'text',
        'required' => true,
        'unique' => true,
      ),
      'mac_address' => 
      array (
        'label' => 'Dirección MAC',
        'type' => 'text',
        'required' => true,
        'unique' => true,
      ),
      'ip_address' => 
      array (
        'label' => 'Dirección IP',
        'type' => 'text',
      ),
      'extension' => 
      array (
        'label' => 'Extensión',
        'type' => 'text',
      ),
      'fecha_adquisicion' => 
      array (
        'label' => 'Fecha de Adquisición',
        'type' => 'date',
      ),
    ),
  ),
  'diademas' => 
  array (
    'singular' => 'Diadema',
    'plural' => 'Diademas',
    'tabla' => 'hardware_diademas',
    'icono' => 'fa-headphones',
    'campos' => 
    array (
      'nombre_de_diadema' => 
      array (
        'label' => 'nombre de Diadema',
        'type' => 'text',
        'required' => false,
        'unique' => false,
      ),
      'responsable' => 
      array (
        'label' => 'responsable',
        'type' => 'text',
        'required' => false,
        'unique' => false,
      ),
      'fecha_entrega' => 
      array (
        'label' => 'Fecha entrega',
        'type' => 'date',
        'required' => false,
        'unique' => false,
      ),
      'fecha_de_salida' => 
      array (
        'label' => 'Fecha de Salida',
        'type' => 'text',
        'required' => false,
        'unique' => false,
      ),
    ),
  ),
);

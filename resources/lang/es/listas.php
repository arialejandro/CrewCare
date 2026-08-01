<?php

/**
 * Superficie "Listas" (Crew List + Gafetes/Badges + Diseñador).
 * Strings NUEVOS agregados al homologar Gafetes al lenguaje de Crew List.
 * es/ y en/ se reflejan exactamente: mismas claves, mismo orden.
 */
return [

    // ===== Fila de gafete (componentes/_idcard-row) =====
    'integrante'         => 'Integrante',
    'depto'              => 'Depto.',
    'estado'             => 'Estado',
    'con_foto'           => 'Con foto',
    'sin_foto'           => 'Sin foto',
    'impreso'            => 'Impreso',
    'pendiente'          => 'Pendiente',
    'acciones'           => 'Acciones',
    'ver_gafete'         => 'Ver gafete',
    'descargar_pdf'      => 'Descargar PDF',
    'desmarcar_impreso'  => 'Desmarcar impreso',
    'marcar_impreso'     => 'Marcar como impreso',

    // ===== Lista de gafetes (admin/idcardscrud) =====
    'gafetes'            => 'Gafetes',
    'miembros_activos'   => ':n miembros activos',
    'disenar'            => 'Diseñar',
    'pdf_listos'         => 'PDF listos (:n)',
    'zip_listos'         => 'ZIP JPG listos (:n)',
    'buscar'             => 'Buscar por nombre…',
    'buscar_label'       => 'Buscar integrantes por nombre',
    'lote_info'          => 'Las descargas en lote incluyen solo gafetes listos: con foto de perfil y aún no marcados como impresos. Marca “Impreso” tras imprimir para no duplicar.',

    // Chips-filtro (clicables) — muestran los counts que ya calcula el controlador
    'filtro_todos'       => 'Todos',
    'filtro_con_foto'    => 'Con foto',
    'filtro_listos'      => 'Listos',

    // Estados / vacíos
    'sin_integrantes'    => 'Sin integrantes.',
    'empty_title'        => 'Sin gafetes que mostrar',
    'empty_sub'          => 'No hay integrantes que coincidan con este filtro.',
    'no_result'          => 'No se encontraron usuarios.',
];

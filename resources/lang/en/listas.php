<?php

/**
 * "Lists" surface (Crew List + Badges + Designer).
 * NEW strings added when homologating Badges to the Crew List language.
 * es/ and en/ mirror exactly: same keys, same order.
 */
return [

    // ===== Badge row (componentes/_idcard-row) =====
    'integrante'         => 'Member',
    'depto'              => 'Dept.',
    'estado'             => 'Status',
    'con_foto'           => 'With photo',
    'sin_foto'           => 'No photo',
    'impreso'            => 'Printed',
    'pendiente'          => 'Pending',
    'acciones'           => 'Actions',
    'ver_gafete'         => 'View badge',
    'descargar_pdf'      => 'Download PDF',
    'desmarcar_impreso'  => 'Unmark printed',
    'marcar_impreso'     => 'Mark as printed',

    // ===== Badge list (admin/idcardscrud) =====
    'gafetes'            => 'Badges',
    'miembros_activos'   => ':n active members',
    'disenar'            => 'Design',
    'pdf_listos'         => 'Ready PDFs (:n)',
    'zip_listos'         => 'Ready JPG ZIP (:n)',
    'buscar'             => 'Search by name…',
    'buscar_label'       => 'Search members by name',
    'lote_info'          => 'Bulk downloads include only ready badges: with a profile photo and not yet marked as printed. Mark “Printed” after printing to avoid duplicates.',

    // Filter chips (clickable) — show the counts the controller already computes
    'filtro_todos'       => 'All',
    'filtro_con_foto'    => 'With photo',
    'filtro_listos'      => 'Ready',

    // States / empty
    'sin_integrantes'    => 'No members.',
    'empty_title'        => 'No badges to show',
    'empty_sub'          => 'No members match this filter.',
    'no_result'          => 'No users found.',
];

<?php

/**
 * Textos del módulo de Scouting / documento "Amazon MGM Studios – Risk Assessment".
 *
 * i18n "dejar listo": estos textos existen en es/ y en/ pero la UI sigue en
 * español (selector apagado). El documento Amazon los resuelve con
 * __('scouting.clave', [], $lang) para poder previsualizar inglés con ?lang=en
 * SIN cambiar el locale global de la app.
 */
return [

    // Encabezado del documento
    'hdr_title' => 'Amazon MGM Studios — Evaluación de Riesgos',
    'hdr_sub'   => 'Seguridad de Producción',

    // Campos del encabezado
    'f_production_title' => 'Título de producción',
    'f_company'          => 'Compañía de producción',
    'f_office'           => 'Domicilio de oficina de producción',
    'f_manager'          => 'Gerente de producción',
    'f_safety_rep'       => 'Rep. de seguridad de producción',
    'f_type'             => 'Tipo de producción',
    'f_location'         => 'Dirección de la locación',
    'f_report_date'      => 'Fecha del reporte',
    'f_shoot_day'        => 'Día de rodaje',

    // Secciones
    's_risk_assessment' => 'Evaluación de riesgos',
    's_activity'        => 'Descripción de la actividad',
    's_scenes'          => 'Escenas específicas de rodaje',
    's_overview'        => 'Panorama general',
    's_matrix'          => 'Matriz de riesgo',
    's_rating_action'   => 'Clasificación de riesgo — Acción requerida',
    's_hierarchy'       => 'Jerarquía de control',
    's_definitions'     => 'Definiciones',

    // Tabla de peligros
    't_hazard'    => 'Peligros potenciales',
    't_l'         => 'P',
    't_c'         => 'C',
    't_rating'    => 'Clasificación',
    't_control'   => 'Medidas de control',
    't_new'       => 'Riesgo residual',
    't_personnel' => 'Personal requerido',
    't_none'      => 'Sin peligros registrados.',
    'overview_none' => 'Sin panorama general registrado.',

    // Palabras de clasificación
    'r_L' => 'Bajo (L)',
    'r_M' => 'Medio (M)',
    'r_H' => 'Alto (H)',
    'r_E' => 'Muy alto (E)',

    // Acción requerida por clasificación (texto formal)
    'act_E' => 'La tarea o actividad propuesta NO debe proceder. Deben tomarse medidas para reducir el nivel de riesgo a lo más bajo razonablemente posible aplicando la jerarquía de controles.',
    'act_H' => 'La actividad solo puede proceder si: (1) el riesgo se redujo a lo más bajo razonablemente posible; (2) los controles incluyen los exigidos por la legislación, normas y códigos de práctica; (3) la evaluación fue revisada y aprobada por el supervisor; (4) se preparó un procedimiento o método de trabajo seguro; (5) el supervisor revisa y documenta la eficacia de los controles implementados.',
    'act_M' => 'La tarea o actividad puede proceder si: (1) el riesgo se redujo a lo más bajo razonablemente posible; (2) la evaluación fue aprobada por el supervisor; (3) se preparó un procedimiento o método de trabajo seguro.',
    'act_L' => 'Se gestiona con procedimientos de rutina documentados, que deben incluir la aplicación de la jerarquía de control.',

    // Jerarquía de control
    'hier_1' => 'Eliminar — quitar el peligro.',
    'hier_2' => 'Sustituir — reemplazar el peligro por algo de menor riesgo.',
    'hier_3' => 'Aislar — separar el peligro de la persona.',
    'hier_4' => 'Ingeniería — minimizar el riesgo con medios de ingeniería (p. ej. ayudas mecánicas).',
    'hier_5' => 'Administración — minimizar el riesgo con medios administrativos (p. ej. capacitación, señalización).',
    'hier_6' => 'EPP — minimizar el riesgo con equipo de protección personal (chalecos, protección auditiva y ocular).',

    // Definiciones
    'def_hazard'      => 'Peligro: fuente de daño potencial o situación con potencial de causar daño.',
    'def_risk'        => 'Riesgo: probabilidad de que algo ocurra y tenga impacto en los objetivos; se mide por consecuencia y probabilidad.',
    'def_consequence' => 'Consecuencia: el resultado de un evento.',
    'def_likelihood'  => 'Probabilidad: la probabilidad de las consecuencias de un evento.',
    'def_rating'      => 'Clasificación de riesgo: procedimiento que produce el nivel de riesgo, combinando la consecuencia y la probabilidad de que ocurra.',

    // Desglose SB-132 (el documento del estudio debe imprimir QUÉ actividad especial se declaró,
    // no solo que la hay). Etiquetas de actividad = los 8 valores cerrados de activity_type.
    's_sb132'          => 'Actividades especiales declaradas (SB-132)',
    'sb132_activities' => 'Actividades declaradas',
    'sb132_scene'      => 'Escena(s)',
    'sb132_personnel'  => 'Personal certificado requerido',
    'act_armas'        => 'Armas',
    'act_pirotecnia'   => 'Pirotecnia / SFX',
    'act_stunts'       => 'Stunts',
    'act_aereo'        => 'Aéreo',
    'act_agua'         => 'Agua',
    'act_offroad'      => 'Off-road',
    'act_fuego'        => 'Fuego abierto / llamas',
    'act_altura'       => 'Trabajo en altura / rigging',

    // Marca de borrador (documento no-final)
    'draft'      => 'BORRADOR',
    'draft_note' => 'Documento en borrador — no es la versión final entregable al estudio.',

    // Varios
    'compiled_by' => 'Elaborado por (representante de producción)',
    'none'        => '—',
    'btn_print'   => 'Imprimir / PDF',
    'btn_back'    => 'Volver',
    'sb132'       => 'Requiere Evaluación de Riesgos Específica (SB132): se declararon actividades especiales.',
];

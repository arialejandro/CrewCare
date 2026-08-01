<?php

/**
 * Textos visibles del dashboard / home (resources/views/inicio.blade.php).
 *
 * Cubre la rama admin (banner, KPIs, gráfica) y la rama crew (tarjeta de perfil,
 * cuestionario) más el modal de recorte de foto compartido. Primera superficie
 * migrada del rollout i18n incremental. es/ y en/ se reflejan exactamente.
 */
return [

    // ===== Banner de bienvenida (admin) =====
    'banner_eyebrow'         => 'Dashboard · Salud y Seguridad',
    'greeting'               => 'Hola',      // se concatena con el nombre del usuario
    'survey_thanks'          => 'Gracias por responder tu cuestionario de salud.',
    'survey_pending'         => 'Aún no respondes tu cuestionario de salud de hoy.',
    'survey_answer_btn'      => 'Responder cuestionario',

    // ===== KPIs (admin) =====
    'kpi_days_no_accidents' => 'Días sin accidentes',
    'kpi_locations_checked' => 'Locaciones revisadas',
    'kpi_production_days'   => 'Días trabajados',
    'kpi_shooting_days'     => 'Días de rodaje',

    // (2026-07-24) Cintillo de calendario: dónde estamos hoy y cuánto falta al wrap.
    'cal_today'             => 'Hoy',
    'cal_wrap'              => 'Wrap',
    'cal_wrap_unset'        => 'Sin fecha de wrap',
    'cal_wrap_unset_hint'   => 'Se fija en la producción',
    'kpi_total_accidents'   => 'Total accidentes',
    'kpi_total_consults'    => 'Total consultas',
    'kpi_pill_safe'         => 'Seguro',
    'kpi_pill_attention'    => 'Atención',

    // ===== Gráfica de tendencia (admin) =====
    'trend_title' => 'Tendencia semanal de reportes inseguros',
    'trend_note'  => '*La línea punteada representa la tendencia general.',
    'chart_acts'   => 'Actos Inseguros',
    'chart_conds'  => 'Condiciones Inseguras',
    'chart_y_axis' => 'Número de reportes',
    'chart_x_axis' => 'Semana del mes',

    // ===== Panel médico (2026-07-24) =====
    // Textos del bloque de atenciones del tablero. "Sólo tus atenciones" no es decorativo:
    // avisa que lo que se está leyendo viene filtrado por propiedad (cmedic::visibleTo), para
    // que nadie tome un número parcial por el total de la producción.
    'med_title'         => 'Atenciones médicas',
    'med_window'        => 'Últimas 8 semanas',
    'med_scope_own'     => 'Sólo tus atenciones',
    'med_consults'      => 'Atenciones',
    'med_today'         => 'Hoy',
    'med_people'        => 'Personas distintas',
    'med_no_record'     => 'Sin expediente',
    'med_no_record_hint'=> 'Se atendieron sin cuestionario: hay que completarles el expediente.',
    'med_last'          => 'Última atención',
    'med_meds_title'    => 'Medicamentos usados',
    'med_mgmt_title'    => 'Manejo clínico',
    'med_empty'         => 'Todavía no hay atenciones en esta ventana.',
    'med_empty_hint'    => 'En cuanto se registre una consulta, aquí aparecen las cifras.',
    'med_go_consults'   => 'Consultas',
    'med_go_log'        => 'Bitácora',
    'med_go_materials'  => 'Conteo',
    'med_weekly'        => 'Por semana',

    // ===== Actividad reciente (admin) =====
    'activity_title'      => 'Actividad reciente',
    'activity_empty'      => 'Sin actividad reciente',
    'activity_empty_hint' => 'Los últimos movimientos aparecerán aquí conforme se registren reportes.',

    // ===== Rama crew =====
    'change_photo'          => 'Cambia tu foto de perfil',
    'crew_survey_thanks'    => 'Gracias responder su cuestionario de salud.',
    'crew_survey_pending'   => 'Por favor responda su cuestionario.',
    'crew_survey_answer_btn'=> 'Responder cuestionario de salud',

    // ===== Modal de recorte de foto (compartido) =====
    'crop_title'  => 'Corta tu foto',
    'crop_cancel' => 'Cancelar',
    'crop_update' => 'Actualizar',
];

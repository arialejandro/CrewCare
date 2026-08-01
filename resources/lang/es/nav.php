<?php

/**
 * Textos de la navegación global (sidebar de escritorio y offcanvas móvil).
 *
 * Primera superficie migrada del rollout i18n incremental. Las claves aquí son
 * usadas por AMBAS copias del menú (escritorio + #mobileSidebar) con __('nav.clave').
 * es/ y en/ se reflejan exactamente: mismas claves, mismo orden.
 */
return [

    // Marca / saludo del sidebar
    'greeting' => 'Hola',      // se concatena con el nombre del usuario
    'menu'     => 'Menú',      // título del offcanvas móvil

    // ===== Acciones del header (barra superior) =====
    'home'         => 'Inicio',
    'back'         => 'Atrás',
    'profile'      => 'Perfil',
    'theme_toggle' => 'Cambiar tema claro / oscuro',

    // ===== Shell: buscador / paleta de comandos + colapsar sidebar =====
    'search'             => 'Buscar…',
    'search_placeholder' => 'Ir a… escribe un destino (crew, accidente, gafete, scouting…)',
    'search_empty'       => 'Sin resultados',
    'search_nav'         => 'navegar',
    'search_open'        => 'abrir',
    'search_close'       => 'cerrar',
    'collapse'           => 'Colapsar menú',
    'expand'             => 'Expandir menú',
    'role_admin'         => 'Super-admin',
    'role_crew'          => 'Crew',

    // ===== General =====
    'sec_general' => 'General',

    // ===== Encabezados de sección =====
    'sec_crew'      => 'Crew',
    'sec_locations' => 'Locaciones',
    'sec_safety'    => 'Seguridad',
    'sec_medical'   => 'Médico',
    'sec_catalogs'  => 'Catálogos',
    'sec_admin'     => 'Admin',
    'sec_settings'  => 'Configuración',

    // ===== CREW =====
    'crew_new'          => 'Nuevo Miembro',
    'crew_list'         => 'Ver Crew List',
    'crew_badge_list'   => 'Lista gafetes',
    'crew_badge_design' => 'Diseñar gafete',

    // ===== LOCACIONES =====
    'loc_scoutings' => 'Scoutings',
    'loc_new'       => 'Nuevo Scouting',

    // ===== SEGURIDAD =====
    'safety_daily_reports' => 'Daily Reports',
    'safety_daily_new'     => 'Nuevo Daily',
    'safety_unsafe_conds'  => 'Cond. Inseguras',
    'safety_unsafe_cond'   => 'Cond. Insegura',
    'safety_unsafe_acts'   => 'Acc. Inseguras',
    'safety_unsafe_act'    => 'Acc. Insegura',
    'safety_accidents'     => 'Accidentes',
    'safety_accident'      => 'Accidente',
    'safety_inspect_tool'  => 'Inspeccionar herramienta',
    'permits'              => 'Permisos de trabajo',
    'epi'                  => 'Vigilancia de salud',
    'safety_wrap'          => 'Reporte de Wrap',

    // ===== MÉDICO =====
    'med_consults'  => 'Consultas Médicas',
    'med_logbook'   => 'Bitácora Semanal',
    'med_materials' => 'Conteo de Medicamentos',

    // ===== CATÁLOGOS =====
    'cat_departments'   => 'Departamentos',
    'cat_positions'     => 'Puestos',
    'cat_notifications'  => 'Notificaciones',
    'cat_standards'      => 'Normas',
    'cat_hazard_events'  => 'Eventos de peligro',

    // ===== ADMIN =====
    'admin_assign_roles' => 'Asignar Roles',
    'admin_permissions'  => 'Permisos por rol',

    // ===== CONFIGURACIÓN =====
    'settings_branding' => 'Marca',
];

<?php

/**
 * Global navigation strings (desktop sidebar and mobile offcanvas).
 *
 * First surface migrated in the incremental i18n rollout. These keys are used by
 * BOTH copies of the menu (desktop + #mobileSidebar) via __('nav.key').
 * es/ and en/ mirror each other exactly: same keys, same order.
 */
return [

    // Sidebar brand / greeting
    'greeting' => 'Hi',        // concatenated with the user's name
    'menu'     => 'Menu',      // mobile offcanvas title

    // ===== Header actions (top bar) =====
    'home'         => 'Home',
    'back'         => 'Back',
    'profile'      => 'Profile',
    'theme_toggle' => 'Toggle light / dark theme',

    // ===== Shell: search / command palette + collapse sidebar =====
    'search'             => 'Search…',
    'search_placeholder' => 'Go to… type a destination (crew, accident, badge, scouting…)',
    'search_empty'       => 'No results',
    'search_nav'         => 'navigate',
    'search_open'        => 'open',
    'search_close'       => 'close',
    'collapse'           => 'Collapse menu',
    'expand'             => 'Expand menu',
    'role_admin'         => 'Super-admin',
    'role_crew'          => 'Crew',

    // ===== General =====
    'sec_general' => 'General',

    // ===== Section headers =====
    'sec_crew'      => 'Crew',
    'sec_locations' => 'Locations',
    'sec_safety'    => 'Safety',
    'sec_medical'   => 'Medical',
    'sec_catalogs'  => 'Catalogs',
    'sec_admin'     => 'Admin',
    'sec_settings'  => 'Settings',

    // ===== CREW =====
    'crew_new'          => 'New Member',
    'crew_list'         => 'View Crew List',
    'crew_badge_list'   => 'Badge List',
    'crew_badge_design' => 'Design Badge',

    // ===== LOCATIONS =====
    'loc_scoutings' => 'Scoutings',
    'loc_new'       => 'New Scouting',

    // ===== SAFETY =====
    'safety_daily_reports' => 'Daily Reports',
    'safety_daily_new'     => 'New Daily',
    'safety_unsafe_conds'  => 'Unsafe Conditions',
    'safety_unsafe_cond'   => 'Unsafe Condition',
    'safety_unsafe_acts'   => 'Unsafe Acts',
    'safety_unsafe_act'    => 'Unsafe Act',
    'safety_accidents'     => 'Accidents',
    'safety_wrap'          => 'Wrap Report',
    'safety_accident'      => 'Accident',
    'safety_inspect_tool'  => 'Inspect a tool',
    'permits'              => 'Work permits',
    'epi'                  => 'Health surveillance',

    // ===== MEDICAL =====
    'med_consults'  => 'Medical Consults',
    'med_logbook'   => 'Weekly Logbook',
    'med_materials' => 'Medication Count',

    // ===== CATALOGS =====
    'cat_departments'   => 'Departments',
    'cat_positions'     => 'Positions',
    'cat_notifications'  => 'Notifications',
    'cat_standards'      => 'Standards',
    'cat_hazard_events'  => 'Hazard events',

    // ===== ADMIN =====
    'admin_assign_roles' => 'Assign Roles',
    'admin_permissions'  => 'Permissions by Role',

    // ===== SETTINGS =====
    'settings_branding' => 'Branding',
];

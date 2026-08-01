<?php

/**
 * Visible dashboard / home strings (resources/views/inicio.blade.php).
 *
 * Covers the admin branch (banner, KPIs, chart) and the crew branch (profile
 * card, questionnaire) plus the shared photo-cropper modal. First surface
 * migrated in the incremental i18n rollout. es/ and en/ mirror each other exactly.
 */
return [

    // ===== Welcome banner (admin) =====
    'banner_eyebrow'         => 'Dashboard · Health & Safety',
    'greeting'               => 'Hi',        // concatenated with the user's name
    'survey_thanks'          => 'Thank you for completing your health questionnaire.',
    'survey_pending'         => "You haven't completed today's health questionnaire yet.",
    'survey_answer_btn'      => 'Answer questionnaire',

    // ===== KPIs (admin) =====
    'kpi_days_no_accidents' => 'Days without accidents',
    'kpi_locations_checked' => 'Locations checked',
    'kpi_production_days'   => 'Working days',
    'kpi_shooting_days'     => 'Shooting days',

    // (2026-07-24) Calendar band: where we are today and how far the wrap is.
    'cal_today'             => 'Today',
    'cal_wrap'              => 'Wrap',
    'cal_wrap_unset'        => 'No wrap date',
    'cal_wrap_unset_hint'   => 'Set it on the production',
    'kpi_total_accidents'   => 'Total accidents',
    'kpi_total_consults'    => 'Total consults',
    'kpi_pill_safe'         => 'Safe',
    'kpi_pill_attention'    => 'Attention',

    // ===== Trend chart (admin) =====
    'trend_title' => 'Weekly trend of unsafe reports',
    'trend_note'  => '*The dotted line represents the overall trend.',
    'chart_acts'   => 'Unsafe Acts',
    'chart_conds'  => 'Unsafe Conditions',
    'chart_y_axis' => 'Number of reports',
    'chart_x_axis' => 'Week of month',

    // ===== Medical panel (2026-07-24) =====
    'med_title'         => 'Medical attentions',
    'med_window'        => 'Last 8 weeks',
    'med_scope_own'     => 'Your attentions only',
    'med_consults'      => 'Attentions',
    'med_today'         => 'Today',
    'med_people'        => 'Distinct people',
    'med_no_record'     => 'No health record',
    'med_no_record_hint'=> 'Attended without a questionnaire: their record still needs completing.',
    'med_last'          => 'Last attention',
    'med_meds_title'    => 'Medication used',
    'med_mgmt_title'    => 'Clinical management',
    'med_empty'         => 'No attentions in this window yet.',
    'med_empty_hint'    => 'As soon as a consult is logged, the figures show up here.',
    'med_go_consults'   => 'Consults',
    'med_go_log'        => 'Logbook',
    'med_go_materials'  => 'Count',
    'med_weekly'        => 'Per week',

    // ===== Recent activity (admin) =====
    'activity_title'      => 'Recent activity',
    'activity_empty'      => 'No recent activity',
    'activity_empty_hint' => 'The latest movements will show up here as reports are logged.',

    // ===== Crew branch =====
    'change_photo'          => 'Change your profile photo',
    'crew_survey_thanks'    => 'Thank you for completing your health questionnaire.',
    'crew_survey_pending'   => 'Please complete your questionnaire.',
    'crew_survey_answer_btn'=> 'Answer health questionnaire',

    // ===== Photo-cropper modal (shared) =====
    'crop_title'  => 'Crop your photo',
    'crop_cancel' => 'Cancel',
    'crop_update' => 'Update',
];

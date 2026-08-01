<?php

/**
 * Scouting module / "Amazon MGM Studios – Risk Assessment" document strings.
 *
 * i18n "ready": these mirror es/. UI stays Spanish (switcher off); the Amazon
 * document resolves them with __('scouting.key', [], $lang) so English can be
 * previewed via ?lang=en WITHOUT changing the app's global locale.
 * Wording of the risk-rating actions, hierarchy and definitions matches the
 * official Amazon MGM Studios Risk Assessment Form.
 */
return [

    // Document header
    'hdr_title' => 'Amazon MGM Studios — Risk Assessment',
    'hdr_sub'   => 'Production Safety',

    // Header fields
    'f_production_title' => 'Production title',
    'f_company'          => 'Production company',
    'f_office'           => 'Production office address',
    'f_manager'          => 'Production manager',
    'f_safety_rep'       => 'Production safety rep',
    'f_type'             => 'Production type',
    'f_location'         => 'Location address',
    'f_report_date'      => 'Date of report',
    'f_shoot_day'        => 'Shoot day',

    // Sections
    's_risk_assessment' => 'Risk assessment',
    's_activity'        => 'Description of activity',
    's_scenes'          => 'Specific filming scenes',
    's_overview'        => 'Overview',
    's_matrix'          => 'Risk matrix',
    's_rating_action'   => 'Risk rating — Required action',
    's_hierarchy'       => 'Hierarchy of control',
    's_definitions'     => 'Definitions',

    // Hazards table
    't_hazard'    => 'Potential hazards',
    't_l'         => 'L',
    't_c'         => 'C',
    't_rating'    => 'Risk rating',
    't_control'   => 'Control measures',
    't_new'       => 'New risk rating',
    't_personnel' => 'Personnel required',
    't_none'      => 'No hazards recorded.',
    'overview_none' => 'No overview recorded.',

    // Rating words
    'r_L' => 'Low (L)',
    'r_M' => 'Medium (M)',
    'r_H' => 'High (H)',
    'r_E' => 'Very high (E)',

    // Required action by rating (formal wording)
    'act_E' => 'The proposed task or activity must not proceed. Steps must be taken to lower the risk level to as low as reasonably practicable using the hierarchy of risk controls.',
    'act_H' => 'The proposed activity can only proceed provided that: (1) the risk level has been reduced to as low as reasonably practicable; (2) the risk controls include those identified in legislation, standards and codes of practice; (3) the risk assessment has been reviewed and approved by the Supervisor; (4) a Safe Working Procedure or Safe Work Method has been prepared; (5) the Supervisor reviews and documents the effectiveness of the implemented risk controls.',
    'act_M' => 'The proposed task or activity can proceed provided that: (1) the risk level has been reduced to as low as reasonably practicable; (2) the risk assessment has been approved by the Supervisor; (3) a Safe Working Procedure or Safe Work Method has been prepared.',
    'act_L' => 'Managed by local documented routine procedures which must include application of the hierarchy of control.',

    // Hierarchy of control
    'hier_1' => 'Eliminate — remove the hazard.',
    'hier_2' => 'Substitute — replace the hazard with something that has less risk.',
    'hier_3' => 'Isolate — isolate the hazard from the person.',
    'hier_4' => 'Engineer — use engineering means to minimise the risk, e.g. mechanical aids.',
    'hier_5' => 'Administration — minimise the risk using administrative means, e.g. training, signs.',
    'hier_6' => 'Personal Protective Equipment (PPE) — minimise the risk by using PPE, e.g. safety vests, hearing and eye protection.',

    // Definitions
    'def_hazard'      => 'Hazard: a source of potential harm or a situation with the potential to cause harm.',
    'def_risk'        => 'Risk: the chance of something happening that will have an impact on objectives; measured in terms of consequences and likelihood.',
    'def_consequence' => 'Consequence: the outcome of an event.',
    'def_likelihood'  => 'Likelihood: the likelihood of the consequences of an event.',
    'def_rating'      => 'Risk Rating: the procedure that produces a risk level for the activity, combining the consequence of a risk and the likelihood that it occurs.',

    // Misc
    // SB-132 breakdown (the studio document must print WHICH special activity was declared,
    // not only that there is one). Activity labels = the 8 closed activity_type values.
    's_sb132'          => 'Declared special activities (SB-132)',
    'sb132_activities' => 'Declared activities',
    'sb132_scene'      => 'Scene(s)',
    'sb132_personnel'  => 'Certified personnel required',
    'act_armas'        => 'Weapons',
    'act_pirotecnia'   => 'Pyrotechnics / SFX',
    'act_stunts'       => 'Stunts',
    'act_aereo'        => 'Aerial',
    'act_agua'         => 'Water',
    'act_offroad'      => 'Off-road',
    'act_fuego'        => 'Open flame / fire',
    'act_altura'       => 'Work at height / rigging',

    // Draft mark (non-final document)
    'draft'      => 'DRAFT',
    'draft_note' => 'Draft document — not the final deliverable for the studio.',

    'compiled_by' => 'Compiled by (production representative)',
    'none'        => '—',
    'btn_print'   => 'Print / PDF',
    'btn_back'    => 'Back',
    'sb132'       => 'Requires Specific Risk Assessment (SB132): special activities were declared.',
];

<?php

/**
 * Expediente clínico del crew (formulario.blade.php + StoreHealthRecordRequest).
 *
 * (2026-07-24 · PIEZA 3) La vista estaba 100 % en español clavado en el HTML, fuera del i18n
 * del resto de la app. Lo llena TODO el crew, y en producciones internacionales hay gente que
 * no lee español: un expediente médico mal entendido no es un problema de comodidad.
 *
 * Convención de claves: f_* etiqueta de campo · h_* ayuda · s_* sección · v_* mensaje de
 * validación · c_* casilla.
 */

return [

    // ---- Cabecera y acciones ----
    'title'          => 'Expediente clínico',
    'intro'          => 'Estos datos los consulta el servicio médico si necesitas atención durante la producción. Se llena una sola vez.',
    'save'           => 'Guardar expediente',
    'saved'          => 'Tu expediente clínico quedó registrado.',
    'already_filed'  => 'Ya tienes un expediente clínico registrado. Si algún dato cambió o quedó mal, plantéalo al servicio médico: es quien puede actualizarlo.',
    'required_note'  => 'Los campos marcados con * son obligatorios.',

    // ---- Secciones ----
    's_general'      => 'Datos generales',
    's_general_sub'  => 'Datos base y contacto de emergencia.',
    's_pathological' => 'Personales patológicas',
    's_pathological_sub' => 'Hospitalizaciones, cirugías, enfermedades, alergias y traumatismos.',
    's_family'       => 'Datos heredo-familiares',
    's_family_sub'   => 'Padecimientos por línea materna y paterna.',
    's_habits'       => 'Datos personales no patológicos',
    's_habits_sub'   => 'Hábitos y estilo de vida.',
    's_vaccines'     => 'Vacunación',
    's_vaccines_sub' => 'Esquema de vacunación vigente.',
    's_gyneco'       => 'Datos gineco-obstétricos',
    's_gyneco_sub'   => 'Antecedentes y detección preventiva.',
    's_prevention'   => 'Prevención',
    's_mother'       => 'Madre',
    's_father'       => 'Padre',

    // ---- Campos ----
    'f_blood'        => 'Tipo de sangre',
    'f_blood_choose' => 'Elige tu tipo de sangre',
    'f_weight'       => 'Peso',
    'f_weight_unit'  => '(Kg)',
    'f_size'         => 'Talla',
    'f_size_unit'    => '(Mts)',
    'f_optional'     => 'opcional',
    'f_contact'      => 'Contacto de emergencia',
    'f_relation'     => 'Parentesco',
    'f_relation_ph'  => 'Pareja, mamá, papá, etc.',
    'f_phone'        => 'Teléfono',
    'f_hospitalizations' => 'Hospitalizaciones',
    'f_hospitalization_n' => 'Hospitalización :n',
    'f_hospitalization_ph' => '¿Hace cuánto? Motivo…',
    'f_surgery'      => 'Quirúrgicas (operaciones)',
    'f_surgery_ph'   => 'Describe brevemente las cirugías y cuándo sucedieron.',
    'f_pathology'    => 'Patológicas (enfermedades diagnosticadas)',
    'f_pathology_ph' => 'Enfermedades que te hayan diagnosticado.',
    'f_allergy'      => 'Alergias (medicamentos y/o alimentos)',
    'f_allergy_ph'   => 'Medicamentos o alimentos a los que seas alérgico.',
    'f_trauma'       => 'Traumáticos (huesos rotos o lesiones en ligamentos)',
    'f_trauma_ph'    => 'Lesiones en huesos, ligamentos o tendones.',
    'f_rythm'        => 'Ritmo (menstruación)',
    'f_rythm_ph'     => 'Regular o irregular',
    'f_pregnant'     => 'Embarazos',
    'f_pregnant_ph'  => 'Ninguno | 1',
    'f_flu_date'     => 'Fecha de aplicación de la influenza',

    // ---- Ayudas ----
    'h_hospitalizations' => 'Al elegir una cantidad se habilitan los campos para describir cada evento.',
    'h_none_switch'  => 'Sin antecedentes',
    'h_none_hint'    => 'Actívalo si no tienes nada que declarar en este campo.',
    'h_none_value'   => 'Ninguna',
    'h_flu_date'     => 'La casilla afirma que la vacuna no tiene más de un año; la fecha es lo que lo sostiene.',
    'h_alive_dead'   => 'Elige una: no se puede estar vivo y fallecido a la vez. Si no lo sabes, deja las dos sin marcar.',

    // ---- Cantidades ----
    'n_hospitalizations' => '{0} 0 hospitalizaciones|{1} 1 hospitalización|[2,*] :count hospitalizaciones',

    // ---- Casillas ----
    'c_alive'        => 'Vivo/Sano',
    'c_dead'         => 'Fallecido',
    'c_diabetes'     => 'Diabetes mellitus (azúcar)',
    'c_hypertension' => 'Hipertensión arterial (presión alta)',
    'c_heart'        => 'Cardiopatías (problemas de corazón)',
    'c_kidney'       => 'Nefropatías (problemas de riñón)',
    'c_cancer'       => 'Neoplasias (tumor o cáncer)',
    'c_tobacco'      => 'Tabaquismo',
    'c_alcohol'      => 'Alcoholismo',
    'c_drugs'        => 'Toxicomanías',
    'c_covid'        => 'COVID-19',
    'c_flu'          => 'Influenza H1N1 (no mayor a un año)',
    'c_tetanus'      => 'Tétanos',
    'c_pneumococcus' => 'Neumococo',
    'c_hepatitis'    => 'Hepatitis B',
    'c_pap'          => 'Papanicolaou',
    'c_mammography'  => 'Mastografía',

    // ---- Validación ----
    'v_blood_required'   => 'Indica tu tipo de sangre.',
    'v_blood_in'         => 'Elige uno de los ocho grupos sanguíneos de la lista.',
    'v_weight_numeric'   => 'El peso debe ser un número en kilogramos (por ejemplo 68.5).',
    'v_weight_between'   => 'Revisa el peso: se espera un valor en kilogramos.',
    'v_size_numeric'     => 'La talla debe ser un número en metros (por ejemplo 1.68).',
    'v_size_between'     => 'Revisa la talla: se espera un valor en metros (1.68, no 168).',
    'v_contact_required' => 'Indica a quién llamar en una emergencia.',
    'v_relation_required'=> 'Indica el parentesco del contacto de emergencia.',
    'v_phone_required'   => 'Indica el teléfono del contacto de emergencia.',
    'v_surgery_required' => 'Declara tus antecedentes quirúrgicos (o marca "Sin antecedentes").',
    'v_pathology_required'=> 'Declara tus antecedentes patológicos (o marca "Sin antecedentes").',
    'v_allergy_required' => 'Declara tus alergias (o marca "Sin antecedentes"). Es el dato que más pesa en una urgencia.',
    'v_trauma_required'  => 'Declara tus antecedentes traumáticos (o marca "Sin antecedentes").',
    'v_rythm_required'   => 'Indica tu ritmo menstrual.',
    'v_pregnant_required'=> 'Indica tus embarazos (escribe "Ninguno" si no aplica).',
    'v_flu_date_required'=> 'Indica cuándo te aplicaron la vacuna de influenza.',
    'v_flu_date_future'  => 'La fecha de la vacuna no puede estar en el futuro.',

    // ================= corrida 2/2 =================

    'yes' => 'Sí',
    'no'  => 'No',

    // ---- Copia al titular ----
    'saved_mailed'      => 'Tu expediente clínico quedó registrado. Te enviamos una copia en PDF a :email para que la revises.',
    'mail_subject'      => 'Tu expediente clínico en CrewCare',
    'mail_title'        => 'Tu expediente clínico',
    'mail_hello'        => 'Hola :nombre:',
    'mail_body_1'       => 'Adjuntamos tu expediente clínico (:folio), tal como quedó registrado el :fecha.',
    'mail_review_title' => 'Por favor revísalo.',
    'mail_review_body'  => 'Comprueba que la información sea real, sobre todo el tipo de sangre, las alergias y el contacto de emergencia: es lo que el servicio médico va a consultar si necesitas atención.',
    'mail_body_2'       => 'Si algo no corresponde o quieres actualizar un dato, plantéalo al servicio médico de la producción: es la única persona que puede ajustarlo, previa valoración. El expediente no se edita para que siempre pueda demostrar qué se declaró y cuándo.',
    'mail_body_3'       => 'Este mensaje se generó automáticamente al registrarse tu expediente.',
    'mail_footer'       => 'CrewCare · Salud y seguridad en producción',

    // ---- PDF ----
    'pdf_declared_on'   => 'Declarado el :fecha',
    'pdf_review_notice' => 'Revisa que esta información sea real. Si algo no corresponde, plantéalo al servicio médico de la producción: es quien puede actualizarlo, previa valoración.',
    'pdf_bmi'           => 'IMC',
    'pdf_bmi_na'        => 'No disponible (falta peso o talla)',
    'pdf_nothing'       => 'Sin datos declarados',
    'pdf_addenda'       => 'Anexos médicos posteriores',
    'pdf_footer'        => 'Expediente :folio · CrewCare. Documento sellado: cualquier cambio posterior aparece como anexo fechado, nunca sobrescribiendo lo anterior.',

    // ---- Anexos ----
    'trace_title'     => 'Anexos y sello del expediente',
    'trace_sub'       => 'Cómo llegó el expediente a su estado actual',
    'trace_add'       => 'Anexar al expediente',
    'trace_add_help'  => 'El expediente no se edita. Un anexo agrega la corrección fechada y firmada, sin borrar lo anterior.',
    'trace_empty'     => 'Sin anexos: el expediente se conserva tal como lo declaró la persona.',
    'trace_by'        => 'Anexó',
    'trace_cedula'    => 'céd.',
    'trace_seal_head' => 'Sello del expediente declarado (:folio)',

    'addendum_title'        => 'Anexo al expediente clínico',
    'addendum_declared_on'  => 'declarado el :fecha',
    'addendum_notice'       => 'El expediente clínico es inmutable: nadie lo edita, tampoco su titular. Un anexo NO sobrescribe nada — agrega un documento fechado, con tu nombre y tu cédula, que queda impreso junto al dato original. Anexa sólo lo que valoraste.',
    'addendum_s_why'        => 'Motivo del anexo',
    'addendum_s_why_sub'    => 'Qué te llevó a actualizar el expediente.',
    'addendum_f_reason'     => 'Tipo de anexo',
    'addendum_f_notes'      => 'Nota clínica',
    'addendum_f_notes_ph'   => 'Qué refirió la persona, qué valoraste y por qué corresponde actualizar el dato.',
    'addendum_f_notes_help' => 'Se imprime en el expediente junto al cambio. Un cambio sin explicación no es un anexo clínico.',
    'addendum_s_data'       => 'Datos del expediente',
    'addendum_s_data_sub'   => 'Cada campo trae su valor vigente. Cambia sólo lo que corresponda: se anexa únicamente la diferencia.',
    'addendum_save'         => 'Firmar y anexar',
    'addendum_cancel'       => 'Cancelar',
    'addendum_saved'        => 'Anexo :folio registrado y sellado. El expediente original queda intacto.',
    'addendum_no_changes'   => 'No cambiaste ningún dato: no se creó el anexo. Si sólo querías dejar una observación, regístrala como consulta.',
    'addendum_only_medic'   => 'Sólo un médico puede anexar al expediente clínico, y tras valorar a la persona.',
    'addendum_no_record'    => 'Esta persona todavía no ha declarado su expediente clínico: no hay a qué anexar.',
    'addendum_unavailable'  => 'El módulo de anexos no está disponible en esta instancia.',
    'v_addendum_notes_required' => 'Explica por qué corresponde este anexo.',
    'v_addendum_notes_min'      => 'La nota clínica es demasiado corta: describe qué valoraste.',
    'v_addendum_reason_required'=> 'Indica de qué tipo de anexo se trata.',

    // ---- Aviso de privacidad ----
    'privacy_title'       => 'Aviso de privacidad',
    'privacy_version'     => 'Versión :version',
    'privacy_draft_title' => 'Versión borrador.',
    'privacy_draft_body'  => 'Este texto está pendiente de redacción legal. Se muestra para que el mecanismo de consentimiento quede operando; cuando se publique el texto definitivo se te volverá a pedir tu aceptación sobre esa versión.',
    'privacy_p_intro'     => 'CrewCare recaba datos de salud para que el servicio médico de la producción pueda atenderte en caso de una urgencia. Antes de pedirte esa información, necesitamos tu consentimiento.',
    'privacy_h_what'      => 'Qué datos se recaban',
    'privacy_p_what'      => 'Tipo de sangre, peso y talla, contacto de emergencia, hospitalizaciones, cirugías, enfermedades diagnosticadas, alergias, lesiones previas, antecedentes familiares, hábitos (tabaquismo, alcoholismo, toxicomanías), esquema de vacunación y, cuando aplica, antecedentes gineco-obstétricos. Son datos personales sensibles.',
    'privacy_h_why'       => 'Para qué se usan',
    'privacy_p_why'       => 'Únicamente para tu atención médica durante la producción y para el cumplimiento de las obligaciones de salud y seguridad en el trabajo. No se usan para decisiones de contratación ni se comparten con fines comerciales.',
    'privacy_h_who'       => 'Quién puede verlos',
    'privacy_p_who'       => 'El personal médico de la producción y, de forma acotada, quien coordina salud y seguridad. Los datos clínicos no se muestran en los listados generales de crew.',
    'privacy_h_immutable' => 'Cómo se conservan',
    'privacy_p_immutable' => 'Tu expediente se sella al momento de declararlo y no se edita: así puede demostrar qué se declaró y cuándo. Si un dato cambia o quedó mal, un médico registra un anexo fechado y firmado; lo anterior no se borra. Recibirás una copia en PDF para revisarla.',
    'privacy_h_rights'    => 'Tus derechos',
    'privacy_p_rights'    => 'Puedes solicitar acceso a tus datos, su corrección vía el servicio médico, y plantear cualquier duda sobre su tratamiento a la coordinación de salud y seguridad de la producción.',
    'privacy_checkbox'    => 'He leído el aviso de privacidad y autorizo el tratamiento de mis datos de salud para los fines descritos.',
    'privacy_accept'      => 'Acepto y continúo',
    'privacy_later'       => 'Ahora no',
    'privacy_later_hint'  => 'Puedes seguir usando CrewCare sin aceptar; sólo el cuestionario de salud queda en espera.',
    'privacy_recorded'    => 'Registramos tu consentimiento.',
    'v_privacy_accept_required' => 'Para continuar tienes que marcar la casilla de aceptación.',
];

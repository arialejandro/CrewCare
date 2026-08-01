<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class locationreport extends Model
{
    use HasFactory;

    protected $table = 'location_report';

    protected $primaryKey = 'id_loc';

    protected $fillable = [
        // Datos generales
        'production_name',
        'scene',
        'name_scene',
        'name_loc',
        'date_prep',
        'date_shoot',
        'date_wrap',
        'hour_prep',
        'hour_shoot',
        'hour_wrap',
        'loc_type',
        'shoot_time',

        // Sección 1: Preguntas para el administrador/propietario
        'owner_1',
        'owner_1_details',
        'owner_2',
        'owner_2_details',
        'owner_3',
        'owner_3_details',
        'owner_3_1',
        'owner_3_2',
        'owner_3_2_details',
        'owner_4',
        'owner_4_details',
        'owner_4_1',
        'owner_4_1_details',
        'owner_5',
        'owner_5_details',
        'owner_5_1',
        'owner_6',
        'owner_6_details',
        'owner_6_1',
        'owner_6_1_details',
        'owner_7',
        'owner_7_details',
        'owner_8',
        'owner_8_details',
        'owner_9',
        'owner_9_details',
        'owner_10',
        'owner_10_details',
        'owner_11',
        'owner_11_details',
        'owner_12',
        'owner_12_details',
        'owner_13',
        'owner_13_details',
        'owner_14',
        'owner_14_details',
        'owner_15',
        'owner_15_details',

        // Sección 2: Inspección visual - Instalaciones
        'inst_16',
        'inst_16_details',
        'inst_17',
        'inst_17_details',
        'inst_18_details',
        'inst_19',
        'inst_19_details',
        'inst_20',
        'inst_20_details',
        'inst_21',
        'inst_21_details',
        'inst_22',
        'inst_22_details',
        'inst_23',
        'inst_23_details',
        'inst_24',
        'inst_24_details',

        // Sección 3: Ventilación
        'vent_25',
        'vent_25_details',
        'vent_26',
        'vent_26_details',
        'vent_27',
        'vent_27_details',
        'vent_28',
        'vent_28_details',
        'vent_29',
        'vent_29_details',

        // Sección 4: Servicios
        'batr_30',
        'batr_30_details',
        'batr_31',
        'batr_31_details',

        // Sección 5: Control de tráfico
        'trafic_32',
        'trafic_32_details',
        'trafic_32_1',
        'trafic_32_2',
        'trafic_32_2_details',
        'trafic_33',
        'trafic_33_details',
        'trafic_34',
        'trafic_34_details',

        // Sección 6: Trabajo en alturas
        'height_35',
        'height_35_details',
        'height_36',
        'height_36_details',
        'height_37',
        'height_37_details',

        // Sección 7: Espacios confinados
        'confi_38',
        'confi_38_details',

        // Sección 8: Consideraciones climáticas
        'wheat_39',
        'wheat_39_details',
        'wheat_39_1',

        // Sección 9: Avisos de seguridad
        'adv_40',
        'adv_40_details',

        // Sección 10: Respuesta a emergencias
        'emer_resp_1',
        'emer_resp_2',
        'emer_resp_3',
        'emer_resp_4',
        'emer_resp_5',
        'emer_resp_6',
        'emer_resp_7',
        'emer_resp_8',
        'emer_resp_hosp',

        // Sección 11: Riesgos adicionales
        'aditional_risk',
        'aditional_cons',

        // Sección 12: Firma y distribución
        'make_by',
        'make_date',

        // Campos para las imágenes
        'main_image_path',
        'additional_images_paths',
    ];

    protected $casts = [
        'additional_images_paths' => 'array',
    ];
}
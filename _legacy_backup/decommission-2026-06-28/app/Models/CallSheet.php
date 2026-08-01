<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * CALL SHEET — "el llamado". Documento central de la jornada de rodaje.
 *
 * Feature estelar del owner: AUTO-ATTACH de boletines de seguridad. La columna
 * `identified_risks` guarda un SNAPSHOT (array JSON) de los boletines aplicables a los
 * riesgos identificados del día: {category, badge, code, url}. Se congela al crear el
 * llamado para que el PDF/vista reflejen exactamente la norma vigente en esa fecha.
 *
 * Vínculos opcionales: a una Production y/o a un DailyReport (de donde se pueden jalar
 * los riesgos ya registrados como logs). Ninguno es obligatorio.
 */
class CallSheet extends Model
{
    use HasFactory;

    protected $table = 'call_sheets';

    // Contrato explícito de columnas escribibles (sin mass-assignment abierto).
    protected $fillable = [
        'production_id',
        'daily_report_id',
        'title',
        'sheet_date',
        'shoot_day',
        'general_call',
        'location_name',
        'location_address',
        'set_setting',
        'day_part',
        'weather_note',
        'sunrise',
        'sunset',
        'nearest_hospital',
        'hospital_address',
        'ambulance_company',
        'emergency_phone',
        'assembly_point',
        'safety_notes',
        'identified_risks',
        'status',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'sheet_date' => 'date',
        'sent_at' => 'datetime',
        // El payload de auto-attach: array de objetos {category, badge, code, url}.
        'identified_risks' => 'array',
    ];

    /**
     * Producción asociada (opcional).
     */
    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    /**
     * Daily Report asociado (opcional). Origen para jalar riesgos del día.
     */
    public function dailyReport()
    {
        return $this->belongsTo(DailyReport::class, 'daily_report_id');
    }

    /**
     * ¿El llamado ya fue enviado al crew?
     */
    public function isSent(): bool
    {
        return $this->status === 'sent';
    }
}

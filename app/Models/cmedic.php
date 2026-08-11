<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

use App\Traits\HasDigitalSignatures;
use App\Traits\GeneratesUuidKey;



class cmedic extends Model
{
    // (2026-07-24) PASO 1/3 médico: el expediente clínico pesa más que los 5 reportes de
    // seguridad y sin embargo era el único SIN sellar. Ahora cada CONSULTA se sella al crearse
    // (SHA-256 + cadena CFDI) y lleva un SNAPSHOT congelado de la cédula de quien atendió: sin
    // congelarlo, cambiar la credencial después movería "quién atendió" sin alterar el hash.
    use HasFactory, HasDigitalSignatures, GeneratesUuidKey;

    protected $table = 'cmedic';

    protected $primaryKey = 'id_cmedic';

    protected $fillable = [
        'id_user',
        'created_by_id',
        'consultation_date',
        'diagnosis',
        'medication',
        'medication_items',
        'observations',
        'aditional',
        // (2026-07-13) Pilar 2 (filtro de ruido): liga OPCIONAL a un accidente laboral.
        // Solo cuando NO es null la consulta se inyecta al DSR (las comunes NO).
        'injury_report_id',
        // (2026-07-24) Identificador público para la cadena CFDI del sello (GeneratesUuidKey).
        'uuid',
        // (2026-07-24) SNAPSHOT de la cédula de quien atendió, CONGELADO al crear la consulta.
        // Es lo que se muestra y lo que entra al payload firmado (el badge en vivo sigue en el
        // perfil del médico; aquí manda el snapshot).
        'medic_cedula',
        'medic_name',
        'medic_cedula_verified',
        // (2026-07-24 · PASO 3/3) MANEJO/CONDUCTA clínica (claves del catálogo de abajo) y marca
        // de que se atendió SIN expediente/intake del paciente.
        'management',
        'without_record',
        // (2026-07-24 · PIEZA 3, corrida 2/2) QUÉ VERSIÓN DEL EXPEDIENTE VIO EL MÉDICO. Si el 3
        // de agosto se atendió sin alergias registradas y el 5 se anexa "penicilina", esta
        // consulta tiene que poder demostrar qué había ENTONCES. Con (formulario_id, addendum_id)
        // el estado es reconstruible exacto —los anexos nunca borran—, y el hash prueba que la
        // reconstrucción es la buena. Entra al payload firmado de la consulta.
        'intake_formulario_id',
        'intake_addendum_id',
        'intake_hash',
        // (2026-07-24 · módulo beta) Paciente NO-crew (extras/visitantes/proveedores). Una consulta
        // tiene id_user (crew) O lite_patient_id (no-crew), NUNCA ambos ni ninguno (XOR en el
        // FormRequest). seal_version DECLARA con qué algoritmo se selló esta fila (ver
        // canonicalSignaturePayload): las viejas = 1, las nuevas = SEAL_VERSION_CURRENT.
        'lite_patient_id',
        'seal_version',
    ];

    /**
     * Versión ACTUAL del algoritmo de sellado. Toda consulta nueva se sella con ésta. Subirla
     * en el futuro NO re-sella las filas viejas: cada una conserva su versión y se verifica con
     * el algoritmo de esa versión (ver canonicalSignaturePayload / getVersionForSeal).
     */
    const SEAL_VERSION_CURRENT = 2;

    /**
     * (2026-07-24 · PASO 3/3, item 2) MANEJO / CONDUCTA CLÍNICA — catálogo cerrado, fuente ÚNICA.
     *
     * No toda consulta lleva medicamento (una valoración, un reposo, una referencia) y sin esto
     * el renglón queda mudo. Equivalente para la consulta del "Tipo de tratamiento" que el Injury
     * ya captura. Es MÚLTIPLE y puede convivir con medicamentos o ir solo.
     *
     * Se guarda la CLAVE, no la etiqueta: las etiquetas cambian con el idioma y con el owner, y
     * la clave es lo que entra al payload sellado. (Mismo criterio que los temas del safety
     * meeting, donde guardar la etiqueta desbordaba el varchar.)
     */
    const MANAGEMENT_OPTIONS = [
        'valoracion' => 'Valoración sin tratamiento',
        'reposo'     => 'Reposo / hidratación',
        'retiro'     => 'Retiro temporal de actividad',
        'curacion'   => 'Curación / vendaje',
        'referencia' => 'Referencia a hospital',
        'observacion'=> 'Observación',
    ];

    /**
     * Etiquetas legibles del manejo guardado. Una clave desconocida (si el owner depura el
     * catálogo) se muestra tal cual en vez de desaparecer: el dato clínico no se pierde en
     * silencio.
     *
     * @return array
     */
    public function managementLabels()
    {
        $keys = $this->management;
        if (! is_array($keys)) {
            return [];
        }
        $out = [];
        foreach ($keys as $k) {
            $out[] = isset(self::MANAGEMENT_OPTIONS[$k]) ? self::MANAGEMENT_OPTIONS[$k] : (string) $k;
        }
        return $out;
    }

    /**
     * (2026-07-25) Medicamentos de ESTA consulta en UNA línea corta y legible, con dosis,
     * presentación y cantidad. Lee el snapshot estructurado `medication_items`; cae al texto libre
     * `medication` de las consultas históricas. '' si no hubo medicamento (el cintillo lo pinta como
     * «Sin medicamento»). FUENTE ÚNICA del cintillo y del historial: antes vivía duplicada de facto
     * como cmedicController::medsShort(), consumida por dos caminos que divergían solos.
     *
     * @return string
     */
    public function medsLine(): string
    {
        $items = $this->medication_items;
        if (is_array($items) && count($items)) {
            $parts = [];
            foreach ($items as $it) {
                $q = $it['quantity'] ?? 1;
                $q = ($q == (int) $q) ? (int) $q : $q;
                $line = trim(($it['name'] ?? '') . ' ' . ($it['dosage'] ?? ''));
                if (($it['presentation'] ?? '') !== '') {
                    $line .= ' (' . $it['presentation'] . ')';
                }
                $parts[] = trim($line) . ' ×' . $q;
            }
            return implode(' · ', $parts);
        }
        return trim((string) ($this->medication ?? ''));
    }

    /**
     * (2026-07-25) La ÚLTIMA consulta de un paciente, para el CINTILLO de tratamiento previo.
     * FUENTE ÚNICA compartida por el camino de crew y el de paciente sin cuenta (antes: dos queries).
     *
     * ⚠ SEGURIDAD (anti-sobredosis): NO se filtra por autor. El cintillo existe justo para que el
     * médico B vea lo que recetó A; aplicarle scopeVisibleTo lo cegaría al tratamiento de otro médico
     * y reintroduciría el riesgo de sumar dosis. Por eso la query es CRUDA (scopeVisibleTo es un scope
     * LOCAL, no global → no se aplica salvo que se invoque, y aquí no se invoca a propósito).
     *
     * @param  int|null    $crewUserId      users.id (paciente crew) o null
     * @param  array<int>  $litePatientIds  ids del GRUPO de identidad (superviviente + fusionados) o []
     * @return \App\Models\cmedic|null
     */
    public static function lastForPatient(?int $crewUserId, array $litePatientIds = []): ?self
    {
        return self::patientScopedQuery($crewUserId, $litePatientIds)
            ->orderByRaw('COALESCE(consultation_date, created_at) DESC')
            ->orderBy('id_cmedic', 'desc')
            ->first();
    }

    /**
     * (2026-07-25) TODAS las consultas de un paciente, más reciente primero, para el HISTORIAL.
     * Cross-médico (NO scopeVisibleTo): el historial clínico es continuidad de atención — un médico
     * que atiende necesita ver TODO el tratamiento previo, no sólo el suyo (misma doctrina que el
     * cintillo). El GATE de quién abre el historial es DOCTOR-ONLY y vive en el controlador/ruta; la
     * nota privada (observations/aditional) NO viaja aquí, sólo la reconciliación (dx/med/manejo).
     *
     * @param  int|null    $crewUserId
     * @param  array<int>  $litePatientIds
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function historyForPatient(?int $crewUserId, array $litePatientIds = [])
    {
        if ($crewUserId === null && empty($litePatientIds)) {
            return self::query()->whereRaw('1 = 0')->get();
        }
        return self::patientScopedQuery($crewUserId, $litePatientIds)
            ->orderByRaw('COALESCE(consultation_date, created_at) DESC')
            ->orderBy('id_cmedic', 'desc')
            ->get();
    }

    /**
     * Base compartida: acota por paciente crew (id_user) o por el GRUPO de ids lite (fusión). XOR:
     * si viene crewUserId manda ése; si no, el grupo lite; si ninguno, una query que no devuelve nada.
     */
    protected static function patientScopedQuery(?int $crewUserId, array $litePatientIds)
    {
        $q = self::query();
        if ($crewUserId !== null) {
            return $q->where('id_user', $crewUserId);
        }
        if (! empty($litePatientIds)) {
            return $q->whereIn('lite_patient_id', array_values($litePatientIds));
        }
        return $q->whereRaw('1 = 0');
    }

    /**
     * (2026-07-24) Columnas del snapshot de cédula EXCLUIDAS del hash de la firma SOLO cuando son
     * null: una consulta sellada sin snapshot (no debería ocurrir, pero defensivo) conserva su
     * hash; con snapshot (consultas nuevas) SÍ lo hashea → la identidad de quien atendió queda
     * cubierta por el sello. `uuid` NO va aquí: el trait ya lo excluye siempre como columna volátil.
     */
    const NULLABLE_HASH_EXCLUDES = [
        'medic_cedula', 'medic_name', 'medic_cedula_verified',
        // (2026-07-24 · PASO 3/3) Igual que arriba: las consultas selladas ANTES de estas columnas
        // las tienen en null → se excluyen y su hash no se mueve (no salen "ALTERADO"). Las nuevas
        // sí las hashean, así que el sello cubre el manejo y el "se atendió sin expediente".
        'management', 'without_record',
        // (2026-07-24 · PIEZA 3, corrida 2/2) Qué versión del expediente vio el médico. Mismo
        // trato: null en las consultas anteriores → fuera del hash → no salen "ALTERADO".
        'intake_formulario_id', 'intake_addendum_id', 'intake_hash',
    ];

    /**
     * Override del payload canónico: replica el del trait (quita volátiles + ksort recursivo) y
     * además excluye las columnas de snapshot cuando su valor es null (ver constante arriba).
     *
     * @return array
     */
    public function canonicalSignaturePayload(): array
    {
        $payload = $this->attributesToArray();

        // Volátiles: nunca entran (el trait ya los quita; se replican por el override).
        foreach (['created_at', 'updated_at', 'uuid'] as $k) {
            unset($payload[$k]);
        }

        // seal_version es METADATO del hash: declara QUÉ algoritmo selló la fila, no puede vivir
        // DENTRO del hash que gobierna, en NINGUNA versión. Su VALOR sí decide el algoritmo (abajo).
        // Si alguien flipea la columna, el recomputo usa otro algoritmo → el hash deja de casar →
        // la fila se marca ALTERADA. Es tamper-evidente sin necesidad de firmar la versión aparte.
        unset($payload['seal_version']);

        if ($this->getVersionForSeal() >= 2) {
            // v2 — ERA DEL PACIENTE LITE. La identidad del paciente ENTRA al sello: id_user y
            // lite_patient_id se hashean SIEMPRE (uno de los dos es null). Un null se serializa de
            // forma DETERMINISTA como JSON `null` — attributesToArray deja la clave PRESENTE con
            // valor null, no la omite —, así que dos verificaciones del mismo documento dan el
            // mismo dígito y el paciente no se puede intercambiar sin romper el hash. (No se hace
            // nada: ambas claves ya están en $payload y NO figuran en NULLABLE_HASH_EXCLUDES.)
        } else {
            // v1 — ALGORITMO ORIGINAL (consultas selladas ANTES de admitir pacientes lite). Las
            // columnas que no existían cuando se selló NO deben entrar, o el hash cambiaría y la
            // fila se auto-acusaría de ALTERADA. lite_patient_id es una de ésas.
            unset($payload['lite_patient_id']);
        }

        // Snapshots OPCIONALES fuera SOLO cuando son null (no mover el hash de filas viejas). OJO:
        // id_user y lite_patient_id NO están en esta lista → en v2 NUNCA se excluyen aunque uno sea
        // null; es a propósito, atan la identidad del paciente.
        foreach (self::NULLABLE_HASH_EXCLUDES as $k) {
            if (array_key_exists($k, $payload) && $payload[$k] === null) {
                unset($payload[$k]);
            }
        }

        $this->ksortRecursive($payload);
        return $payload;
    }

    /**
     * Versión del algoritmo de sellado con que se selló ESTA fila. Filas anteriores al registro
     * lite = v1 (default de la columna en BD). Determinista y defensivo si la columna aún no
     * existe (BD sin el SQL aplicado): asume v1.
     */
    protected function getVersionForSeal(): int
    {
        $v = $this->getAttribute('seal_version');
        return $v === null ? 1 : (int) $v;
    }

    /**
     * (2026-07-24 · PASO 2/3, item 1) AISLAMIENTO POR PROPIEDAD — fuente ÚNICA del alcance de una
     * CONSULTA INDIVIDUAL. Primera vez que la app acota por PROPIEDAD (quién la escribió) y no por
     * rol/departamento: el patrón vive aquí para que ninguna superficie lo reimplemente distinto.
     *
     * REGLA (decisión owner): un paciente puede recibir consultas de médicos distintos, pero
     * CADA MÉDICO SOLO VE LAS SUYAS. El aislamiento es ENTRE MÉDICOS:
     *   · rol `medic` sin key medic  → sólo donde created_by_id = él.
     *   · rol `medic` CON `medical.consolidate` (key medic) → TODAS.
     *   · super-admin → TODAS (pasa por Gate::before en el can() de arriba, sea médico o no).
     *   · cualquier otro rol con medical.view (coordinador, line-producer, HOD, safety-officer
     *     con acceso otorgado) → TODAS las de las personas que YA puede ver. Son figuras que
     *     OBSERVAN, no atienden: no hay secreto médico-a-médico que proteger entre ellas. El
     *     alcance POR PERSONA (depto del HOD) es otro candado y vive en canManageCrewMember().
     *
     * NO se aplica a los reportes AGREGADOS (bitácora semanal y conteo de medicamentos): son
     * documentos de producción y SIEMPRE concentran a todos los médicos (ver MedicalReportController).
     *
     * Consultas históricas SIN created_by_id: quedan fuera para un médico común (no son suyas) y
     * las ven super-admin/coordinador/key medic. Siguen contando en los agregados y en el cintillo
     * de tratamiento previo — el dato clínico importa aunque no se sepa quién lo escribió.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \App\Models\User|null                  $viewer
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVisibleTo($query, $viewer)
    {
        // Sin usuario no hay expediente que valga: err-restrictive (mismo criterio que
        // User::applyDepartmentScope cuando el visor no tiene departamentos).
        if (! $viewer) {
            return $query->whereRaw('1 = 0');
        }

        // Sólo el CLÍNICO (médico o médico beta) se aísla de sus pares. Los demás roles ya
        // vienen acotados por persona. isClinician() incluye a medicbeta para que caiga en la
        // rama de aislamiento por autor de abajo, nunca en este `return $query` (que ve todas).
        if (! $viewer->isClinician()) {
            return $query;
        }

        // Key medic: consolida la función semanal → ve todas. can() incluye al super-admin
        // (Gate::before) y respeta permisos DIRECTOS de Spatie (así se otorga, ver /rolescrud).
        if ($viewer->can('medical.consolidate')) {
            return $query;
        }

        return $query->where('cmedic.created_by_id', $viewer->id);
    }

    /**
     * (2026-07-13) Pilar 2 (Event-Driven): al guardar una consulta se dispara el
     * evento; el Listener SOLO inyecta al DSR si está ligada a un accidente
     * (injury_report_id) — filtro de ruido para mantener la bitácora ágil.
     */
    protected $dispatchesEvents = [
        'saved' => \App\Events\MedicalConsultRecorded::class,
    ];

    /** Accidente laboral al que se liga esta consulta (opcional). */
    public function injuryReport()
    {
        return $this->belongsTo(InjuryReport::class, 'injury_report_id');
    }

    // medication_items: snapshot estructurado de los medicamentos dispensados
    // [{medication_id,name,dosage,presentation,quantity}] — JSON como scouting_reports.
    protected $casts = [
        'medication_items'  => 'array',
        // (2026-07-24) Claves del manejo clínico. MySQL 5.7 → TEXT + cast array (igual que
        // medication_items). `without_record` se deja SIN cast a propósito: un cast boolean
        // convertiría el null de las consultas viejas en false y borraría el tercer estado
        // ("no se sabe" ≠ "sí tenía expediente").
        'management'        => 'array',
        'consultation_date' => 'date',
        // Sólo para lecturas limpias; se EXCLUYE del hash, así que el cast no afecta al sello.
        'seal_version'      => 'integer',
    ];

    // (2026-08-11 upgrade) created_at/updated_at los castea Eloquent solo cuando hay timestamps;
    // el viejo `protected $dates` (deprecado L9+) era redundante → retirado.

    // Paciente atendido (id_user → users.id).
    public function patient()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    // Médico que registró la consulta (autofirma / auditoría).
    public function author()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    // (2026-07-24 · módulo beta) Paciente NO-crew (extras/visitantes/proveedores).
    public function litePatient()
    {
        return $this->belongsTo(LitePatient::class, 'lite_patient_id');
    }

    /** ¿Esta consulta es de un paciente lite (no-crew)? */
    public function isLite(): bool
    {
        return $this->lite_patient_id !== null;
    }
}

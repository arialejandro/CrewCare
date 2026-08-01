<?php

namespace App\Models;

use App\Traits\GeneratesUuidKey;
use App\Traits\HasDigitalSignatures;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * EXPEDIENTE CLÍNICO del crew (tabla `formularios`).
 *
 * No es una encuesta: es el historial médico que un médico consulta en una urgencia. Nació como
 * "encuesta diaria" en el módulo COVID y por eso conserva nombres de columna crípticos
 * (momdat/daddat/pers_nopat/vacci) y la bandera `users.encuestadiaria`. Lo diario se retiró el
 * 2026-07-24: se llena UNA VEZ por producción (ver app/Console/Kernel.php).
 *
 * (2026-07-24 · PIEZA 3) Aquí vive ahora la FUENTE ÚNICA de lectura, `vigenteDe()`. Antes cada
 * consumidor resolvía "el expediente de X" a su manera y NO coincidían:
 *   · cmedicController        → ->first()                 (la fila MÁS ANTIGUA)
 *   · _crew-medical-cards     → ->pluck('alergy','id_user') (se quedaba con la ÚLTIMA)
 *   · historiamr              → @foreach sobre TODAS, repitiendo la ficha entera con ids
 *                               HTML duplicados y sin decir la fecha de ninguna
 * Con una sola fila por persona los tres coincidían por casualidad. Con dos, el badge de la
 * tarjeta y la consulta del médico habrían hablado de expedientes distintos — en un dato
 * (alergias) donde discrepar tiene consecuencias.
 */
class formulario extends Model
{
    use HasFactory;
    // (2026-07-24 · corrida 2/2) El expediente se SELLA al declararse y queda inmutable. El
    // uuid es para el verificador público por QR; el trait de firmas lo excluye del hash.
    use HasDigitalSignatures;
    use GeneratesUuidKey;

    protected $table = 'formularios';

    protected $primaryKey = 'id_formulario';

    /**
     * Las 24 casillas del formulario, por sección. FUENTE ÚNICA: la usan el FormRequest (para
     * declararlas nullable), el controlador (para castear "on" → 1/0) y quien necesite
     * recorrerlas. Añadir una casilla al formulario = añadirla aquí y a `$fillable`.
     *
     * momdat/daddat → antecedentes heredo-familiares (madre / padre), en este orden:
     *   1 Vivo-Sano · 2 Fallecido · 3 Diabetes · 4 Hipertensión · 5 Cardiopatías
     *   6 Nefropatías · 7 Neoplasias
     * pers_nopat → tabaquismo / alcoholismo / toxicomanías
     * vacci      → COVID-19 / Influenza / Tétanos / Neumococo / Hepatitis B
     * prevent    → Papanicolaou / Mastografía (sólo se pintan si sex = F)
     */
    const CHECKBOXES = [
        'momdat1', 'momdat2', 'momdat3', 'momdat4', 'momdat5', 'momdat6', 'momdat7',
        'daddat1', 'daddat2', 'daddat3', 'daddat4', 'daddat5', 'daddat6', 'daddat7',
        'pers_nopat1', 'pers_nopat2', 'pers_nopat3',
        'vacci1', 'vacci2', 'vacci3', 'vacci4', 'vacci5',
        'prevent1', 'prevent2',
    ];

    /**
     * Pares MUTUAMENTE EXCLUYENTES: no se puede estar vivo y fallecido a la vez. Eran dos
     * checkboxes independientes y se podían marcar los dos. El formulario ya lo impide con
     * radios, pero un POST directo no pasa por el formulario: el controlador vuelve a
     * resolverlo con esta lista (si llegan ambos, gana el primero y se apaga el segundo).
     */
    const EXCLUSIVOS = [
        ['momdat1', 'momdat2'],
        ['daddat1', 'daddat2'],
    ];

    /**
     * Campos que un ANEXO MÉDICO puede actualizar, con su etiqueta i18n.
     *
     * Es lista blanca, no "todo lo demás": un anexo corrige DATOS CLÍNICOS DEL PACIENTE y su
     * contacto de emergencia. Deliberadamente NO incluye los antecedentes heredo-familiares ni
     * los hábitos: eso es lo que la persona DECLARÓ de su propia historia, y reescribirlo desde
     * fuera cambiaría el sentido del documento (dejaría de ser una declaración firmada). Si un
     * antecedente familiar resulta relevante, va en la NOTA del anexo, que sí se imprime.
     *
     * El tipo de sangre encabeza la lista a propósito: es el caso que justifica todo el módulo.
     */
    const CAMPOS_ANEXABLES = [
        'blod_type'   => 'health.f_blood',
        'alergy'      => 'health.f_allergy',
        'pathology'   => 'health.f_pathology',
        'cirugy'      => 'health.f_surgery',
        'trauma'      => 'health.f_trauma',
        'height'      => 'health.f_weight',
        'size'        => 'health.f_size',
        'c_emer'      => 'health.f_contact',
        'relation'    => 'health.f_relation',
        'p_emer'      => 'health.f_phone',
        'vacci1'      => 'health.c_covid',
        'vacci2'      => 'health.c_flu',
        'vacci2_date' => 'health.f_flu_date',
        'vacci3'      => 'health.c_tetanus',
        'vacci4'      => 'health.c_pneumococcus',
        'vacci5'      => 'health.c_hepatitis',
    ];

    protected $fillable = [
        'id_user',
        'blod_type',
        'height',
        'size',
        'c_emer',
        'relation',
        'p_emer',
        'hospitals',
        'hsp1', 'hsp2', 'hsp3', 'hsp4', 'hsp5', 'hsp6', 'hsp7', 'hsp8', 'hsp9', 'hsp10',
        'cirugy',
        'pathology',
        'alergy',
        'trauma',
        'momdat1', 'momdat2', 'momdat3', 'momdat4', 'momdat5', 'momdat6', 'momdat7',
        'daddat1', 'daddat2', 'daddat3', 'daddat4', 'daddat5', 'daddat6', 'daddat7',
        'pers_nopat1', 'pers_nopat2', 'pers_nopat3',
        'vacci1', 'vacci2', 'vacci3', 'vacci4', 'vacci5',
        // (2026-07-24) `vacci2_date`: fecha de la influenza. La etiqueta promete vigencia
        // ("no mayor a un año") y sin fecha esa promesa no se podía verificar.
        'vacci2_date',
        // (2026-07-24) `crt19` RETIRADO: era el certificado COVID-19, sin input desde el
        // desacople; entraba siempre con su default. Columna eliminada del esquema.
        'rythm',
        'pregnant',
        'prevent1', 'prevent2',
    ];

    protected $dates = ['created_at', 'updated_at'];

    protected $casts = [
        'vacci2_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }

    /**
     * Anexos médicos, del MÁS ANTIGUO al más reciente. El orden importa: aplicados() los
     * recorre en secuencia y el último gana, así que invertirlo devolvería el dato viejo.
     */
    public function anexos()
    {
        return $this->hasMany(HealthRecordAddendum::class, 'formulario_id', 'id_formulario')
            ->orderBy('created_at')->orderBy('id');
    }

    /**
     * ESTADO VIGENTE = lo declarado + los anexos aplicados, como stdClass.
     *
     * ⚠ POR QUÉ DEVUELVE UNA COPIA Y NO MUTA EL MODELO. `computeDocumentHash()` se arma con
     * `attributesToArray()`. Si aplicáramos los anexos SOBRE el modelo, el hash dejaría de
     * casar con la firma y el expediente se auto-acusaría de "ALTERADO" — justo por hacer bien
     * lo que el módulo promete. El modelo queda intacto (y su sello verifica); los valores
     * clínicos se leen de aquí. Mismo criterio que HasMedicalAddendums en el Injury: valores
     * "efectivos" al lado del documento original, nunca encima.
     *
     * @return \stdClass
     */
    public function aplicados()
    {
        $valores = (object) $this->attributesToArray();

        if (! HealthRecordAddendum::supported()) {
            return $valores;
        }

        foreach ($this->anexos as $anexo) {
            $cambios = $anexo->camposCambiados();
            foreach ($cambios as $campo => $valor) {
                // Doble llave: sólo campos de la lista blanca entran, aunque en BD hubiera otra
                // cosa guardada. La validación ya lo filtra al crear; esto cubre el histórico.
                if (array_key_exists($campo, self::CAMPOS_ANEXABLES)) {
                    $valores->$campo = $valor;
                }
            }
        }

        return $valores;
    }

    /**
     * ¿Tiene anexos? Atajo legible para las vistas (y evita cargar la relación dos veces).
     *
     * @return bool
     */
    public function tieneAnexos()
    {
        return HealthRecordAddendum::supported() && $this->anexos->isNotEmpty();
    }

    /**
     * El ÚLTIMO anexo vigente, o null. Lo usa la consulta médica para dejar constancia de qué
     * versión del expediente vio (cmedic.intake_addendum_id).
     *
     * @return \App\Models\HealthRecordAddendum|null
     */
    public function ultimoAnexo()
    {
        return HealthRecordAddendum::supported() ? $this->anexos->last() : null;
    }

    /**
     * Huella del ESTADO VIGENTE (no del documento original). Es lo que la consulta congela en
     * `intake_hash`: permite demostrar después que la reconstrucción de "lo que vio el médico"
     * es la buena, sin guardar una copia del expediente dentro de cada consulta.
     *
     * @return string
     */
    public function hashVigente()
    {
        $valores = (array) $this->aplicados();
        unset($valores['created_at'], $valores['updated_at'], $valores['uuid']);
        ksort($valores);

        return hash('sha256', json_encode($valores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Folio impreso y del acuse público del verificador. */
    public function folio()
    {
        return 'EXP-' . str_pad((string) $this->getKey(), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Etiqueta legible (i18n) de una columna. La usa la traza de anexos para no imprimir
     * nombres de columna heredados del COVID ("vacci2") en un documento clínico.
     *
     * @param  string  $campo
     * @return string
     */
    public static function etiquetaDe($campo)
    {
        return isset(self::CAMPOS_ANEXABLES[$campo]) ? __(self::CAMPOS_ANEXABLES[$campo]) : $campo;
    }

    /**
     * Valor legible de un campo anexado: las casillas se imprimen Sí/No en vez de 1/0.
     *
     * @param  string  $campo
     * @param  mixed   $valor
     * @return string
     */
    public static function valorLegible($campo, $valor)
    {
        if (in_array($campo, self::CHECKBOXES, true)) {
            return $valor ? __('health.yes') : __('health.no');
        }

        if ($valor === null || $valor === '') {
            return '—';
        }

        return (string) $valor;
    }

    /**
     * EL EXPEDIENTE VIGENTE de una persona, o null si no tiene.
     *
     * "Vigente" = la fila MÁS RECIENTE. Hoy debería haber exactamente una por persona (el
     * expediente se llena una vez), pero la tabla admite N —no hay UNIQUE en `id_user`— y la
     * reapertura manual del cuestionario puede sembrar otra. Ante dos, la respuesta correcta
     * es SIEMPRE la última declarada: un expediente viejo no puede ganarle a uno nuevo cuando
     * el médico pregunta por alergias.
     *
     * ⚠ Ordena por `created_at` y desempata por PK. Sin el desempate, dos filas creadas dentro
     * del mismo segundo (el timestamp de MySQL no guarda fracciones aquí) devolverían
     * cualquiera de las dos según el plan del optimizador — no determinista.
     *
     * En la corrida 2/2 este método pasa a devolver el original CON SUS ANEXOS APLICADOS. Todo
     * el resto de la app ya lee por aquí, así que ese cambio no toca ningún consumidor.
     *
     * @param  int|null  $idUser
     * @return \App\Models\formulario|null
     */
    public static function vigenteDe($idUser)
    {
        if (! $idUser) {
            return null;
        }

        $consulta = static::where('id_user', $idUser)
            ->orderByDesc('created_at')
            ->orderByDesc('id_formulario');

        // Los anexos viajan con el expediente: quien lo pide va a leer valores clínicos, y sin
        // ellos leería el dato viejo. Eager-load para no disparar una consulta por lectura.
        if (HealthRecordAddendum::supported()) {
            $consulta->with('anexos');
        }

        return $consulta->first();
    }

    /**
     * Variante para LISTADOS: el expediente vigente de MUCHAS personas en UNA consulta.
     * La usa la tarjeta de crew (badge "Expediente pendiente") sobre la página paginada, que
     * no puede permitirse un query por persona.
     *
     * Devuelve [id_user => fila]. Mismo criterio de "vigente" que vigenteDe(): se ordena
     * ASCENDENTE y se deja que las filas posteriores pisen a las anteriores en el mapa, así
     * la que queda es la más reciente.
     *
     * @param  array  $idsUsuarios
     * @return array
     */
    public static function vigentesDe(array $idsUsuarios)
    {
        if (empty($idsUsuarios)) {
            return [];
        }

        $consulta = static::whereIn('id_user', $idsUsuarios)
            ->orderBy('created_at')
            ->orderBy('id_formulario');

        if (HealthRecordAddendum::supported()) {
            $consulta->with('anexos');
        }

        $filas = $consulta->get();

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[$fila->id_user] = $fila;
        }

        return $mapa;
    }

    /**
     * ¿Está COMPLETO el expediente? Testigo: `alergy`.
     *
     * "Existe fila" ≠ "está completo": una fila vacía bastaba para pasar el bloqueo viejo. Se
     * mira la alergia porque es EL campo que cambia una decisión clínica — los demás
     * antecedentes informan, la alergia contraindica.
     *
     * (2026-07-24 · corrida 2/2) Mira el estado VIGENTE, no lo declarado: si el expediente
     * nació sin alergias y un médico anexó "penicilina", está completo. Al revés también
     * cuenta — leer lo declarado seguiría avisando "sin alergias registradas" cuando ya las
     * hay, y ese aviso es lo que hace que el médico pregunte.
     *
     * @param  \App\Models\formulario|object|null  $fila
     * @return string  'missing' | 'incomplete' | 'ok'
     */
    public static function estadoDe($fila)
    {
        if (! $fila) {
            return 'missing';
        }

        $valores = ($fila instanceof self) ? $fila->aplicados() : $fila;
        $alergia = isset($valores->alergy) ? trim((string) $valores->alergy) : '';

        return $alergia === '' ? 'incomplete' : 'ok';
    }

    /**
     * Fecha de llenado LEGIBLE. `users.lastwr` NO sirve para esto: CrewController lo pone en
     * now() al DAR DE ALTA al usuario, así que los 92 usuarios lo tienen aunque sólo 3 hayan
     * llenado el expediente. La fecha real del expediente es su propio `created_at`.
     *
     * @param  string  $formato
     * @return string
     */
    public function fechaLlenado($formato = 'd/m/Y')
    {
        return $this->created_at ? $this->created_at->format($formato) : '—';
    }

    /**
     * IMC, o null si falta peso o talla. Devuelve null EN LUGAR de estimar: el hueco se
     * declara, no se rellena (misma doctrina que el reporte de wrap).
     *
     * @return float|null
     */
    public function imc()
    {
        $peso  = (float) $this->height;   // Kg  (la columna se llama `height` por herencia)
        $talla = (float) $this->size;     // Mts (y `size` es la estatura: nombres heredados)

        if ($peso <= 0 || $talla <= 0) {
            return null;
        }

        return round($peso / ($talla * $talla), 1);
    }

    /**
     * ¿Existe ya la columna de la fecha de influenza? Permite que la vista y el controlador
     * funcionen ANTES de que el owner corra el SQL en producción. Memo estático: Schema::
     * hasColumn no está cacheado en Laravel 8 y pega a information_schema cada vez.
     *
     * @return bool
     */
    public static function soportaFechaInfluenza()
    {
        static $soporta = null;

        if ($soporta === null) {
            try {
                $soporta = Schema::hasColumn('formularios', 'vacci2_date');
            } catch (\Throwable $e) {
                $soporta = false;
            }
        }

        return $soporta;
    }
}

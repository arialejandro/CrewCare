<?php

namespace App\Models;

use App\Traits\GeneratesUuidKey;
use App\Traits\HasDigitalSignatures;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * ANEXO AL EXPEDIENTE CLÍNICO (2026-07-24 · PIEZA 3, corrida 2/2).
 *
 * EL EXPEDIENTE NO SE EDITA. Ni el titular puede: si fuera editable, alguien podría ocultar
 * una enfermedad crónica retroactivamente ante un seguro o una reclamación, y el expediente
 * existe justamente para demostrar QUÉ SE DECLARÓ Y CUÁNDO.
 *
 * Pero un tipo de sangre equivocado congelado para siempre puede MATAR a una persona. El anexo
 * no es una comodidad: es el requisito de seguridad que hace tolerable la inmutabilidad. Y lo
 * crea SÓLO un médico, tras valorar — como funciona un expediente clínico real: el paciente no
 * edita su historia, el profesional la actualiza y lo documenta.
 *
 * APPEND-ONLY, igual que el addendum médico del Injury ([[injury-doc-two-outputs]]):
 *   · nunca sobrescribe: el original conserva folio, hash y QR;
 *   · va fechado, con su PROPIO sello y la cédula del médico CONGELADA;
 *   · dice QUÉ cambia (`changes`) y POR QUÉ (`notes`, obligatorio);
 *   · la ficha muestra el estado vigente MÁS la traza cronológica.
 *
 * EFECTO DISUASORIO DELIBERADO: quien intentara maquillar algo dejaría un anexo firmado, con
 * su nombre y su cédula, al lado del original que dice lo contrario. Eso crea evidencia del
 * intento — más fuerte que bloquear, porque bloquear sólo empuja el problema a otro lado.
 */
class HealthRecordAddendum extends Model
{
    use HasFactory;
    use HasDigitalSignatures;
    use GeneratesUuidKey;

    protected $table = 'health_record_addendums';

    /**
     * Motivos posibles. Lista cerrada: un anexo sin categoría se vuelve un cajón de sastre y
     * deja de servir para leer la historia de un expediente de un vistazo.
     */
    const MOTIVOS = [
        'correccion' => 'Corrección de un dato mal capturado',
        'vacuna'     => 'Vacuna nueva o refuerzo',
        'condicion'  => 'Condición detectada en valoración',
        'otro'       => 'Otro (se explica en la nota)',
    ];

    protected $fillable = [
        'uuid', 'formulario_id', 'reason', 'changed_fields', 'notes', 'created_by_id',
        'medic_cedula', 'medic_name', 'medic_cedula_verified',
    ];

    protected $casts = [
        // MySQL 5.7 no tiene tipo JSON usable aquí; TEXT + cast array, misma convención que
        // cmedic.medication_items.
        'changed_fields' => 'array',
    ];

    /**
     * Columnas de snapshot que se EXCLUYEN del hash cuando son null, para que un anexo
     * sellado antes de que existieran no se marque "ALTERADO". Mismo patrón que cmedic.
     */
    const NULLABLE_HASH_EXCLUDES = ['medic_cedula', 'medic_name', 'medic_cedula_verified'];

    public function canonicalSignaturePayload(): array
    {
        $payload = $this->attributesToArray();
        foreach (['created_at', 'updated_at', 'uuid'] as $k) {
            unset($payload[$k]);
        }
        foreach (self::NULLABLE_HASH_EXCLUDES as $k) {
            if (array_key_exists($k, $payload) && $payload[$k] === null) {
                unset($payload[$k]);
            }
        }
        $this->ksortRecursive($payload);
        return $payload;
    }

    /**
     * ¿Existe la tabla? Apaga el módulo entero si el owner no aplicó el SQL, sin romper nada:
     * el expediente se ve igual, simplemente sin traza ni botón de anexar. Memo estático
     * porque Schema::hasTable no está cacheado en Laravel 8.
     *
     * @return bool
     */
    public static function supported()
    {
        static $existe = null;

        if ($existe === null) {
            try {
                $existe = Schema::hasTable('health_record_addendums');
            } catch (\Throwable $e) {
                $existe = false;
            }
        }

        return $existe;
    }

    public function expediente()
    {
        return $this->belongsTo(formulario::class, 'formulario_id', 'id_formulario');
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'created_by_id', 'id');
    }

    /** Folio impreso y del acuse público: EXPA-0001. */
    public function folio()
    {
        return 'EXPA-' . str_pad((string) $this->getKey(), 4, '0', STR_PAD_LEFT);
    }

    /** Etiqueta legible del motivo. */
    public function motivoLabel()
    {
        return isset(self::MOTIVOS[$this->reason]) ? self::MOTIVOS[$this->reason] : $this->reason;
    }

    /**
     * Los campos que cambia este anexo: [columna => valor nuevo].
     *
     * ⚠ ACCESOR EXPLÍCITO, NO `$anexo->changed_fields` A PELO — y menos aún una columna llamada
     * `changes`. Eloquent declara `protected $changes` (rastreo de cambios tras save) en
     * HasAttributes. Como TODOS los modelos heredan de Model, leer `$otroModelo->changes` desde
     * DENTRO de otro modelo es acceso legítimo a un miembro protegido para PHP: devuelve el
     * arreglo interno de Eloquent —vacío— sin pasar por __get() ni por el cast. Costó encontrarlo
     * porque falla SÓLO en ese contexto: desde una vista o un script el valor salía correcto, y
     * los anexos parecían guardarse bien pero no se aplicaban nunca.
     *
     * La columna ya se llama `changed_fields` para que la colisión no exista, y este método
     * mantiene un único punto de lectura por si mañana cambia el almacenamiento.
     *
     * @return array
     */
    public function camposCambiados()
    {
        $valor = $this->getAttribute('changed_fields');

        return is_array($valor) ? $valor : [];
    }

    /**
     * Los cambios en forma legible: [['campo' => etiqueta, 'valor' => valor nuevo], …].
     * Traduce la clave de columna a la etiqueta que la persona vio en el formulario, para que
     * la traza se lea sin conocer el esquema.
     *
     * @return array
     */
    public function cambiosLegibles()
    {
        $salida = [];

        foreach ($this->camposCambiados() as $campo => $valor) {
            $salida[] = [
                'campo' => formulario::etiquetaDe($campo),
                'valor' => formulario::valorLegible($campo, $valor),
            ];
        }

        return $salida;
    }
}

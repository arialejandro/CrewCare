<?php

namespace App\Traits;

use App\Models\DigitalSignature;
use Illuminate\Support\Facades\Schema;

/**
 * Trait HasDigitalSignatures — Firmas digitales / no-repudio (SHA-256).
 *
 * Da a un reporte de seguridad (Hazard / Unsafe / Injury / Scouting / Daily):
 *   - signatures()               relación polimórfica morphMany.
 *   - canonicalSignaturePayload() array DETERMINISTA (ordenado) del documento,
 *                                 sin claves volátiles → base del hash.
 *   - computeDocumentHash()       SHA-256 del payload canónico (JSON).
 *   - signDocument()              crea la firma (hash + firmante + metadatos req).
 *   - verifyLatestSignature()     ¿el documento actual sigue casando con la
 *                                 última firma? (detección de manipulación).
 *
 * DEFENSIVO: si la tabla digital_signatures aún no existe (owner no aplicó el
 * SQL), signDocument() regresa null y nada truena. Un modelo puede sobreescribir
 * canonicalSignaturePayload() o declarar `protected $signatureExcludes = [...]`
 * para excluir columnas adicionales del hash.
 */
trait HasDigitalSignatures
{
    /**
     * Firmas digitales de este documento (morphMany polimórfica).
     */
    public function signatures()
    {
        return $this->morphMany(DigitalSignature::class, 'documentable');
    }

    /**
     * Payload DETERMINISTA para hashear. Toma attributesToArray(), elimina claves
     * volátiles (created_at/updated_at/uuid) y cualquier clave de $signatureExcludes,
     * aplica las exclusiones CONDICIONALES EN NULL (const NULLABLE_HASH_EXCLUDES del
     * modelo), luego ordena recursivamente por clave (ksort recursivo). Sobreescribible.
     *
     * @return array
     */
    public function canonicalSignaturePayload(): array
    {
        $payload = $this->attributesToArray();

        // Exclusiones INCONDICIONALES: volátiles + $signatureExcludes (fuera SIEMPRE).
        $volatile = ['created_at', 'updated_at', 'uuid'];
        if (isset($this->signatureExcludes) && is_array($this->signatureExcludes)) {
            $volatile = array_merge($volatile, $this->signatureExcludes);
        }
        foreach ($volatile as $key) {
            unset($payload[$key]);
        }

        // Exclusiones CONDICIONALES EN NULL (const NULLABLE_HASH_EXCLUDES del modelo): la columna
        // sale del hash SOLO cuando su valor es null. Así una columna añadida DESPUÉS de que ya
        // había filas selladas no mueve su hash (la traían en null → se excluye), pero en cuanto
        // lleva valor SÍ se sella (queda cubierta contra manipulación). Patrón PROMOVIDO a la base
        // (2026-09-05, Unidades P1) desde el override que ya vivía en DailyReport / unsafecond /
        // hazardnotification / HealthRecordAddendum. Esos modelos SOBRESCRIBEN este método y aplican
        // su propia const en su override, así que NO pasan por aquí (no hay doble exclusión);
        // IssuedPermit sí pasa, porque su override delega en este método vía alias.
        foreach ($this->nullableHashExcludes() as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] === null) {
                unset($payload[$key]);
            }
        }

        $this->ksortRecursive($payload);

        return $payload;
    }

    /**
     * Columnas a excluir del hash SOLO cuando son null. Fuente: la const NULLABLE_HASH_EXCLUDES del
     * modelo si la declara; [] si no. Sin la const este método es no-op y el hash NO cambia — por eso
     * promover el patrón al trait no altera el sello de ningún modelo que no declare la const.
     *
     * @return array
     */
    protected function nullableHashExcludes(): array
    {
        if (defined(static::class . '::NULLABLE_HASH_EXCLUDES')) {
            $list = constant(static::class . '::NULLABLE_HASH_EXCLUDES');

            return is_array($list) ? $list : [];
        }

        return [];
    }

    /**
     * HMAC-SHA256 (clave dedicada) del payload canónico (JSON estable).
     *
     * Antes era hash('sha256', ...) SIN clave: el sello era reproducible por
     * cualquiera que conociera el payload → se podía fabricar. Ahora es HMAC con
     * `config('crewcare.seal.key')` (CREWCARE_SEAL_KEY). Fallback a app.key (siempre
     * presente) si la clave dedicada no está configurada, para no volver NUNCA a un
     * hash sin clave. Verificar y sellar usan este mismo método → misma clave, casan.
     *
     * @return string
     */
    public function computeDocumentHash(): string
    {
        $key = (string) (config('crewcare.seal.key') ?: config('app.key'));

        return hash_hmac(
            'sha256',
            json_encode($this->canonicalSignaturePayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $key
        );
    }

    /**
     * Firma el documento: guarda el hash actual + firmante + metadatos de request.
     * No-op seguro (regresa null) si la tabla digital_signatures aún no existe.
     *
     * @param  \App\Models\User|null                    $user
     * @param  \Illuminate\Http\Request|null            $request
     * @return \App\Models\DigitalSignature|null
     */
    public function signDocument($user = null, $request = null)
    {
        if (!Schema::hasTable('digital_signatures')) {
            return null;
        }

        // Firmante: el $user recibido o, en su defecto, el autenticado.
        $actingUser = $user;
        if ($actingUser === null && auth()->check()) {
            $actingUser = auth()->user();
        }

        // role_at_signing: primer rol (Spatie) de forma defensiva; null si no se puede.
        $role = null;
        try {
            if ($actingUser !== null && method_exists($actingUser, 'getRoleNames')) {
                $names = $actingUser->getRoleNames();
                if ($names !== null && method_exists($names, 'first')) {
                    $role = $names->first();
                }
            }
        } catch (\Throwable $e) {
            $role = null;
        }

        return $this->signatures()->create([
            'user_id'         => $user ? $user->id : auth()->id(),
            'role_at_signing' => $role,
            'ip_address'      => $request ? $request->ip() : null,
            'user_agent'      => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            'document_hash'   => $this->computeDocumentHash(),
            'signed_at'       => now(),
        ]);
    }

    /**
     * Sella el documento COMO SISTEMA: sin persona firmante (user_id NULL).
     *
     * (2026-07-22) Nace para el auto-sellado del DSR al cerrarse el día. El sellado a las
     * 24 h no lo ejecuta nadie: lo dispara el paso del tiempo. Atribuirlo a quien casualmente
     * abrió el reporte —que es lo que haría signDocument(), porque cae en auth()->id()—
     * sería FALSIFICAR el firmante en un documento que existe justamente para ser creíble.
     * Por eso user_id queda NULL (la columna lo admite y no tiene FK) y role_at_signing
     * declara el motivo. La vista lo muestra como "CrewCare (sello automático del sistema)".
     *
     * OJO: esto es la firma SHA de INTEGRIDAD, no una firma autógrafa. Dice "este documento
     * no ha cambiado desde este instante", no "fulano está de acuerdo con su contenido".
     *
     * @param  string $motivo  queda en role_at_signing (p. ej. 'sistema:cierre-24h')
     * @return \App\Models\DigitalSignature|null
     */
    public function signDocumentAsSystem($motivo = 'sistema')
    {
        if (!Schema::hasTable('digital_signatures')) {
            return null;
        }

        return $this->signatures()->create([
            'user_id'         => null,
            'role_at_signing' => $motivo,
            'ip_address'      => null,
            'user_agent'      => null,
            'document_hash'   => $this->computeDocumentHash(),
            'signed_at'       => now(),
        ]);
    }

    /**
     * ¿El documento actual sigue casando con su ÚLTIMA firma?
     * null si no hay firmas; true/false según hash_equals (constant-time).
     *
     * @return bool|null
     */
    public function verifyLatestSignature()
    {
        if (!Schema::hasTable('digital_signatures')) {
            return null;
        }

        $sig = $this->signatures()->latest('id')->first();
        if (!$sig) {
            return null;
        }

        return (bool) hash_equals($sig->document_hash, $this->computeDocumentHash());
    }

    /**
     * Ordena un array recursivamente por clave (in-place). Estabiliza el JSON del
     * hash independientemente del orden de inserción de atributos/sub-arreglos.
     *
     * @param  array $array
     * @return void
     */
    protected function ksortRecursive(array &$array)
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
        unset($value);
        ksort($array);
    }
}

<?php

namespace App\Traits;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;

/**
 * Trait HasMagicMitigation — Pilar 1b: Magic Links para cierre de acciones correctivas.
 *
 * Da a un ActionItem el canal "click-to-chat" (wa.me) + una URL FIRMADA y EXPIRABLE
 * (Laravel signed routes) hacia una página PÚBLICA (sin auth) donde el responsable
 * sube la foto de la acción correctiva ejecutada.
 *
 *   - mitigationUrl()  URL firmada temporal (7 días) a la página pública de subida.
 *   - waLink()         enlace https://wa.me/… con el mensaje pre-armado (sólo dígitos).
 *   - hasMitigation()  ¿ya llegó la foto de mitigación? (guard defensivo de columna).
 *
 * DEFENSIVO: hasMitigation() usa Schema::hasColumn — si el owner aún no aplicó el SQL
 * (columna mitigation_image_path inexistente), regresa false sin truenos.
 * Feature-gated aguas arriba con @feature('magic_links') / Features::enabled('magic_links').
 */
trait HasMagicMitigation
{
    /**
     * URL firmada y expirable (7 días) hacia la página pública de subida de foto.
     * Sin guard de columna: es una ruta, no depende de datos de la tabla.
     *
     * @return string
     */
    public function mitigationUrl()
    {
        return URL::temporarySignedRoute(
            'mitigation.show',
            now()->addDays(7),
            ['action' => $this->id]
        );
    }

    /**
     * Enlace WhatsApp (click-to-chat) con el mensaje pre-armado para el responsable.
     * $phone se normaliza a sólo dígitos; si queda vacío se usa wa.me sin número
     * (WhatsApp abre el selector de contacto). El texto va URL-encodeado.
     *
     * @return string
     */
    public function waLink()
    {
        $phone = preg_replace('/\D/', '', (string) ($this->responsible_phone ?? ''));

        $msg = "Hola " . ($this->responsible_name ?? '')
            . ", por favor sube la foto de la acción correctiva: "
            . $this->mitigationUrl();

        if ($phone === '') {
            return 'https://wa.me/?text=' . rawurlencode($msg);
        }

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($msg);
    }

    /**
     * ¿Ya se recibió la foto de mitigación? Guard defensivo de columna.
     *
     * @return bool
     */
    public function hasMitigation()
    {
        // Memo estático (mismo patrón que MedicCredential::supportsCredentials /
        // SafetyStandard::supportsActiveFlag): Schema::hasColumn NO está cacheado en
        // Laravel 8 y cada llamada consulta information_schema. Este método se invoca
        // una vez por acción correctiva al pintar un reporte, así que sin el memo un
        // DSR con 8 hallazgos gastaba una decena de consultas en preguntar lo mismo.
        // El esquema no cambia a mitad de request, así que memorizarlo es seguro.
        static $existe = null;
        if ($existe === null) {
            $existe = Schema::hasColumn('action_items', 'mitigation_image_path');
        }
        if (! $existe) {
            return false;
        }

        return ! empty($this->mitigation_image_path);
    }
}

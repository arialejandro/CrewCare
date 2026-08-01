<?php

namespace App\Support;

/**
 * CedulaResult — el resultado de consultar la fuente pública de cédulas (BúhoLegal), en un
 * objeto que NO obliga al llamador a interpretar HTML ni códigos HTTP.
 *
 * TRES estados, y la diferencia entre ellos decide la telemetría del Paso B:
 *   - found : la fuente respondió y hay al menos una fila de resultados.
 *   - empty : la fuente respondió BIEN, pero el número no existe en el registro. Es una
 *             señal de NEGOCIO (número inválido), NO un fallo de la fuente → sin correo.
 *   - error : red caída, timeout, HTML cambiado o no parseable → 'fuente_caida' → correo.
 *
 * NUNCA nace de una excepción propagada: CedulaVerifier atrapa todo y lo traduce a error().
 */
class CedulaResult
{
    const STATUS_FOUND = 'found';
    const STATUS_EMPTY = 'empty';
    const STATUS_ERROR = 'error';

    /** @var string */
    public $status = self::STATUS_ERROR;

    /** @var string */
    public $cedula = '';

    /** @var string|null Motivo técnico (solo en error): 'no_csrf', 'no_table', 'http_post_503'… */
    public $reason = null;

    /** @var int|null Código HTTP de la última respuesta (diagnóstico). */
    public $httpStatus = null;

    /** @var string|null Nombre completo del titular según el registro (mejor fila). */
    public $name = null;

    /** @var string|null Carrera de nivel licenciatura (tipo C1). */
    public $profession = null;

    /** @var string|null Carrera de nivel especialidad (tipo E/A). */
    public $specialty = null;

    /** @var string Etiqueta de nivel de la primera fila (Licenciatura/Especialidad/…). */
    public $level = '';

    /** @var array Todas las filas parseadas, crudas — se guardan íntegras en el snapshot. */
    public $rows = [];

    /** @var string Diagnóstico legible. */
    public $message = '';

    public function isFound(): bool { return $this->status === self::STATUS_FOUND; }
    public function isEmpty(): bool { return $this->status === self::STATUS_EMPTY; }
    public function isError(): bool { return $this->status === self::STATUS_ERROR; }

    public static function error(string $reason, $httpStatus = null, string $message = ''): self
    {
        $r = new self();
        $r->status     = self::STATUS_ERROR;
        $r->reason     = $reason;
        $r->httpStatus = $httpStatus;
        $r->message    = $message;
        return $r;
    }

    /** Nombrado makeEmpty (no `empty`) para no chocar con la construcción `empty()` del lenguaje. */
    public static function makeEmpty(string $cedula): self
    {
        $r = new self();
        $r->status = self::STATUS_EMPTY;
        $r->cedula = $cedula;
        return $r;
    }

    /**
     * Deriva los campos útiles a partir de las filas crudas.
     *
     * Un titular puede tener VARIAS filas (p. ej. una licenciatura y una especialidad). El
     * nombre es común a todas; profesión = carrera de la fila licenciatura (C1), especialidad
     * = carrera de la fila E/A. Así el resultado mapea limpio a las columnas profession /
     * specialty de medic_credentials.
     */
    public static function fromRows(string $cedula, array $rows): self
    {
        $r = new self();
        $r->status = self::STATUS_FOUND;
        $r->cedula = $cedula;
        $r->rows   = array_values($rows);

        foreach ($r->rows as $row) {
            if (($row['nombre'] ?? '') !== '') { $r->name = $row['nombre']; break; }
        }
        foreach ($r->rows as $row) {
            $level   = CedulaVerifier::levelFromTipo($row['tipo'] ?? '');
            $carrera = trim((string) ($row['carrera'] ?? ''));
            if ($carrera === '') { continue; }
            if ($level === 'Especialidad' && $r->specialty === null) { $r->specialty = $carrera; }
            if ($level === 'Licenciatura' && $r->profession === null) { $r->profession = $carrera; }
        }
        // Fallback: si ninguna fila trae 'tipo' reconocible, usa la carrera de la primera fila.
        if ($r->profession === null) {
            foreach ($r->rows as $row) {
                if (($row['carrera'] ?? '') !== '') { $r->profession = $row['carrera']; break; }
            }
        }
        $r->level = isset($r->rows[0]) ? CedulaVerifier::levelFromTipo($r->rows[0]['tipo'] ?? '') : '';
        return $r;
    }

    /** ¿El titular tiene al menos una ESPECIALIDAD registrada? (dato de gestión de riesgo). */
    public function isSpecialist(): bool
    {
        foreach ($this->rows as $row) {
            if (CedulaVerifier::levelFromTipo($row['tipo'] ?? '') === 'Especialidad') {
                return true;
            }
        }
        return false;
    }
}

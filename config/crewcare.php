<?php

// Se declara UNA vez y las claves de abajo la derivan: si el número se repitiera
// literalmente en dos claves, volveríamos a tener el problema que este archivo
// viene a resolver, sólo que con dos sitios en vez de ocho.
$version = '3.5';

// Clave del sello (HMAC): decodifica el prefijo `base64:` (igual que APP_KEY) para
// hashear con bytes crudos. Vacía si no está configurada → el trait cae a APP_KEY.
$sealKey = (string) env('CREWCARE_SEAL_KEY', '');
if (str_starts_with($sealKey, 'base64:')) {
    $sealKey = base64_decode(substr($sealKey, 7)) ?: $sealKey;
}

return [

    /*
    |--------------------------------------------------------------------------
    | Versión del producto
    |--------------------------------------------------------------------------
    |
    | Fuente ÚNICA de la versión. Antes vivía como la cadena literal 'VER 2.1'
    | repetida en 8 vistas (los 5 reportes vivos + 4 fantasmas -legacy), así que
    | cada documento firmable afirmaba una versión que dejó de ser cierta hace
    | mucho: el pie decía 2.1 cuando la app ya iba muy por delante.
    |
    | Importa más de lo que parece porque el número se IMPRIME en el pie de un
    | documento sellado. Cuando dentro de dos años alguien coteje un acta, el pie
    | tiene que decir con qué versión del sistema se generó — es parte de poder
    | reproducir el resultado, igual que el hash.
    |
    | Se sube A MANO al cerrar cada tanda de cambios. No se deriva de git ni del
    | composer.json a propósito: la versión del PRODUCTO es una decisión editorial
    | del owner, no un efecto secundario de un commit.
    |
    */
    'version' => $version,

    /*
    |--------------------------------------------------------------------------
    | Etiqueta de versión para el pie de los documentos
    |--------------------------------------------------------------------------
    |
    | Lo que literalmente se imprime junto al folio en el pie de cada reporte.
    |
    */
    'doc_version' => 'VER ' . $version,

    /*
    |--------------------------------------------------------------------------
    | Clave del sello digital (HMAC-SHA256)
    |--------------------------------------------------------------------------
    |
    | El sello de integridad de los documentos (App\Traits\HasDigitalSignatures)
    | pasó de SHA-256 SIN CLAVE a HMAC-SHA256 CON CLAVE. Sin clave, cualquiera que
    | conociera el payload canónico podía FABRICAR un sello válido (el hash era
    | público y reproducible). Con HMAC, sólo quien tiene la clave produce un sello
    | que el verificador acepte.
    |
    | Clave DEDICADA (no se reusa APP_KEY): la integridad del sello se puede rotar
    | sin invalidar sesiones/cookies, y rotar APP_KEY no tira todos los sellos. Se
    | genera con `php -r "echo 'base64:'.base64_encode(random_bytes(32));"` y va en
    | .env como CREWCARE_SEAL_KEY. Si falta, el trait cae a APP_KEY (siempre presente)
    | como red de seguridad — nunca vuelve a un hash sin clave.
    |
    | ⚠ Cutover LIMPIO: al activar HMAC, los sellos SHA viejos dejan de casar. Válido
    | porque cada deploy es migrate:fresh (prod nace sin sellos viejos) y el local se
    | re-siembra. NO rotar esta clave en una instancia con sellos vivos sin re-sellar.
    |
    */
    'seal' => [
        'key' => $sealKey,
    ],

    /*
    |--------------------------------------------------------------------------
    | Contratos · caminos de escape (Fase 2)
    |--------------------------------------------------------------------------
    |
    | `expire_days` — ventana de vigencia de un sobre en firma. Al ENVIAR se fija
    | `expires_at = sent_at + expire_days`. El barrido `contracts:expire-stale`
    | marca 'expired' los sobres cuyo plazo ya pasó (o, para sobres viejos sin
    | expires_at, cuyo sent_at rebasó la ventana). El barrido es MANUAL/programable
    | por el owner — no vence nada solo. Súbelo/bájalo según qué tan lentas sean
    | las producciones; NO es agresivo (un contrato vencido es un estado terminal).
    |
    */
    'contracts' => [
        'expire_days' => (int) env('CONTRACTS_EXPIRE_DAYS', 45),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sello de tiempo TSA (RFC 3161)
    |--------------------------------------------------------------------------
    |
    | Timbre EXTERNO sobre cada sello digital. Es lo único que sobrevive si se filtra
    | CREWCARE_SEAL_KEY (que no se puede rotar con sellos vivos): con la llave alguien podría
    | FABRICAR un sello, pero no un timbre fechado en el pasado por una TSA independiente.
    | Sólo viaja el HASH (imprint = SHA-256 del document_hash) → confidencialidad intacta.
    | Best-effort/async: el cron `tsa:stamp` lo hace aparte; el sellado NUNCA se bloquea.
    |
    | AUTORIDADES EN ORDEN: se intenta la primera; si no responde, la siguiente (respaldo), y se
    | REGISTRA en la fila del timbre cuál emitió (columna `authority`) — a 3 años hay que saber
    | contra qué certificado verificar. Principal DigiCert: su raíz (DigiCert Trusted Root G4) viene
    | PREINSTALADA en todo sistema operativo → un tercero verifica apuntando `-CAfile` al bundle del
    | sistema, SIN descargar ni archivar cert de la TSA. Respaldo freeTSA (⚠ la URL lleva `/tsr`; el
    | dominio a secas falla), cuya raíz NO está en los almacenes estándar → sus timbres exigen su
    | `cacert.pem`. El `.tsr` embebe su propia cadena de firma en ambos casos.
    |
    */
    'tsa' => [
        'enabled' => (bool) env('CREWCARE_TSA_ENABLED', true),
        'timeout' => (int) env('CREWCARE_TSA_TIMEOUT', 8),
        'authorities' => [
            [
                'name' => env('CREWCARE_TSA_PRIMARY_NAME', 'DigiCert'),
                'url'  => env('CREWCARE_TSA_PRIMARY_URL', 'http://timestamp.digicert.com'),
            ],
            [
                'name' => env('CREWCARE_TSA_BACKUP_NAME', 'freeTSA'),
                'url'  => env('CREWCARE_TSA_BACKUP_URL', 'https://freetsa.org/tsr'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Endurecimiento (cabeceras + CSP)
    |--------------------------------------------------------------------------
    |
    | `csp_report` — emite la CSP completa en modo REPORTE (no bloquea); las violaciones van a
    | /csp-report para MEDIR qué se rompería. `csp_enforce` — emite ADEMÁS una CSP que hace enforce de
    | `script-src 'self' 'nonce-…'` (sin unsafe-inline) + `img-src 'self' data: blob:` + `font-src 'self'
    | https://fonts.gstatic.com data:` (tras empaquetar, sólo quedan orígenes propios/permitidos; gstatic
    | se conserva porque Google Fonts sigue en uso) + `object-src 'none'` + `base-uri 'self'` (las dos que
    | hacen efectivo al nonce: cierran el bypass por <object>/<base>). SÓLO `style-src` sigue en reporte
    | (los `style=` en línea, mayormente de correos exentos de CSP, se deciden después). Dos cabeceras
    | conviven: la de enforce bloquea lo suyo, la Report-Only sigue midiendo el resto. `hsts` — HSTS en producción
    | sobre https (SecurityHeaders ya gatea el entorno). Todo sin fricción para el usuario.
    |
    */
    'security' => [
        'csp_report'  => (bool) env('CREWCARE_CSP_REPORT', true),
        'csp_enforce' => (bool) env('CREWCARE_CSP_ENFORCE', false),
        'hsts'        => (bool) env('CREWCARE_HSTS', true),
    ],

];

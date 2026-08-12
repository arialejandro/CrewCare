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

];

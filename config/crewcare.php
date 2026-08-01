<?php

// Se declara UNA vez y las claves de abajo la derivan: si el número se repitiera
// literalmente en dos claves, volveríamos a tener el problema que este archivo
// viene a resolver, sólo que con dos sitios en vez de ocho.
$version = '3.5';

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

];

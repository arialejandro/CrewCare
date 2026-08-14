<?php

namespace App\Support;

/**
 * CONTRACT BUILDER · incremento 1 — catálogo de FORMATOS (arquitecturas del Espec).
 *
 * El FORMATO es la FORMA del contrato: cómo abre (carátula tabla numerada / ficha etiqueta:valor /
 * declaraciones-primero) y cómo se ve la hoja. El usuario ELIGE un formato y el canvas carga su
 * ANDAMIAJE (esqueleto real, derivado del corpus de contratos firmados) para empezar a escribir.
 *
 * Increment 1 = los 3 formatos de UNA columna (A/C/F del Espec). El bilingüe 2-col (B/D) llega en el
 * incremento 2 (columna `bilingual` ya reservada).
 *
 * ⚖ FRONTERA LEGAL (contract-builder-legal-boundary): los andamiajes traen la ESTRUCTURA (carátula,
 * etiquetas, proemio, bloque de firmas con anclas reales) pero el CUERPO de cada cláusula es un
 * PLACEHOLDER — CrewCare NO redacta clausulado. El owner pega el suyo, revisado por su área legal.
 */
class ContractArchitectures
{
    public const DEFAULT = 'caratula_numbered';

    /** key => [label, desc, bilingual]. El ORDEN define el orden en el selector. */
    public static function all(): array
    {
        return [
            'caratula_numbered' => [
                'label'     => 'Carátula numerada + cláusulas (1 columna)',
                'desc'      => 'Tabla “Carátula” con apartados numerados y cláusulas ordinales. Ej.: Redrum, Cuadernos de Cine.',
                'bilingual' => false,
            ],
            'field_sheet' => [
                'label'     => 'Ficha de datos (etiqueta: valor) + cláusulas',
                'desc'      => 'Abre con una lista de campos “Etiqueta: valor”, rica en logística. Ej.: K&K Films.',
                'bilingual' => false,
            ],
            'declarations' => [
                'label'     => 'Declaraciones y cláusulas (sin carátula)',
                'desc'      => 'Formato notarial: proemio + Declaraciones I/II + cláusulas. Sin tabla de carátula.',
                'bilingual' => false,
            ],
        ];
    }

    public static function isValid(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::all());
    }

    public static function normalize(?string $key): string
    {
        return self::isValid($key) ? $key : self::DEFAULT;
    }

    public static function label(?string $key): string
    {
        return self::all()[self::normalize($key)]['label'];
    }

    /** Andamiajes por formato, para cargar en el canvas al elegir. key => HTML. */
    public static function starters(): array
    {
        return [
            'caratula_numbered' => self::starterCaratula(),
            'field_sheet'       => self::starterFieldSheet(),
            'declarations'      => self::starterDeclarations(),
        ];
    }

    public static function starter(?string $key): string
    {
        return self::starters()[self::normalize($key)];
    }

    /** CSS extra por formato, inyectado en la hoja del render (además del base de page()). */
    public static function pageCss(?string $key): string
    {
        switch (self::normalize($key)) {
            case 'caratula_numbered':
                return '.caratula td{vertical-align:top}.caratula td:first-child{width:38%;background:#f6f7f9}';
            case 'field_sheet':
                return '.ficha{line-height:1.9}';
            case 'declarations':
                return 'body{text-align:justify}h2{text-align:center}';
            default:
                return '';
        }
    }

    // ── Andamiajes (estructura real; el cuerpo de cláusula es placeholder) ──────────────────

    private static function firmasBlock(string $izq, string $der): string
    {
        return "<h2>Firmas</h2>\n<table style=\"width:100%\"><tr>\n"
            . "  <td style=\"text-align:center;padding:12px;vertical-align:bottom\">[[firma:contratado]]<div style=\"font-size:11px;color:#555\">{$izq}</div></td>\n"
            . "  <td style=\"text-align:center;padding:12px;vertical-align:bottom\">[[firma:dept_hod]]<div style=\"font-size:11px;color:#555\">{$der}</div></td>\n"
            . "</tr></table>";
    }

    private static function starterCaratula(): string
    {
        return "<h1>Contrato de prestación de servicios profesionales</h1>\n"
            . "<p style=\"text-align:center\">Celebrado, por una parte, <strong>{{empresa}}</strong> (la “Empresa”), "
            . "representada por {{representante_legal}}, con domicilio en {{domicilio_empresa}}; y por la otra, "
            . "<strong>{{payee_nombre}}</strong> (el “Prestador”).</p>\n"
            . "<h2 style=\"text-align:center\">Carátula</h2>\n"
            . "<table class=\"caratula\" border=\"1\" cellpadding=\"6\" style=\"border-collapse:collapse;width:100%\">\n"
            . "  <tr><td><strong>1. Datos del Prestador</strong></td><td>{{payee_nombre}} — RFC {{payee_rfc}}</td></tr>\n"
            . "  <tr><td><strong>2. Descripción de los Servicios</strong></td><td>{{puesto}} — {{actividad}}</td></tr>\n"
            . "  <tr><td><strong>3. Crédito en pantalla</strong></td><td>{{credito}}</td></tr>\n"
            . "  <tr><td><strong>4. Contraprestación</strong></td><td>{{honorarios}} {{moneda}}, más IVA y menos las retenciones aplicables</td></tr>\n"
            . "  <tr><td><strong>5. Vigencia</strong></td><td>Del {{vigencia_inicio}} al {{vigencia_fin}}</td></tr>\n"
            . "  <tr><td><strong>6. Beneficiario en caso de fallecimiento</strong></td><td>&nbsp;</td></tr>\n"
            . "</table>\n"
            . "<h2>Declaraciones</h2>\n<p>Las partes se reconocen mutuamente la capacidad para suscribir el presente contrato.</p>\n"
            . "<h2>Cláusulas</h2>\n"
            . "<p class=\"cc-clause\"><strong>PRIMERA. Objeto.</strong> [Redacta aquí el objeto — remite al Apartado 2 de la Carátula.]</p>\n"
            . "<p class=\"cc-clause\"><strong>SEGUNDA. Contraprestación.</strong> [Redacta aquí — remite a los Apartados 4 y 5.]</p>\n"
            . "<p><em>[Agrega aquí el resto del clausulado revisado por tu área legal.]</em></p>\n"
            . self::firmasBlock('El Prestador', 'Por la Empresa');
    }

    private static function starterFieldSheet(): string
    {
        return "<h1 style=\"text-align:center\">{{empresa}}</h1>\n"
            . "<p style=\"text-align:center\">Contrato de prestación de servicios independientes y actividades empresariales</p>\n"
            . "<p class=\"ficha\">\n"
            . "<strong>Contratante (la “Empresa”):</strong> {{empresa}}<br>\n"
            . "<strong>Domicilio de la Empresa:</strong> {{domicilio_empresa}}<br>\n"
            . "<strong>Representante legal:</strong> {{representante_legal}}<br>\n"
            . "<strong>Actividad objeto del contrato (los “Servicios”):</strong> {{actividad}}<br>\n"
            . "<strong>Puesto:</strong> {{puesto}}<br>\n"
            . "<strong>Vigencia:</strong> del {{vigencia_inicio}} al {{vigencia_fin}}<br>\n"
            . "<strong>Nombre del Contratista:</strong> {{payee_nombre}}<br>\n"
            . "<strong>RFC o CURP:</strong> {{payee_rfc}}<br>\n"
            . "<strong>Honorarios:</strong> {{honorarios}} {{moneda}}, más IVA y menos las retenciones aplicables<br>\n"
            . "<strong>Crédito en pantalla:</strong> {{credito}}\n"
            . "</p>\n"
            . "<p>En virtud de los tiempos limitados de la producción, el Contratista prestará sus servicios "
            . "exclusivamente a este proyecto durante la vigencia.</p>\n"
            . "<h2>Cláusulas</h2>\n"
            . "<p class=\"cc-clause\"><strong>PRIMERA. Objeto.</strong> [Redacta aquí el objeto del contrato.]</p>\n"
            . "<p><em>[Agrega aquí el resto del clausulado revisado por tu área legal.]</em></p>\n"
            . self::firmasBlock('El Contratista', 'Por la Empresa');
    }

    private static function starterDeclarations(): string
    {
        return "<p><strong>CONTRATO DE PRESTACIÓN DE SERVICIOS PROFESIONALES</strong> (en lo sucesivo, el “Contrato”) "
            . "que celebran, por una parte, <strong>{{empresa}}</strong>, representada por {{representante_legal}} "
            . "(la “Productora”), y por la otra, <strong>{{payee_nombre}}</strong>, por su propio derecho "
            . "(el “Prestador”), al tenor de las siguientes declaraciones y cláusulas:</p>\n"
            . "<h2>Declaraciones</h2>\n"
            . "<p><strong>I. Declara la Productora, por conducto de su apoderado, que:</strong></p>\n"
            . "<p>a) Es una sociedad debidamente constituida conforme a la legislación mexicana.<br>\n"
            . "b) Su representante cuenta con las facultades necesarias, no revocadas ni limitadas.<br>\n"
            . "c) Señala como domicilio {{domicilio_empresa}}.</p>\n"
            . "<p><strong>II. Declara el Prestador, por su propio derecho, que:</strong></p>\n"
            . "<p>a) Es persona física con capacidad legal para obligarse.<br>\n"
            . "b) Cuenta con la experiencia y los recursos propios para prestar los Servicios como {{puesto}}.<br>\n"
            . "c) Su RFC es {{payee_rfc}}.</p>\n"
            . "<h2>Cláusulas</h2>\n"
            . "<p class=\"cc-clause\"><strong>PRIMERA. Objeto.</strong> [Redacta aquí el objeto — los Servicios ({{actividad}}), "
            . "del {{vigencia_inicio}} al {{vigencia_fin}}, por {{honorarios}} {{moneda}}.]</p>\n"
            . "<p><em>[Agrega aquí el resto del clausulado revisado por tu área legal.]</em></p>\n"
            . self::firmasBlock('El Prestador', 'Por la Productora');
    }
}

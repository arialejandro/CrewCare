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
                'desc'      => 'Tabla “Carátula” con apartados numerados y cláusulas ordinales.',
                'bilingual' => false,
            ],
            'field_sheet' => [
                'label'     => 'Ficha de datos (etiqueta: valor) + cláusulas',
                'desc'      => 'Abre con una lista de campos “Etiqueta: valor”, rica en logística de producción.',
                'bilingual' => false,
            ],
            'declarations' => [
                'label'     => 'Declaraciones y cláusulas (sin carátula)',
                'desc'      => 'Formato notarial: proemio + Declaraciones I/II + cláusulas. Sin tabla de carátula.',
                'bilingual' => false,
            ],
            'bilingual_crew' => [
                'label'     => 'Bilingüe 2 columnas · Crew (Front Page | Carátula)',
                'desc'      => 'Portada a doble columna EN|ES tipo “Service Agreement for Crew Members”, con apartados numerados.',
                'bilingual' => true,
            ],
            'bilingual_vendor' => [
                'label'     => 'Bilingüe 2 columnas · Proveedor (Goods & Services Supply Agreement)',
                'desc'      => 'Doble columna EN|ES para proveedores/vendors: suministro de bienes y servicios + cláusulas.',
                'bilingual' => true,
            ],
            'bilingual_main_terms' => [
                'label'     => 'Bilingüe 2 columnas · Main Terms + Orden de Compra',
                'desc'      => 'Doble columna EN|ES en prosa (Main Terms); la tarifa se externaliza a una Orden de Compra/Anexo.',
                'bilingual' => true,
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
            'caratula_numbered'    => self::starterCaratula(),
            'field_sheet'          => self::starterFieldSheet(),
            'declarations'         => self::starterDeclarations(),
            'bilingual_crew'       => self::starterBilingualCrew(),
            'bilingual_vendor'     => self::starterBilingualVendor(),
            'bilingual_main_terms' => self::starterBilingualMainTerms(),
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
            case 'bilingual_crew':
            case 'bilingual_vendor':
            case 'bilingual_main_terms':
                // Rejilla completa (cada apartado en su recuadro + divisor central EN|ES), como el corpus.
                return '.bili{width:100%}.bili td{border:1px solid #333;vertical-align:top;width:50%;padding:5px 7px}';
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

    // ── Bilingües 2 columnas (EN|ES) ────────────────────────────────────────────────────────
    // El andamiaje bilingüe es una TABLA `.bili` de dos columnas (izq inglés, der español) que el
    // redactor rellena. Los datos son tokens {{...}} (mismo valor en ambas) y [CONFIRMAR] cuando el
    // dato no existe como token (igual que las plantillas reales). Cero clausulado de fábrica.

    private static function firmasBili(string $enL, string $esL, string $enR, string $esR): string
    {
        return "<h2>Signatures / Firmas</h2>\n<table style=\"width:100%\"><tr>\n"
            . "  <td style=\"text-align:center;padding:12px;vertical-align:bottom\">[[firma:contratado]]<div style=\"font-size:11px;color:#555\">{$enL} / {$esL}</div></td>\n"
            . "  <td style=\"text-align:center;padding:12px;vertical-align:bottom\">[[firma:dept_hod]]<div style=\"font-size:11px;color:#555\">{$enR} / {$esR}</div></td>\n"
            . "</tr></table>";
    }

    private static function starterBilingualCrew(): string
    {
        return "<table class=\"bili\" border=\"1\" cellpadding=\"6\" style=\"border-collapse:collapse;width:100%\">\n"
            . "<tr>\n"
            . "  <td style=\"text-align:center\"><strong>FRONT PAGE</strong><br>SERVICE AGREEMENT FOR CREW MEMBERS<br><br><strong>{{empresa}}</strong> (the “Producer”)<br>represented herein by {{representante_legal}}<br>with address at {{domicilio_empresa}}<br><br>and by<br><br><strong>{{payee_nombre}}</strong> (the “Contractor”)</td>\n"
            . "  <td style=\"text-align:center\"><strong>CARÁTULA</strong><br>CONTRATO DE PRESTACIÓN DE SERVICIOS<br><br><strong>{{empresa}}</strong> (el “Productor”)<br>representada por {{representante_legal}}<br>con domicilio en {{domicilio_empresa}}<br><br>y por otra parte<br><br><strong>{{payee_nombre}}</strong> (el “Contratista”)</td>\n"
            . "</tr>\n"
            . "<tr><td><strong>2. PROGRAM TITLE:</strong> [CONFIRMAR]</td><td><strong>2. TÍTULO DEL PROGRAMA:</strong> [CONFIRMAR]</td></tr>\n"
            . "<tr><td><strong>3. CONTRACTOR’S INFORMATION:</strong><br>a) Address: [CONFIRMAR]<br>b) Legal representative: [CONFIRMAR]<br>c) Phone: [CONFIRMAR]<br>d) Cellphone: [CONFIRMAR]<br>e) Emergency contact &amp; phone: [CONFIRMAR]<br>f) E-mail: [CONFIRMAR]<br>g) Loanout company (if any): [CONFIRMAR]<br>h) Beneficiary (if any): [CONFIRMAR]<br>i) Tax ID (RFC): {{payee_rfc}}</td>"
            . "<td><strong>3. INFORMACIÓN DEL CONTRATISTA:</strong><br>a) Domicilio: [CONFIRMAR]<br>b) Representante legal: [CONFIRMAR]<br>c) Teléfono: [CONFIRMAR]<br>d) Celular: [CONFIRMAR]<br>e) Contacto de emergencia y teléfono: [CONFIRMAR]<br>f) Correo: [CONFIRMAR]<br>g) Empresa que proporciona (en su caso): [CONFIRMAR]<br>h) Beneficiario (en su caso): [CONFIRMAR]<br>i) RFC: {{payee_rfc}}</td></tr>\n"
            . "<tr><td><strong>4. REMUNERATION:</strong> {{honorarios}} {{moneda}} weekly, plus VAT minus the withholdings required by law.</td><td><strong>4. REMUNERACIÓN:</strong> {{honorarios}} {{moneda}} semanales, más IVA y menos las retenciones fiscales correspondientes.</td></tr>\n"
            . "<tr><td><strong>5. SERVICE TO BE SUPPLIED:</strong> {{puesto}} — {{actividad}}</td><td><strong>5. DESCRIPCIÓN DE LOS SERVICIOS:</strong> {{puesto}} — {{actividad}}</td></tr>\n"
            . "<tr><td><strong>6. TERMS OF AGREEMENT:</strong><br>a) Date of agreement: [CONFIRMAR]<br>b) Start date: {{vigencia_inicio}}<br>c) Finish date: {{vigencia_fin}}</td><td><strong>6. TÉRMINOS DE CONTRATO:</strong><br>a) Fecha de contrato: [CONFIRMAR]<br>b) Fecha de comienzo: {{vigencia_inicio}}<br>c) Fecha de terminación: {{vigencia_fin}}</td></tr>\n"
            . "<tr><td><strong>7. BOX / CAR RENTAL:</strong> ( ) Yes ( ) No — Amount: [CONFIRMAR]</td><td><strong>7. RENTA DE CAJA / AUTO:</strong> ( ) Sí ( ) No — Cantidad: [CONFIRMAR]</td></tr>\n"
            . "<tr><td><strong>8. ADDITIONAL PROVISIONS:</strong> [Draft here — reviewed by your legal team.]</td><td><strong>8. PROVISIONES ADICIONALES:</strong> [Redacta aquí — revisado por tu área legal.]</td></tr>\n"
            . "</table>\n"
            . "<h2>Clauses / Cláusulas</h2>\n"
            . "<p class=\"cc-clause\"><strong>FIRST / PRIMERA.</strong> [Draft the clause body / Redacta el clausulado — reviewed by your legal team / revisado por tu área legal.]</p>\n"
            . "<p><em>[Add the rest of the reviewed clauses. / Agrega aquí el resto del clausulado.]</em></p>\n"
            . self::firmasBili('The Contractor', 'El Contratista', 'For the Producer', 'Por el Productor');
    }

    private static function starterBilingualVendor(): string
    {
        return "<table class=\"bili\" border=\"1\" cellpadding=\"6\" style=\"border-collapse:collapse;width:100%\">\n"
            . "<tr>\n"
            . "  <td style=\"text-align:center\"><strong>GOODS AND SERVICES SUPPLY AGREEMENT</strong><br>entered into by<br><br><strong>{{empresa}}</strong> (the “Producer”)<br>represented herein by {{representante_legal}}<br>with address at {{domicilio_empresa}}<br><br>and by<br><br><strong>{{payee_nombre}}</strong> (the “Vendor”)</td>\n"
            . "  <td style=\"text-align:center\"><strong>CONTRATO DE SUMINISTRO DE BIENES Y SERVICIOS</strong><br>que celebran, por una parte<br><br><strong>{{empresa}}</strong> (el “Productor”)<br>representada por {{representante_legal}}<br>con domicilio en {{domicilio_empresa}}<br><br>y por otra parte<br><br><strong>{{payee_nombre}}</strong> (el “Proveedor”)</td>\n"
            . "</tr>\n"
            . "<tr><td><strong>1. Vendor’s address and email:</strong> [CONFIRMAR]</td><td><strong>1. Domicilio y correo del Proveedor:</strong> [CONFIRMAR]</td></tr>\n"
            . "<tr><td><strong>2. Tax Identity Number:</strong> {{payee_rfc}}</td><td><strong>2. Registro Federal de Contribuyentes:</strong> {{payee_rfc}}</td></tr>\n"
            . "<tr><td><strong>3. Goods and services to be supplied:</strong> {{actividad}}</td><td><strong>3. Descripción de los bienes y/o servicios contratados:</strong> {{actividad}}</td></tr>\n"
            . "<tr><td><strong>4. Agreed remuneration:</strong> {{honorarios}} {{moneda}} plus VAT, less the corresponding deductions.</td><td><strong>4. Contraprestación pactada:</strong> {{honorarios}} {{moneda}} más IVA, menos las deducciones correspondientes.</td></tr>\n"
            . "<tr><td><strong>5. Date, place and terms of payment:</strong> [CONFIRMAR]</td><td><strong>5. Fecha, lugar y forma de pago:</strong> [CONFIRMAR]</td></tr>\n"
            . "<tr><td><strong>6. Date and place of delivery:</strong> [CONFIRMAR]</td><td><strong>6. Fecha y lugar de entrega:</strong> [CONFIRMAR]</td></tr>\n"
            . "<tr><td><strong>7. Duration:</strong> From {{vigencia_inicio}} until {{vigencia_fin}}</td><td><strong>7. Duración:</strong> Del {{vigencia_inicio}} al {{vigencia_fin}}</td></tr>\n"
            . "</table>\n"
            . "<h2>Clauses / Cláusulas</h2>\n"
            . "<table class=\"bili\" style=\"width:100%\">\n"
            . "<tr><td><strong>First. Vendor’s Representations.</strong> [Draft — reviewed by your legal team.]</td><td><strong>Primera. Declaraciones del Proveedor.</strong> [Redacta — revisado por tu área legal.]</td></tr>\n"
            . "<tr><td><strong>Second. Subject Matter.</strong> [Draft the clause body.]</td><td><strong>Segunda. Objeto del Contrato.</strong> [Redacta el clausulado.]</td></tr>\n"
            . "<tr><td><em>[Add the rest of the reviewed clauses.]</em></td><td><em>[Agrega aquí el resto del clausulado.]</em></td></tr>\n"
            . "</table>\n"
            . self::firmasBili('The Vendor', 'El Proveedor', 'For the Producer', 'Por el Productor');
    }

    private static function starterBilingualMainTerms(): string
    {
        return "<table class=\"bili\" style=\"width:100%\">\n"
            . "<tr><td style=\"text-align:center\"><strong>CREW AND VENDORS AGREEMENT</strong><br>MAIN TERMS</td><td style=\"text-align:center\"><strong>CONTRATO ENTRE PERSONAL DE PRODUCCIÓN (CREW) Y PROVEEDORES</strong><br>TÉRMINOS PRINCIPALES</td></tr>\n"
            . "<tr><td><strong>DATED:</strong> [CONFIRMAR]</td><td><strong>FECHA:</strong> [CONFIRMAR]</td></tr>\n"
            . "<tr><td><strong>PARTIES:</strong><br>(1) <strong>{{empresa}}</strong>, with registered address at {{domicilio_empresa}} (the “Company”); and<br>(2) <strong>{{payee_nombre}}</strong> (the “Individual”).</td><td><strong>PARTES:</strong><br>(1) <strong>{{empresa}}</strong>, con domicilio en {{domicilio_empresa}} (la “Empresa”); y<br>(2) <strong>{{payee_nombre}}</strong> (el “Individuo”).</td></tr>\n"
            . "<tr><td><strong>1. PRODUCTION:</strong> [CONFIRMAR] (the “Project”).</td><td><strong>1. PRODUCCIÓN:</strong> [CONFIRMAR] (el “Proyecto”).</td></tr>\n"
            . "<tr><td><strong>2. SERVICES:</strong> The Individual’s capacity as {{puesto}} — {{actividad}}, as set out in the Purchase Order.</td><td><strong>2. SERVICIOS:</strong> La capacidad del Individuo como {{puesto}} — {{actividad}}, según la Orden de Compra.</td></tr>\n"
            . "<tr><td><strong>3. PURCHASE ORDER:</strong> Numbered purchase order(s) detailing the consideration and pre-approved expenses (Schedule 2). The remuneration is set out there, not in these Main Terms.</td><td><strong>3. ORDEN DE COMPRA:</strong> Orden(es) de compra numerada(s) con el importe de la contraprestación y los gastos preaprobados (Anexo 2). La remuneración se establece ahí, no en estos Términos Principales.</td></tr>\n"
            . "</table>\n"
            . "<h2>Clauses / Cláusulas</h2>\n"
            . "<p class=\"cc-clause\"><strong>1.</strong> [Draft the clause body here / Redacta el clausulado — reviewed by your legal team / revisado por tu área legal.]</p>\n"
            . "<p><em>[Attach Schedule 2 / Purchase Order. — Adjunta el Anexo 2 / Orden de Compra.]</em></p>\n"
            . self::firmasBili('The Individual', 'El Individuo', 'For the Company', 'Por la Empresa');
    }
}

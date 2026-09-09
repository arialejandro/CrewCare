<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

/**
 * Catálogo MAESTRO de tipos de documento (BASE ÚNICA DE QUIEN COBRA). Da la CLAVE que
 * reemplaza el texto libre. Idempotente (updateOrCreate por `code`). El PAQUETE por
 * producción (qué claves aplican, toggles antes/después, fecha de corte 32-D) es Paso 2:
 * aquí solo vive el universo de tipos con sus propiedades INTRÍNSECAS (forma de vigencia,
 * si exige positiva, familia billing/operate, naturaleza).
 */
class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $B = DocumentType::FAMILY_BILLING;
        $O = DocumentType::FAMILY_OPERATE;
        $ID = DocumentType::SCOPE_IDENTITY;
        $CT = DocumentType::SCOPE_CONTRACT;
        $PE = DocumentType::SCOPE_PERSON;

        // [code, name, family, scope, legal_nature, validity_shape, validity_days,
        //  requires_positive, is_repse, repse_phase, sort_order]
        $rows = [
            // ── BILLING · IDENTIDAD (paquete fiscal de la persona) ──
            ['COTIZACION',          'Cotización',                              $B, $ID, 'ambas',  null,                        null, 0, 0, null, 10],
            ['INE',                 'Identificación oficial (INE)',            $B, $ID, 'fisica', DocumentType::V_PERMANENT,   null, 0, 0, null, 20],
            ['CSF',                 'Constancia de Situación Fiscal (CSF)',    $B, $ID, 'ambas',  DocumentType::V_MONTH,       null, 0, 0, null, 30],
            ['OPINION_32D',         'Opinión de cumplimiento (32-D)',          $B, $ID, 'ambas',  DocumentType::V_MONTH,       null, 1, 0, null, 40],
            ['COMP_DOMICILIO',      'Comprobante de domicilio',                $B, $ID, 'ambas',  DocumentType::V_DAYS,        90,   0, 0, null, 50],
            ['CARATULA_BANCO',      'Carátula de estado de cuenta',            $B, $ID, 'ambas',  DocumentType::V_DAYS,        90,   0, 0, null, 60],
            ['ACTA_CONSTITUTIVA',   'Acta constitutiva',                       $B, $ID, 'moral',  DocumentType::V_PERMANENT,   null, 0, 0, null, 70],
            ['PODER_REPRESENTANTE', 'Poder del representante',                 $B, $ID, 'moral',  DocumentType::V_PERMANENT,   null, 0, 0, null, 80],
            ['INE_REPRESENTANTE',   'INE del representante',                   $B, $ID, 'moral',  DocumentType::V_PERMANENT,   null, 0, 0, null, 90],
            ['COI',                 'Certificado de seguro (COI)',             $B, $ID, 'ambas',  DocumentType::V_DAYS,        365,  0, 0, null, 100],

            // ── BILLING · CONTRATO (no REPSE) ──
            ['FACT_RENTA',          'Factura de renta',                        $B, $CT, 'ambas',  null,                        null, 0, 0, null, 110],
            ['FACT_PROPIEDAD',      'Factura que acredita propiedad del equipo', $B, $CT, 'ambas', null,                       null, 0, 0, null, 120],

            // ── BILLING · CONTRATO · REPSE ANTES ──
            ['REPSE_STPS',            'Registro validado ante STPS',                        $B, $CT, 'ambas', null,                  null, 0, 1, DocumentType::PHASE_BEFORE, 130],
            ['REPSE_CSF_ISR',         'CSF con alta de retenciones de ISR por sueldos',     $B, $CT, 'ambas', DocumentType::V_MONTH, null, 0, 1, DocumentType::PHASE_BEFORE, 140],
            ['REPSE_CONTRATO_LABORAL','Contrato laboral con el trabajador a disposición',   $B, $CT, 'ambas', null,                  null, 0, 1, DocumentType::PHASE_BEFORE, 150],
            ['REPSE_ACTA_ACTIVIDADES','Acta constitutiva (actividades económicas)',         $B, $CT, 'moral', DocumentType::V_PERMANENT, null, 0, 1, DocumentType::PHASE_BEFORE, 160],
            ['REPSE_CONTRATO_PS',     'Contrato de prestación de servicios',                $B, $CT, 'ambas', null,                  null, 0, 1, DocumentType::PHASE_BEFORE, 170],
            ['REPSE_LISTADO_PERSONAL','Listado de personal (nombre, CURP y NSS)',           $B, $CT, 'ambas', null,                  null, 0, 1, DocumentType::PHASE_BEFORE, 180],

            // ── BILLING · CONTRATO · REPSE DESPUÉS DEL PAGO ──
            ['REPSE_CFDI_NOMINA',   'CFDI de nómina',                          $B, $CT, 'ambas', null,                    null, 0, 1, DocumentType::PHASE_AFTER, 190],
            ['REPSE_ISN',           'Impuesto sobre nóminas estatal',          $B, $CT, 'ambas', null,                    null, 0, 1, DocumentType::PHASE_AFTER, 200],
            ['REPSE_DECL_SUELDOS',  'Declaración por sueldos y salarios',      $B, $CT, 'ambas', null,                    null, 0, 1, DocumentType::PHASE_AFTER, 210],
            ['REPSE_DECL_IVA',      'Declaración de IVA',                      $B, $CT, 'ambas', null,                    null, 0, 1, DocumentType::PHASE_AFTER, 220],
            ['REPSE_SUA',           'SUA IMSS/INFONAVIT',                      $B, $CT, 'ambas', null,                    null, 0, 1, DocumentType::PHASE_AFTER, 230],
            ['REPSE_ICSOE',         'ICSOE trimestral',                        $B, $CT, 'ambas', DocumentType::V_QUARTER, null, 0, 1, DocumentType::PHASE_AFTER, 240],
            ['REPSE_SISUB',         'SISUB',                                   $B, $CT, 'ambas', null,                    null, 0, 1, DocumentType::PHASE_AFTER, 250],

            // ── OPERATE (ambulancias — targets de la migración del Paso 5) ──
            ['AMB_AVISO_FUNC',      'Aviso de funcionamiento',                 $O, $ID, 'moral',  null, null, 0, 0, null, 300],
            ['AMB_DICTAMEN',        'Dictamen',                                $O, $ID, 'moral',  null, null, 0, 0, null, 310],
            ['AMB_HOLOGRAMA',       'Holograma',                               $O, $ID, 'moral',  null, null, 0, 0, null, 320],
            ['AMB_POLIZA',          'Póliza de seguro',                        $O, $ID, 'moral',  null, null, 0, 0, null, 330],
            ['AMB_CONOCER',         'Certificación CONOCER',                   $O, $PE, 'fisica', null, null, 0, 0, null, 340],
            ['AMB_TAMP',            'Formación TAMP',                          $O, $PE, 'fisica', null, null, 0, 0, null, 350],
            ['AMB_CEDULA',          'Cédula profesional',                      $O, $PE, 'fisica', null, null, 0, 0, null, 360],

            // ── OPERATE (Transportación · Bloque 1 — documentos del vehículo, §4) ──
            // Se captura FECHA DE VENCIMIENTO explícita (validity_shape null → no auto-deriva; la
            // vigencia sale del valid_until capturado). NO duplican los docs FISCALES del proveedor
            // (esos viven en el payee). La licencia es del conductor (scope persona), pero se valida
            // en el contexto de operatividad del vehículo (holder = Vehicle).
            ['VEH_TARJETA',       'Tarjeta de circulación',      $O, $ID, 'ambas',  null, null, 0, 0, null, 400],
            ['VEH_POLIZA',        'Póliza de seguro (vehículo)', $O, $ID, 'ambas',  null, null, 0, 0, null, 410],
            ['VEH_VERIFICACION',  'Verificación vehicular',      $O, $ID, 'ambas',  null, null, 0, 0, null, 420],
            ['VEH_LICENCIA',      'Licencia del conductor',      $O, $PE, 'fisica', null, null, 0, 0, null, 430],
        ];

        foreach ($rows as $r) {
            DocumentType::updateOrCreate(
                ['code' => $r[0]],
                [
                    'name'                     => $r[1],
                    'family'                   => $r[2],
                    'scope'                    => $r[3],
                    'legal_nature'             => $r[4],
                    'validity_shape'           => $r[5],
                    'validity_days'            => $r[6],
                    'requires_positive_status' => $r[7],
                    'is_repse'                 => $r[8],
                    'repse_phase'              => $r[9],
                    'sort_order'               => $r[10],
                    'is_active'                => 1,
                ]
            );
        }

        // XML DE LA FACTURA (CFDI): marca los tipos que SON factura → el intake acepta/parsea su XML.
        // Los CFDI reconocidos hoy (renta, propiedad del equipo, nómina REPSE). El owner puede marcar
        // otros aquí si aparece una factura de honorarios como tipo aparte.
        if (\Illuminate\Support\Facades\Schema::hasColumn('document_types', 'expects_cfdi_xml')) {
            DocumentType::whereIn('code', ['FACT_RENTA', 'FACT_PROPIEDAD', 'REPSE_CFDI_NOMINA'])
                ->update(['expects_cfdi_xml' => 1]);
        }

        // Sinónimos de búsqueda (PASO 2): "Opinión SAT" es como se le llama a la 32-D en la
        // práctica. Solo se fija si la columna existe (delta 2026-08-13-payee-packages aplicado).
        if (\Illuminate\Support\Facades\Schema::hasColumn('document_types', 'aliases')) {
            DocumentType::where('code', 'OPINION_32D')
                ->update(['aliases' => 'Opinión SAT, Opinión de cumplimiento']);
        }

        // Delta de EXTRANJERO: paquete alterno por nacionalidad. Docs mexicanos se marcan
        // 'mexicana'; se agregan pasaporte/visa/residencia fiscal 'extranjera'. El resto queda
        // NULL = ambas. Así un extranjero no queda en `missing` por INE/CSF/32-D que no le aplican.
        if (\Illuminate\Support\Facades\Schema::hasColumn('document_types', 'nationality')) {
            DocumentType::whereIn('code', ['INE', 'CSF', 'OPINION_32D'])->update(['nationality' => 'mexicana']);

            $foreign = [
                ['DOC_PASAPORTE',         'Pasaporte',                        DocumentType::V_PERMANENT, null, 25],
                ['DOC_MIGRATORIO',        'Visa o documento migratorio',      DocumentType::V_PERMANENT, null, 26],
                ['DOC_RESIDENCIA_FISCAL', 'Comprobante de residencia fiscal', DocumentType::V_DAYS,      90,   35],
            ];
            foreach ($foreign as $f) {
                DocumentType::updateOrCreate(['code' => $f[0]], [
                    'name' => $f[1], 'family' => $B, 'scope' => $ID, 'legal_nature' => 'fisica',
                    'nationality' => 'extranjera', 'validity_shape' => $f[2], 'validity_days' => $f[3],
                    'requires_positive_status' => 0, 'is_repse' => 0, 'repse_phase' => null,
                    'sort_order' => $f[4], 'is_active' => 1,
                ]);
            }
        }
    }
}

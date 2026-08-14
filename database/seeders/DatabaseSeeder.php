<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Cimientos RBAC (idempotentes). El mapeo de usuarios va al final porque depende de que
        // la producción exista; BackfillUserPositions corre después del mapeo para enriquecer
        // department_id/position_id del pivote a partir de los títulos planos del legacy.
        //
        // ⚠ AQUÍ SÓLO VA LO QUE DEBE LLEGAR A UNA INSTANCIA DE CLIENTE.
        // `db:seed` a secas es lo primero que se corre al levantar una instancia, así que esta
        // lista es, de hecho, la definición de "lo que el producto trae de fábrica".
        //
        // (2026-07-24) Se sacó `TestAccountsSeeder`: creaba cuatro cuentas con una contraseña
        // conocida y pública —una con rol `hod`, otra con rol `medic`, que abre el expediente
        // clínico— y estaba en esta lista, o sea que se sembraban solas. Ahora se invoca a mano
        // y además aborta fuera del entorno local:
        //     php artisan db:seed --class=TestAccountsSeeder
        //
        // Por la misma razón NUNCA debe encadenarse aquí `DemoProductionSeeder` (el corpus de
        // demostración). Se invoca explícito y también tiene candado de entorno.
        // (2026-08-11) Chain de FABRICA completo: un `migrate && db:seed` en base vacia
        // deja una app USABLE (login + catalogos + RBAC completo). Se sacaron del chain
        // MapExistingUsersSeeder y BackfillUserPositionsSeeder: son helpers LEGACY que
        // migran una tabla `users` preexistente (no-op en fresh) y hacen firstOrFail sobre
        // el nombre de la produccion — se invocan a mano solo si se migra un install viejo.
        $this->call([
            // ── RBAC: roles + TODOS los permisos (base + por-modulo) + matriz ──
            RolesAndPermissionsSeeder::class,          // 8 roles + 53 permisos base + grants
            NormasEventosPermissionsSeeder::class,     // standards.* / hazardevents.*
            MedicCredentialPermissionsSeeder::class,   // medic.credential.manage
            ToolInspectionPermissionsSeeder::class,    // tools.inspect
            PermitIssuancePermissionsSeeder::class,    // permits.issue
            EpiPermissionsSeeder::class,               // epi.view
            MedevacPermissionsSeeder::class,           // medevac.issue
            RiskMapPermissionsSeeder::class,           // riskmap.issue
            PaePermissionsSeeder::class,               // pae.issue
            AmbulancePermissionsSeeder::class,         // ambulance.manage + ambulance.view (visibilidad producción/safety)
            PayeeAccessPermissionsSeeder::class,       // payees.view (quien cobra · Paso 4) — DESPUÉS del barrido %.view del auditor
            PeriodPermissionsSeeder::class,            // periods.view/manage (ventana de recepción) — mismo motivo, después del barrido
            ContractAuthorPermissionsSeeder::class,    // contracts.author (Contract Builder → line-producer)
            // (SdsPermissionsSeeder NO: sds.* ya viene en el base)
            // (MedicRolePermissionsSeeder NO: migra usuarios; el grant a medic vive en el base)

            // ── Estructura organizacional + produccion + primer super-admin ──
            OrgCatalogSeeder::class,                   // departments/positions globales (production_id NULL)
            ProductionDemoSeeder::class,               // la fila de produccion de la instancia (nombre por env)
            InstallAdminSeeder::class,                 // 1er super-admin idempotente (creds por env)

            // ── Normativa + eventos (ORDEN ESTRICTO por dependencia FK/logica) ──
            SafetyCatalogSeeder::class,                // normas — PRIMERO (todos resuelven codigos contra esta)
            HazardEventSeeder::class,                  // eventos base (necesita Safety)
            EnrichedCatalogSeeder::class,              // +eventos/+normas -> 207/83 (necesita Safety+Hazard)
            HazardEventPpeSeeder::class,               // required_ppe (necesita los 207 poblados)

            // ── SFX / consumibles (ORDEN ESTRICTO) ──
            ConsumableSeeder::class,                   // fichas legacy
            SpfxCatalogSeeder::class,                  // consumibles + effect types + links
            EffectStandardBridgeSeeder::class,         // puente effect<->standard (necesita Spfx + Safety+Enriched)

            // ── Herramienta / permisos de trabajo (ORDEN ESTRICTO) ──
            ToolPermitCatalogSeeder::class,            // tools + permits (necesita safety_standards)
            ToolInspectionRegimeSeeder::class,         // inspection_regime (necesita tools poblado)

            // ── Catalogos independientes ──
            MedicationCatalogSeeder::class,            // medicamentos
            IndicatorTermSeeder::class,                // terminos indicadores
            AmbulanceCatalogSeeder::class,             // tipos + puntos de ambulancia
            DocumentTypeSeeder::class,                 // catalogo de tipos de documento (quien cobra)
            DocumentRequirementSeeder::class,          // paquete + settings de la produccion (Paso 2)
        ]);
    }
}

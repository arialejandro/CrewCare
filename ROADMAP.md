# ROADMAP.md — CrewCare → Plataforma de producción

Plan de evolución del producto. **Documento de visión y estrategia, no de implementación.**
Sirve para entender el panorama y decidir el orden.

> **⚠ Estado real (2026-08-06):** ESTE documento es la VISIÓN; hace tiempo dejó de ser
> cierto que "no se ha tocado código". La **capa de Health & Safety** (el diferenciador)
> está fuertemente CONSTRUIDA: catálogo único de eventos+normas, los 5 reportes v2 sobre
> chrome compartido, identidad médica + cédula, SDS/SPFX, herramientas/permisos +
> inspección, emisión de permisos, vigilancia epidemiológica, consultas médicas, MEDEVAC,
> scouting geo + Amazon MGM RA, Wrap report, y el **Mapeo de Riesgos y Recursos** (editor +
> documento sellado + biblioteca de señalética). El registro cronológico de todo esto está
> en **[PROGRESS.md](PROGRESS.md)** (deltas #40-#51) y el detalle en las notas de memoria.
> Las FASES de abajo siguen siendo la guía estratégica; no reflejan aún todo lo implementado.

> **🔺 MILESTONE ESTRATÉGICO EN CURSO (2026-08-11): PUESTA A PUNTO DEL STACK.** ✅ HECHAS en rama `upgrade/laravel-13` (`clean-main` intacta): **Fase 0** (rollback probado), **Fase 1** (migraciones limpias — `migrate` levanta el esquema 76/76 idéntico), **Fase 1b** (seeders de fábrica — `db:seed` deja app usable), **Fase 2** (suite de QA COMPLETA: **14 verticales, 475 tests verde; los 7 bugs cerrados, 0 abiertos**). ⬜ FALTA: **upgrade PHP 8.3 + Laravel 13 (Fases 3–4)** + cierre HMAC (5). Ver [[qa-suite-and-findings]].
> Con el H&S ya construido, el siguiente foco es el **cimiento**: (1) cerrar la deuda de `migrate`
> con **migraciones limpias** (hoy `migrate` levanta 56 de 92 tablas); (2) **actualizar a Laravel 13 /
> PHP 8.3** (ambos actuales están EOL → soporte + seguridad); (3) **suite de QA profunda** (headless,
> agente de QA) para blindar los saltos. Motivación del owner: **soporte y seguridad reales** para
> proteger la información clínica ("blindaje tipo militar"). Tracks aparte a futuro: **seguridad de
> verdad** (cifrado en reposo, sello HMAC, MFA, bitácora de acceso) y **PWA funcional + puente nativo
> (Capacitor)**. Plan completo por fases y estimación (~5-9 sesiones): dossier
> `Plan-Upgrade-L13-Migraciones-QA.md`; decisiones en la memoria [[code-health-and-db-baseline-plan]].

Documentos relacionados: [ARCHITECTURE.md](ARCHITECTURE.md) (estado actual),
[COVID-DECOMMISSION.md](COVID-DECOMMISSION.md) (qué retirar), [SECURITY.md](SECURITY.md),
[PERFORMANCE.md](PERFORMANCE.md), [PROGRESS.md](PROGRESS.md) (bitácora).

> **🧭 North-star de producto → [VISION-PRODUCT.md](VISION-PRODUCT.md).** Las fases técnicas de
> abajo se leen en ese contexto: el producto es **administración / coordinación de producción**
> (la cuña de venta en LATAM) con **H&S embebido como capa** sobre los datos que el producto ya
> captura. Esto reordena prioridades de venta, **no** reestructura las fases de este roadmap.

---

## 0. Dónde estamos (lectura honesta)

### Naturaleza de este repositorio (importante)
- **Este repo es la BASE / plantilla, OFFLINE, con datos de PRUEBA.** No es la app en
  producción. Los "usuarios" son de prueba ⟹ **podemos reestructurar a fondo (incluido
  rehacer tablas y renombrar campos) sin dañar nada en producción.**
- **Modelo de negocio:** cada despliegue es una **copia de esta base subida a un VPS y
  personalizada** por productora/estudio. O sea: esta base es una **plantilla que se
  forkea y configura por cliente**. Lo que mejoremos aquí se vuelve el nuevo estándar
  para los próximos despliegues; las instancias ya vivas de clientes son separadas y no
  se tocan hasta que se decida actualizarlas.
  - **🧭 Aclaración del owner (2026-06-24) — NO hay multi-producción DENTRO de una instancia.**
    Cada cliente = una **copia independiente** de la carpeta de la app en su propio VPS ⟹
    **una instancia = una producción**. Por eso la app **no** lleva navegación/selector de
    producción (p. ej. la pantalla de asignación de rol+depto opera sobre una sola "Producción
    Demo", sin selector). **Rationale del owner (blast radius):** prefiere esta independencia
    por instancia precisamente porque un update **centralizado** tendría radio de impacto total
    (un mal update podría tumbar a todos los clientes a la vez), mientras que un update **manual
    por instancia** solo afecta a una app — apropiado para datos sensibles de uso diario. Por eso
    los updates de clientes son **manuales, por instancia** (strangler), no centralizados.
- **Implicación de diseño:** conviene mover la personalización por cliente (branding,
  paquetes de documentos, módulos activos, textos) hacia **configuración**, no hacia
  ediciones de código por copia — eso hace la "receta" repetible y barata. (Ver RECIPE.md.)
- **Requisito de cliente (Amazon):** exige que la app corra sobre **su tecnología (AWS)**.
  Esto convierte AWS en **requisito de despliegue para clientes tier-Amazon**, no en un
  "algún día". La base debe ser **AWS-desplegable** (ver §4.5).

### Estado del código
Construido sin background formal de Laravel: lógica en GET, sin roles reales (un `bool
admin` + el campo `daytest` reutilizado como rol), sin migraciones, campos de BD
reutilizados con nombres que no corresponden a su uso (`age` = "tiene foto", `daytest` =
"rol", `formulario` = "historial médico" de ~50 campos, no encuesta COVID).

**Esto no es un defecto tuyo — es deuda técnica normal de un producto que creció a base
de necesidad.** El objetivo es convertir esta base en una plataforma tipo **Scenechronize**,
robusta/segura/escalable y **fácil de personalizar por cliente**.

### La visión (destino)
Una plataforma de producción audiovisual con tres patas:
1. **Health & Safety** (ya existe, es tu diferenciador): lesiones, peligros, condiciones
   inseguras, auditorías de locación, reporte diario de seguridad (DSR), reportes médicos.
2. **Documental de producción:** contratos, NDA, Deal Memo, Hiring Form, con **firma
   electrónica** estilo DocuSign (sin la capa de certificación ISO por ahora).
3. **Operación de set:** sides personalizados, breakdown/análisis de guion, cast & crew,
   scheduling — el territorio Scenechronize.

Todo unido por **roles reales** y **espacios de trabajo por departamento/producción**.

---

## 1. Principio rector: 3 capas de trabajo

Para no repetir los errores actuales, todo lo nuevo se construye en este orden de prioridad:

1. **Fundación primero** (roles, producciones, permisos). Sin esto, cada módulo nuevo
   vuelve a inventar su propio control de acceso con flags — el problema de hoy.
2. **Datos antes que pantallas.** Migraciones reales para cada tabla nueva (hoy no hay
   ninguna). Esto hace el esquema reproducible y versionable.
3. **Un módulo a la vez, vertical completo** (modelo → migración → permisos → controller
   → vista), en lotes de 5-20 archivos, no "todo a la vez".

---

## 1.5 Barrido arquitectónico por artefacto (transversal) — *petición explícita del owner*

**Principio cross-cutting (no una fase al final).** El owner pidió capturar como tarea explícita un
**barrido sistemático sobre CADA artefacto** del repo — **vistas, modelos, controllers, CSS, JS** —
para **arreglar su arquitectura, reconstruir el código sin bugs y eliminar los "parches del pasado"**
(las soluciones puntuales acumuladas que arrastran deuda).

- **Cómo se hace:** de forma **incremental, por el método de estrangulamiento** (*strangler*), **nunca
  un rewrite de cero**. Lo viejo se retira por vertical cuando el nuevo está verificado; la app sigue
  corriendo todo el tiempo. **Explicando el código a medida** que se reconstruye, para que el owner
  pueda adaptarlo después.
- **Es TRANSVERSAL, atraviesa TODAS las fases:** no es un bloque separado al cierre. **Cada vertical que
  tocamos** (Home, Profile, Search, Roles, y los que sigan) **limpia de paso sus propios
  views/models/controllers/CSS/JS** — su arquitectura, sus bugs y sus parches viejos. Así el barrido
  avanza con el trabajo normal, sin un "gran día de limpieza" final.
- **Encaja con la decisión #5** (reconstruir por verticales, no parchar en sitio — ver §3) y se ejecuta
  con el [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md) (descifrar → objetivo → reconstruir → verificar →
  retirar). El detalle accionable por artefacto vive en [RECIPE.md](RECIPE.md) (Bloques 1.5 / 1.6).

> En una línea: **cada artefacto que se toca queda con su arquitectura sana, sin bugs y sin parches del
> pasado** — incrementalmente, por verticales, no de golpe.

---

## 2. Plan por fases

> Las fases son **secuenciales en dependencia**, no necesariamente en calendario. Cada una
> deja la app funcionando. Los tiempos son relativos (S/M/L = small/medium/large esfuerzo).

### FASE 0 — Higiene y desmantelamiento COVID  · esfuerzo M
**Meta:** quitar peso muerto y dejar la base limpia antes de construir encima.
- Retirar todo lo COVID/PCR siguiendo el orden de [COVID-DECOMMISSION.md](COVID-DECOMMISSION.md)
  (rutas → vistas → jobs → controllers → modelos → columnas). **Cuidado con las trampas:**
  `daytest` (rol) y `age` (foto) NO se tocan; `formulario`/`cmedic` se **conservan**.
- Arreglar los críticos de app en vivo de [SECURITY.md](SECURITY.md): C2 (auto-promoción
  a admin por mass-assignment) y C3 (endpoints sin auth). Estos no esperan a GitHub.
- Borrar código muerto colateral (`checkfroms`, `newformulario1`, `*.bladeOLD`, `*BKP`).
- **Aún no** publicar a GitHub (eso es pre-requisito de Fase 1+ con `.env` ya saneado).

**Entregable:** app más pequeña, sin PCR, sin los 2 agujeros críticos, lista para construir.

### FASE 1 — Fundación: roles reales + producciones  · esfuerzo L · **la inversión clave**
**Meta:** reemplazar `bool admin` + `daytest` por un sistema de roles/permisos de verdad.
Sin esto, los módulos documentales y de set serán inmanejables.
- **Roles & permisos:** adoptar `spatie/laravel-permission` (estándar de facto en Laravel).
  Roles tipo: `super-admin`, `line-producer`, `coordinator`, `hod` (jefe de depto),
  `medic`, `safety-officer`, `crew`. Permisos granulares por acción.
- **Modelo `Production` (proyecto/rodaje)** y **`Department`** como entidades de primera
  clase. Hoy "departamento" existe pero la app no está organizada por producción — todo
  vive en un único espacio global. Esto habilita el **"espacio de trabajo por departamento"**
  que pides: un usuario pertenece a (producción × departamento × rol).
- **Pivot `production_user`** con rol por producción (un mismo usuario puede ser HOD en una
  y crew en otra). Migrar los datos actuales de `puestodepartamento`/`userpuesto` a esto.
- Reescribir `AdminMiddleware` y el menú (`sidebar`) para usar permisos, no flags.

**Entregable:** multi-producción, con roles reales y scoping por departamento. Es el
cambio que desbloquea todo lo demás. **Recomiendo no saltarse esta fase.**

> **✅ FUNDACIÓN RBAC CONSTRUIDA Y VERIFICADA (2026-06-24).** Ya están sembrados y verificados
> contra la BD: **8 roles** (los 7 del organigrama + `auditor` solo-lectura) + **39 permisos**
> granulares (spatie), modelos `Production`/`Department`/`Position`, y el pivote `production_user`.
> El **rol por producción vive en la columna `production_user.role`** (varchar, no spatie teams) →
> un usuario puede ser HOD en una producción y crew en otra. `positions.production_id` nullable =
> catálogo plantilla reutilizable + puestos propios por producción. Verificado: 35 deptos / 192
> puestos / 1 "Producción Demo" / 88 usuarios atados. **Backfill (2026-06-24): 75/88** usuarios mapeados a
> departamento/puesto (13 NULL diferidos a decisión de campo del owner). **MENÚ AHORA FINO ítem-por-ítem**
> (2026-06-24): el sidebar se reconstruyó a `@can('<permiso>')` por entrada (mismo permiso que protege la ruta → sin
> visible-pero-403; `daytest` retirado del menú). **Verticales del menú migrados a `permission:` middleware** (spatie):
> users.*/crew.view/medical.*/locations.*/hazards.*/dsr.*/injury.* salieron del grupo binario `admin` a grupos
> `permission:` (el flag `admin` se **retiró para esos verticales**; **catálogos y COVID/PCR siguen en `admin`**, pendientes
> del siguiente incremento → futuros `catalogs.*` + COVID-DECOMMISSION). Se **retiró el lock global
> `$this->middleware('admin')` del constructor de `AdminController`** (anulaba la migración per-ruta). Existen **4 cuentas
> de prueba** (`test.coordinador@`/`test.hod@`/`test.medico@`/`test.crew@`, `admin=0`, password conocida) para probar RBAC
> puro en incógnito. **Pendiente:** verificación en vivo de las 4 cuentas por el owner; migrar las rutas `admin` restantes
> (catálogos/COVID); construir la UI de asignación de roles; y los 13 títulos NULL del backfill. Detalle en
> [PROGRESS.md](PROGRESS.md), [AUTH-RBAC-PLAN.md](AUTH-RBAC-PLAN.md) y [RECIPE.md](RECIPE.md) §1.C.

### FASE 2 — Núcleo documental + firma electrónica  · esfuerzo L
**Meta:** la pata "documental de producción". Construir un **motor genérico**, nunca un
controller por documento. El requisito #3 (documentos variables por proyecto) manda aquí.

- **Motor de plantillas de documento:**
  - `DocumentTemplate` — plantilla reutilizable (Contrato, NDA, Deal Memo, Hiring Form,
    Safety Guidelines, Reporte Médico, y los que sean) con campos/variables
    (`{{nombre}}`, `{{puesto}}`, `{{tarifa}}`, `{{produccion}}`…). El contenido es editable
    (ya tienes TinyMCE) y el render final reutiliza tu **DomPDF**.
  - `Document` — instancia generada de una plantilla, poblada con datos del usuario/producción.
- **Paquetes configurables por producción (clave, del requisito #3):**
  - `DocumentPackage` por `Production` = el conjunto de documentos que ESA producción exige
    a su crew (p. ej. Amazon: Safety Guidelines + NDA + Deal Memo; otra producción: solo
    Hiring Form). Permite varios documentos y varios del mismo tipo.
  - Asignación a crew: cada miembro recibe su paquete y la app rastrea **estado por documento**
    (pendiente / enviado / firmado / vencido). Un dashboard de "quién falta de firmar qué".
- **Orden de construcción:** Hiring Form (entrada, alimenta a los demás) → Deal Memo → NDA
  → Contrato → Safety Guidelines. Pero el motor los trata a todos igual; agregar uno nuevo
  es crear una plantilla, no programar.
- **Reportes Médicos:** evolucionar `cmedic` + `formulario` (historial médico ya existente)
  a un módulo propio con permisos `medic`. Aquí ya tienes datos reales.
- **Firma electrónica interna (decisión #1, estilo DocuSign sin ISO):**
  - `SignatureRequest` (documento + firmantes + orden + estado) y `Signature` (firmante,
    trazo/typed, timestamp, IP, **hash del documento firmado**, user-agent).
  - Frontend: canvas de firma (`signature_pad`) + PDF sellado + **bitácora de auditoría**
    (quién, cuándo, desde dónde) — eso da validez de firma simple en MX.
  - **Extensibilidad (decisión #2):** el modelo se diseña para poder añadir después
    **sellado NOM-151** (constancia de conservación / timestamp confiable) o delegar a un
    proveedor externo, **sin rehacer** el flujo. Campo `provider` + tabla de evidencia desde el día 1.

**Entregable:** definir el paquete documental de cada producción, generarlo, enviarlo a
firma, rastrear quién firmó qué, y archivar el PDF sellado con su bitácora.

### FASE 3 — Operación de set (territorio Scenechronize)  · esfuerzo XL
**Meta:** la pata de set. Es la más grande; se hace por sub-módulos.
- **Cast & Crew** sobre la fundación de Fase 1 (ya tienes usuarios/departamentos/roles).
- **Breakdown / análisis de guion:** modelar `Script` → `Scene` → `BreakdownElement`
  (cast, props, vestuario, locación, etc.). Es el corazón de Scenechronize. Empezar por
  importar/registrar escenas y elementos manualmente; el parsing automático de guion
  (PDF/Final Draft) es una mejora posterior.
- **Sides personalizados:** generar PDFs de las escenas del día por persona/departamento
  (otra vez DomPDF + el motor de plantillas de Fase 2).
- **Scheduling / call sheets:** día de rodaje, escenas, llamados — se conecta con el DSR
  de Health & Safety que ya tienes (sinergia: el call sheet alimenta el reporte diario).

**Entregable:** la app ya compite en el espacio Scenechronize, con H&S como diferenciador.

> **⏸️ Call sheet — EN PAUSA / rediseño con dirección (2026-06-28 tarde).** El módulo inicial se construyó
> pero el owner decidió que **NO refleja un callsheet real**: un llamado se arma desde **guion(es)/script +
> cast list + números de personaje + necesidades por departamento**. Se reconstruirá **"cuando lleguemos con
> dirección"** (encaja con el Breakdown/análisis de guion de esta Fase 3). **RETIRADO:** `CallSheet` +
> `CallSheetController` + vistas `admin/callsheets/*` **movidos a `_legacy_backup/decommission-2026-06-28/`**;
> rutas `/call-sheets` y permisos `call_sheets.*` **eliminados**; sección "Llamados" del sidebar quitada; el
> `CREATE TABLE call_sheets` del SQL del owner queda **EN PAUSA** (no aplicar). **Idea que SOBREVIVE para el
> rediseño:** auto-adjuntar el Boletín CSATF aplicable (vía `reference_url`) cuando se identifique un riesgo —
> prevención específica del día para el crew. **⚠️ Copyright AMPTP/CSATF:** solo **enlazar** al PDF oficial de
> csatf.org (número/título), nunca copiar el contenido. Detalle en [PROGRESS.md](PROGRESS.md).

> **🎯✅ Plan "los 4 de una vez" (2026-06-28) — IMPLEMENTADO. Capa H&S sobre datos de producción.** Todo
> el código está en el repo (verificado `php -l` + `tinker` + `route:list` 10 rutas nuevas + Blade);
> **DEFENSIVO** (escrituras gateadas con `Schema::hasColumn`, módulos nuevos en paralelo al legacy) → nada
> se rompe antes de que el owner aplique el esquema. SQL consolidado en
> `database/owner-apply/2026-06-28-cuatro-items-safety.sql`. Detalle en [PROGRESS.md](PROGRESS.md):
> 1. **✅ `safety_standards.reference_url`:** seeder poblado (45 URLs CSATF + 6 OSHA + 10 STPS) + liga "Ver
>    boletín" en el DSR. **Δ:** `ADD COLUMN reference_url VARCHAR(500) NULL` + re-correr `SafetyCatalogSeeder`.
> 2. **✅ Catálogo normativo en Hazard/Cond. Insegura/Lesión:** select de `category_name` en sus create;
>    `store()` snapshotea `regulation_badge`+`regulation_code`; `show()` resuelve `reference_url` + badge.
>    **Δ:** `ADD COLUMN regulation_badge VARCHAR(20) NULL, regulation_code VARCHAR(50) NULL` en las 3 tablas.
> 3. **✅ Scouting H&S combinado — ahora el ÚNICO scouting (2026-06-28 tarde):** `ScoutingReport` +
>    `ScoutingReportController` + vistas `admin/scoutings/*` + rutas `/scoutings` (permisos `locations.*`).
>    Encabezado de emergencia + 13 categorías Sí/No/N-A + nivel + catálogo + gatillo SB132
>    (`requires_specific_ra`) + capa operativa (JSON); show estilo DSR. El **scouting viejo** (`location_report`,
>    161 campos: `locationController`/`locationController2` + vistas `location*`) quedó **DECOMISIONADO** (movido
>    a `_legacy_backup/decommission-2026-06-28/`, rutas `/location*` eliminadas; el modelo `locationreport` se
>    conserva solo por el dashboard → follow-up). Además ahora con **AUTOFIRMA** (sistema cerrado): `make_by`/
>    `make_date`/`created_by_id` se fijan **server-side** (no del form). **Δ:** `CREATE TABLE scouting_reports`
>    (incluye `created_by_id`).
> 4. **⏸️ Call sheet — EN PAUSA / rediseño con dirección** (ver nota de arriba — módulo retirado a
>    `_legacy_backup/`; `CREATE TABLE call_sheets` EN PAUSA).
> **Catálogo ya sembrado:** `safety_standards` 22→67 filas (45 CSATF + 6 OSHA/Cal-OSHA + 10 NOMs STPS)
> vía `SafetyCatalogSeeder`. Pendiente del owner: aplicar el SQL + re-correr el seeder. Transversal aún
> abierto: homologar print CSS al del DSR, chips de severidad/estado, matriz de riesgo 5×5.

### FASE 4 — Plataforma y puente BlackHouse  · esfuerzo M-L
- Exponer una **API real** (hoy Sanctum está casi sin usar) para el puente con BlackHouse
  (locaciones). El módulo de auditoría de locación que ya tienes es el punto de contacto
  natural: BlackHouse aporta locaciones, CrewCare aporta el H&S de esa locación.
- App móvil / PWA mejorada para set (ya tienes base PWA).

---

## 3. Decisiones tomadas (2026-06-23)

1. **Firma electrónica → INTERNA.** Gratis/barata, control total. Es indispensable y de
   uso intensivo (múltiples documentos, actualizaciones de contratos, re-firmas). Se
   construye in-house, pero el modelo `SignatureRequest` se deja **extensible** para
   poder delegar a un proveedor externo (o capa NOM-151) si el producto crece.
2. **Validez legal → firma simple por ahora.** Suficiente para la etapa actual. **Prever
   NOM-151** como evolución: la capa de firma debe poder añadir sellado de tiempo /
   constancia NOM-151 sin rehacerse. Diseñar pensando en eso desde el modelo de datos.
3. **Documentos → NO son una lista fija.** El orden de arranque sigue siendo
   Hiring Form → Deal Memo → NDA → Contrato, **pero** hay muchos más y **varían por
   proyecto**: p. ej. Amazon exige un **Safety Guidelines firmado por todo el crew**, y
   cada producción puede requerir documentos distintos y/o varios del mismo tipo. ⟹
   **Requisito firme:** el motor documental debe manejar **paquetes de documentos
   configurables por producción** (ver Fase 2), no documentos hardcodeados.
4. **GitHub → empezar de cero más adelante.** Se descartará todo el git actual y se hará
   un `git init` limpio cuando la app esté más completa. **Implicación buena:** el problema
   de "secretos en el historial" desaparece (historial nuevo). Solo queda como bloqueante,
   en ese momento futuro: `.gitignore` correcto + `.env` fuera del tracking + **rotar
   secretos** antes del primer push a cualquier remoto. Mientras tanto, no es urgente.
5. **Estrategia de ejecución → RECONSTRUIR por verticales, no parchar en sitio** (2026-06-24).
   Aprendido en la práctica: al "arreglar" el Quirks mode de `inicio` se **rompió el Home**, porque
   ese bug era **carga estructural** (el orden de Bootstrap sostenía el render). Regla resultante:
   - **Parchar en sitio SOLO:** (a) hoyos de seguridad peligrosos en vivo (C2/C3) y (b) borrar código
     muerto / COVID confirmado. Son cambios finitos y de bajo riesgo.
   - **Lo estructural (vistas, frameworks CSS/JS, esquema) NO se parcha: se RECONSTRUYE** sobre la
     fundación nueva, y lo viejo se retira **por vertical** cuando el nuevo está verificado (strangler).
     No hay big-bang final; la app sigue corriendo todo el tiempo. El Home se reemplaza por uno limpio
     en BS5 sobre la fundación nueva, y solo entonces se borra `inicio.blade.php`.
   - **Producción (instancia viva) = oráculo de verificación:** cada pantalla reconstruida se compara
     contra la instancia de producción antes de retirar la vieja.
   - **El inicio real de la reconstrucción es la FUNDACIÓN: esquema en migraciones + roles (spatie) +
     producciones** (Fase 1) — la buena práctica de arranque en Laravel. Plan concreto y verificado en
     **[AUTH-RBAC-PLAN.md](AUTH-RBAC-PLAN.md)**. Al escribir el esquema nuevo, las tablas/columnas COVID
     simplemente **no se incluyen** → eliminar COVID y reconstruir el esquema son la misma operación.
   - **No vale la pena pulir código que se va a reemplazar:** no perseguir cosméticos (Quirks, colisión
     BS4/BS5, duplicación de vistas) en el código viejo; mueren gratis al reconstruir ese vertical.

> **✅ STRANGLER DE `AdminController` CERRADO (2026-06-28 tarde, fase 3) — God Object 100% desmantelado.** El último corte (**paso #2,
> catálogos / `sort_order`**) se hizo **junto con su rediseño**: el CRUD de departamentos/puestos/notificaciones se carveó al NUEVO
> **`CatalogController`** operando sobre las **tablas NUEVAS** `departments`/`positions` (las que usa el alta de crew), con columna
> **`sort_order`** (orden canónico a Crew List/call sheet, reemplaza el hack muerto `users.labn`). Con los catálogos fuera,
> `AdminController` quedó vacío y fue **RETIRADO** a `_legacy_backup/` (cero referencias en `route:list`). **Además: dashboard
> rediseñado** (rama admin de `inicio`: banner CrewCare, 6 KPIs en grid responsivo 2→3→6, gráfica de tendencia fluida, Tailwind CDN
> homologado al DSR). Detalle en [PROGRESS.md](PROGRESS.md) y [ARCHITECTURE.md](ARCHITECTURE.md) §6.

---

## 3.5 ¿Reconstruir la base de datos y formalizar la estructura? — SÍ, pero por "estrangulamiento"

Tu instinto es correcto: **este es el momento de formalizar.** Y como esta base es
**offline con datos de prueba**, tenemos una ventaja enorme: **no necesitamos el cuidado
de migrar datos vivos** — podemos **rediseñar tablas y renombrar campos directamente**,
dejándolos bien desde el principio. (El *strangler pattern* aplica a las **instancias ya
desplegadas en clientes**, no a esta base; aquí somos libres.)

Eso sí, "libres" no es "a lo loco": el orden importa para no olvidar detalles. Aprovechamos
la libertad para hacer las cosas **bien y limpias**, no para improvisar.

**Estrategia concreta (orden de mayor a menor palanca):**

1. **Definir el esquema objetivo en migraciones, desde cero y limpio.** Como hay libertad,
   no solo hacemos *reverse-engineering* del esquema actual: lo **rediseñamos** donde haga
   falta (tablas con nombres correctos, FKs reales, sin campos reutilizados). El resultado
   es una BD **versionable y reproducible** — base para entornos de prueba y despliegues.
   *Ejemplo concreto que pediste:* el reporte médico de ~50 campos se rediseña a una tabla
   `medical_records` esbelta (campos núcleo + secciones opcionales normalizadas), no 50
   columnas planas.

2. **Estándares de integridad en TODO desde el día 1.** Toda tabla (nueva o rehecha) con
   migración, **claves foráneas**, `$fillable` con lista blanca (cierra el mass-assignment),
   nombres correctos. Arreglar de paso el PK con espacio de `departamentousuario` y el FK `id_usrio`.

3. **Renombres/saneamiento sin miedo (datos de prueba):** `daytest` → se **retira** al entrar
   roles reales (spatie); `age` → `has_badge_photo`; columnas COVID (`enfermo`, `lastpcr`,
   `resultpcr`, `tested`, `inline`, `labn`) → se eliminan; `formulario` → `medical_records`.
   Como no hay datos reales que preservar, es renombrar/recrear, no migrar con cuidado.

4. **Seed de datos de prueba realistas.** Al rehacer el esquema, crear **seeders/factories**
   (hoy vacíos) para repoblar datos de prueba — así cualquiera levanta la base desde cero.

**Decisión del owner (2026-06-25) — limpieza de la tabla `users`:**
- **Enfoque elegido: RENOMBRAR/DROPEAR columnas EN SU LUGAR** sobre la tabla `users` existente, vía
  migraciones — **NO una tabla nueva**. Una tabla nueva rompería el auth de Laravel + spatie + el
  pivote `production_user`; además `role`/`dept`/`position` ya se sacaron a spatie + `production_user`.
- **Mapa de columnas planeado:**
  - **DROP columnas COVID:** `tested`, `resultpcr`, `enfermo`, `inline`, `ultimatemperatura`, `inlined`,
    `lastpcr`, `labn`.
  - **RENAME `age` → `has_badge_photo`** (es flag "tiene foto de gafete", no edad).
  - **DROP `daytest`** (rol legacy 0/1/2, reemplazado por RBAC) **solo después** de desacoplar el
    sidebar/`usuarioscrud`. **KEEP `daytest`/`age` hasta entonces; NUNCA dropearlos a ciegas** antes de
    migrar su lógica.
- **Primer super-admin = creado por un SEEDER** (`php artisan migrate --seed`), **no** por una migración.
  Extender `TestAccountsSeeder` / añadir un `AdminUserSeeder` con el super-admin real para producción.

**Por qué esto sí es viable aquí (y no sería con clientes vivos):** big-bang sobre datos
reales = romper producción; big-bang sobre **datos de prueba** = simplemente construir bien.
La única regla: las instancias de clientes ya desplegadas se actualizan aparte, controladamente.

**Robusto / seguro / escalable — de dónde sale de verdad:**
- **Robusto** = migraciones + FKs + tests → el esquema deja de ser frágil.
- **Seguro** = roles reales (Fase 1) + `$fillable` blanco + cerrar C2/C3 → no más auto-admin.
- **Escalable** = la fundación de **roles + producciones**, NO el hosting. AWS no hace
  escalable una app; una estructura de datos sana sí. (Sobre AWS: ver nota al final.)

> Conclusión: formalizamos a fondo. Como esta base es offline con datos de prueba,
> **rediseñamos el esquema limpio en migraciones** (incluyendo simplificar el reporte
> médico) y construimos todo con estándares. La libertad es real; la disciplina (orden +
> migraciones + seeders) es lo que evita olvidar detalles — eso vive en **RECIPE.md**.

---

## 4. Riesgos y cómo los mitigamos
- **"Reescribir todo de golpe":** no. Fundación (Fase 1) + un módulo vertical a la vez.
- **Romper lo que hoy funciona:** cada fase deja la app operativa; los módulos H&S actuales
  no se tocan hasta que haya reemplazo probado.
- **Esquema no reproducible (sin migraciones):** desde Fase 1, **todo lo nuevo lleva
  migración**. Lo viejo se va migrando a medida que se toca.
- **No sabes Laravel a fondo:** por eso el plan prioriza estándares (spatie/permission,
  convenciones Laravel) sobre soluciones a medida — más documentación, menos sorpresas.

---

## 4.5 Infraestructura / AWS — requisito de cliente, no opcional

**Amazon exige que la app corra sobre su tecnología (AWS).** Por tanto AWS deja de ser
"algún día" y pasa a ser **requisito de despliegue para clientes tier-Amazon**. La base
debe ser **AWS-desplegable** sin re-arquitectura. Matiz importante: **AWS no es lo que
vuelve escalable la app** (eso es la fundación de datos/roles) — es *dónde* corre y un
requisito contractual. Las dos cosas son verdad a la vez.

**Mapeo de tu stack actual → AWS (para tenerlo claro desde ya, sin ejecutar):**
- **Archivos subidos (imágenes/PDF)** → **S3**. Hoy viven en el disco del servidor: no
  escala, frágil ante pérdida, e incompatible con varios servidores. Laravel cambia disco
  `public` → `s3` casi sin tocar código. *Es la pieza #1 y la que más conviene preparar pronto.*
- **MySQL** → **RDS** (administrado: backups automáticos, restauración, réplicas).
- **Correo (Mailgun)** → **SES** (más barato y dentro de AWS; encaja con el requisito).
- **Cómputo:** VPS+Plesk actual → **Lightsail** (migración más simple, parecido a un VPS) o
  **EC2/Elastic Beanstalk** para clientes que exijan más control/compliance.
- **PDFs** ya usan DomPDF (PHP puro), compatible con cualquier cómputo.

**Estrategia de doble pista:** clientes chicos pueden seguir en VPS; clientes tier-Amazon
se despliegan en AWS. Para que esto no duplique trabajo, la app debe abstraer disco/correo/BD
vía configuración (Laravel ya lo permite con `filesystems`, `mail`, `database`). Diseñar la
"receta" para que **cambiar de VPS a AWS sea cambiar `.env`/config, no código.**

Detalle de despliegue concreto se aborda al llegar a esa fase (conversación aparte).

---

## 4.6 Arquitectura objetivo (la foto del destino estable)

Esto es a dónde llegamos. Cada decisión apunta a **estabilidad** (menos sorpresas, menos
bugs, fácil de desplegar y mantener por un equipo sin Laravel profundo). Detalle de por qué
en [CODE-AUDIT.md](CODE-AUDIT.md).

**Backend**
- Controllers delgados por recurso (`UserController`, `DepartmentController`, …), no un God Object.
- **Form Requests** para toda entrada (hoy hay 0) — validación consistente.
- Capa **Services/Actions** para casos de uso (encuesta, mail masivo, imágenes, búsqueda).
- **Eloquent en todo** (sin `DB::table` suelto) + relaciones declaradas + `$casts` + `$fillable` blanco.
- **`DB::transaction()`** en flujos multi-paso; **Mailables + colas** (no `Mail::send` inline).

**Datos**
- Esquema en **migraciones** con FKs reales; campos con nombres correctos (sin `age`=foto, `daytest`=rol).
- **Roles reales** (spatie) + `Production`/`Department` + scoping por producción.
- Seeders/factories para levantar la base desde cero.

**Frontend**
- Blade + **un solo bundle** (Mix o Vite) **realmente compilado** y versionado con `mix()`/`@vite`.
- **Un solo Bootstrap (5)**, sin Materialize/Tailwind/Vue; jQuery una vez o eliminado.
- Librerías pesadas (TinyMCE/Chart.js) cargadas **por vista**, no en el shell global.
- Patrones JS repetidos extraídos a módulos del bundle.

**Infraestructura (config-driven, AWS-ready)**
- **Laravel 11 + PHP 8.2+** (hoy ambos EOL).
  > **🔮 FUTURO / DIFERIDO — Actualizar Laravel (8.x → versión LTS/actual soportada).**
  > **Objetivo:** mantener vigente el **soporte de seguridad** y la **compatibilidad con librerías**
  > necesarias (incl. `spatie/laravel-permission` y futuras dependencias).
  > **Aislamiento confirmado:** el update es **POR PROYECTO** — `composer update` dentro de
  > `crewcarerr/` solo reescribe `crewcarerr/vendor/`; **NO afecta a las demás apps** en
  > `C:\laragon\www\`. Lo único compartido en Laragon es el **binario de PHP global**, que solo
  > cambia si se modifica a mano.
  > **Momento (NO ahora):** va **DESPUÉS de la reconstrucción por verticales**, cuando cada parte
  > esté verificada contra producción. Razón: hay **bugs "load-bearing"** y **drift de migraciones**
  > (ver decisión #5 y bitácora); un salto de versión mayor sobre la base actual es **riesgoso y
  > difícil de rastrear**.
  > **Cómo, cuando llegue:** probar el upgrade en una **COPIA aislada** (no en esta carpeta), revisar
  > el **upgrade guide de Laravel por cada salto mayor**, y **verificar que la versión de PHP requerida
  > sea compatible** antes de tocar el PHP global de Laragon. (Detalle de etapas en RECIPE Bloque 4.)
- Disco/correo/sesión/caché/cola por **config/`.env`**: S3, SES, Redis, colas async — cambiar de
  VPS a AWS = cambiar `.env`, no código.
- Branding/`from`/cliente por **config** (no hardcode) → personalización por productora repetible.

**PWA**
- Manifest por `silviolleite/laravelpwa` + **service worker con Workbox** (precache automático,
  runtime caching, página offline standalone). Instalable y con offline real.

**Transversal**
- Primeros **tests** (hoy solo scaffolds) sobre los flujos críticos a medida que se reescriben.

---

## 5. Resumen de una línea
> Limpiar (Fase 0) → poner cimientos de roles y producciones (Fase 1) → documental + firma
> (Fase 2) → set/guion/sides (Fase 3) → plataforma + BlackHouse (Fase 4). Health & Safety,
> que ya tienes, es el diferenciador que sostiene todo.

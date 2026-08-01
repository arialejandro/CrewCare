# COVID-DECOMMISSION.md — Inventario de desmantelamiento COVID/PCR

> Inventario de planificación **solo lectura**. No se modificó ni borró código.
> Objetivo del dueño: **REMOVER** todo lo COVID/PCR (ya no se usa),
> **CONSERVAR** los módulos de Salud y Seguridad (lesiones, peligros/condiciones
> inseguras, auditorías de locación, reporte diario de seguridad — DSR) y
> **REPROPÓSITO** de la consulta médica (`cmedic`) hacia un futuro módulo genérico
> "Medical Reports".
>
> Verificado contra el código fuente real (no solo `ARCHITECTURE.md`). Cada artefacto
> se clasifica **REMOVE / KEEP / REPURPOSE** con evidencia `archivo:línea` y notas de
> dependencia. **Las trampas (campos reusados) están marcadas con ⚠.**

> **✅ DONE: Lote 3b (2026-06-25)** — `resetController` métodos `newTD`/`CleanR`/`Nresult` + sus rutas (`/newTD`, `/Nresult`) REMOVIDOS; modelos `prueba` + `usuariopcr` MOVIDOS a `_legacy_backup/`; **barrido de los 7 correos COVID huérfanos restantes** (`avisosg`/`certificados`/`nuevoingreso`/`personalizado`/`pruebas`/`pruebasentrada`/`testnotification`) MOVIDOS a `_legacy_backup/`. (`correos/avisos` se queda — aún lo referencia un método muerto de `FormulariosController`, cae en el lote de desacople.)
> **✅ DONE: Lote 4 (2026-06-25)** — página host `checkpoint` + `temperature` REMOVIDOS (`/checkpoint`, `/temperature`); vista `admin/checkpoint.blade.php` MOVIDA a `_legacy_backup/`. (`ultimatemperatura` queda como columna a dropear en el lote final de esquema.)
> **✅ DONE: Desacople 1/n (2026-06-25)** — botón NotSick + lectura de `enfermo` removidos de `usuarioscrud`/`search-results`/`comcrud`; método `notsick()` + ruta `/notsick`; `'enfermo'` fuera de la proyección de `SearchController`; mapeo COVID de `QueueImport` limpiado.
> **✅ DONE: Desacople 2/n (2026-06-25)** — métodos muertos de SÍNTOMAS eliminados: `FormulariosController` (`newformulario1`/`newformulario2`/`checkform`, archivo 668→194 líneas) + `encuestasController@checkfroms` (bug `findOrFail(id)`); `correos/avisos.blade.php` MOVIDO a `_legacy_backup/`. Con esto el acoplamiento **síntomas→`enfermo` queda totalmente eliminado**; `enfermo` queda **dormido** (solo data-setters) hasta el lote de esquema. **CONSERVADOS (clínicos vivos):** `registrarformulario` (`/formularios/medicos`) y `newformulario` (`/formularios/registro`). `route:list` arranca con **110 rutas**.
>
> **Restante COVID:** (1) **repurpose del formulario (gateado por diseño)** — los campos COVID `vacci1` (vacuna) / `crt19` (certificado) que el flujo VIVO `newformulario` + `formulario.blade.php` aún procesa; salen con el repurpose "Medical Reports". (2) **lote de esquema (ÚLTIMO)** — DROP de tablas/columnas.

---

## 0. Resumen de trampas críticas (leer primero)

| Artefacto | Apariencia | Realidad | Acción |
|---|---|---|---|
| `users.daytest` | "día de prueba PCR" | **ROL funcional 0/1/2** (admin/supervisor/médico). Lo setean `putga/putgb/putgg`; el sidebar y `usuarioscrud` ramifican el menú por él. | ⚠ **NO DROPEAR.** KEEP. Renombrar a `role` solo con refactor coordinado. |
| `users.age` | "edad" | **Flag "tiene foto de gafete"** (0/1). Lo setean `checkgft`/`uncheckgft`; lo lee `PhotoReminder` y `expCsv`. | ⚠ **NO DROPEAR.** KEEP. Pertenece a gafetes, no a COVID. |
| `formulario` / `formularios` (~50 cols) | "encuesta diaria de salud COVID" | Es un **historial médico** (tipo de sangre, antecedentes familiares mamá/papá, alergias, cirugías, vacunas, embarazo). Solo 2 campos son COVID: `vacci1` (vacuna COVID-19) y `crt19` (certificado COVID). El cuestionario de **síntomas** COVID (`sintoma1-8`, `convivencia`, `ninguno`) solo vive en **métodos muertos**. | **REPURPOSE** hacia "Medical Reports" (quitar `vacci1`/`crt19`); NO es un artefacto puramente COVID. |
| `users.lastwr` / `encuestadiaria` | "última prueba / encuesta COVID" | Hoy marcan el envío del **historial médico** (`newformulario` los setea), no PCR. | KEEP por ahora (acoplados al flujo de formulario que se reprop.). |
| `enviarCorreoTest` / `remindertest` "Salud y Seguridad" | parecen generales | Filtran por `tested=0 / daytest` (cola PCR) y mandan from `covid@crewcare.tech`. | **REMOVE** (son de la cola PCR pese al asunto). |

---

## 1. MODELOS (`app/Models/`)

| Modelo | Tabla | Clasificación | Por qué / Referencias |
|---|---|---|---|
| `pcr` | `pcr` | ~~**REMOVE**~~ **✅ DONE (Lote 2, 2026-06-25): modelo MOVIDO a `_legacy_backup/`** | `pcr.php:11`. Resultado PCR (`estado`). Lo usaban **solo** los 5 métodos PCR CRUD de `AdminController` (`pcrcrud/crearpcr/editarpcr/savepcr/eliminarpcr`), ya removidos (ver §3c). El `use App\Models\pcr;` de `AdminController` se quitó; `resetController` tenía un `use pcr` **muerto** (nunca lo usaba — usa `usuariopcr`/`prueba`) que también se retiró. |
| `prueba` | `prueba` | ~~**REMOVE**~~ **✅ DONE (Lote 3b, 2026-06-25): modelo MOVIDO a `_legacy_backup/`** | `prueba.php:11`. Resultado de lab (`resultado` 0/1). Sus escritores `positivepcr`/`negativepcr`/`negativeantg` se removieron en Lote 3; su último consumidor `resetController@Nresult` se removió en Lote 3b → modelo sin referencias vivas (verificado, cero `prueba::` en el código) → MOVIDO al backup. |
| `usuariopcr` | `usuariopcr` | ~~**REMOVE**~~ **✅ DONE (Lote 3b, 2026-06-25): modelo MOVIDO a `_legacy_backup/`** | `usuariopcr.php:21`. Junction user↔prueba (⚠ FK `id_usrio`, typo). Su lector `historial()` se removió en Lote 3; su último consumidor `resetController@Nresult` se removió en Lote 3b → modelo sin referencias vivas (verificado, cero `usuariopcr::` en el código) → MOVIDO al backup. |
| `queuevirtual` | `testqueue` | **REMOVE** | `queuevirtual.php:9`. Registro único `mainday` (2/3/4) que controla el día de prueba global. Lo usa `queueController` (`selecta/selectb/selectglobal`) y todo el switch de cola. |
| `userform` | `userform` | **REMOVE** — ⚠ **ahora HUÉRFANO (2026-06-25)** | `userform.php:11`. Junction user↔formulario, **solo lo escribían métodos muertos** (`newformulario2`, `checkform`); el `newformulario` activo NO lo usa. **✅ Esos métodos ya fueron REMOVIDOS (Desacople 2/n, 2026-06-25)** → el modelo quedó **sin escritores ni lectores vivos**. Listo para MOVER a `_legacy_backup/` (modelo + tabla `userform` al lote de esquema). |
| `formulario` | `formularios` | **REPURPOSE** | `formulario.php:21`. Historial médico (~50 campos). Base del futuro "Medical Reports". Quitar campos COVID `vacci1`/`crt19` (`formulario.php:65,70`). Lo leen `historialWR` y `cmedicController@create/index`. |
| `cmedic` | `cmedic` | **REPURPOSE** | `cmedic.php:19`. Consulta médica (`diagnosis/medication/observations/aditional`). **Candidato principal** a "Medical Reports". Lo cuenta el dashboard (`HomeController:40`) y lo usa `cmedicController`. |
| `message` | `message` | ⚠ **KEEP (revisar)** — **HUÉRFANO tras Lote 1** | Plantilla de correo editable (TinyMCE). Solo la usaba `queueController` (cola PCR), **ya removido (✅ Lote 1 2026-06-25)** → el modelo quedó **huérfano**. **Decisión pendiente:** REMOVE o reusar para correos del formulario; verificar antes que ninguna vista de correo superviviente lo lea. |
| `usuariosnotificacione` | `usuariosnotificaciones` | **KEEP** | Lista de destinatarios de alertas. La usan los módulos de seguridad supervivientes (`FormulariosController`, `positivepcr`). Quitar solo las llamadas COVID, no el modelo. |
| `User` | `users` | **KEEP** | Hub central. Ver §2 para las columnas COVID dentro de `users`. |
| `departamento`, `puesto`, `departamentousuario`, `userpuesto` | varias | **KEEP** | Organización. Sin relación COVID. |
| `InjuryReport`, `hazardnotification`, `unsafecond`, `locationreport`, `DailyReport`, `DailyLog`, `SafetyStandard` | varias | **KEEP** | Módulos de Salud & Seguridad a conservar. |

---

## 2. TABLAS / COLUMNAS DE BD

### 2a. Tablas COVID-específicas a DROPEAR (migrations last)
- `pcr` — **REMOVE** — ⏳ tabla aún viva pero ya **sin código** (modelo + CRUD removidos ✅ Lote 2 2026-06-25); el DROP de la tabla queda para el lote final de migraciones.
- `prueba` — **REMOVE**
- `usuariopcr` — **REMOVE**
- `testqueue` (modelo `queuevirtual`) — **REMOVE**
- `userform` — **REMOVE** (junction huérfano, solo métodos muertos)
- `formularios` — **NO dropear; REPURPOSE** (historial médico)
- `cmedic` — **NO dropear; REPURPOSE**

> ⚠ No existen migraciones para estas tablas (el esquema se creó fuera de Laravel,
> ver `ARCHITECTURE.md §2`). El "drop" será DDL manual / nueva migración, no editar
> una migración existente.

### 2b. Columnas COVID-ish en `users` (`User.php:20-49` = `$fillable`)

| Columna | Clasificación | Evidencia / quién la escribe-lee |
|---|---|---|
| `enfermo` | **REMOVE** (flujo COVID) ⚠ acoplado — ✅ **vista DESACOPLADA (2026-06-25)** | Lo setean `FormulariosController` (síntomas), `positivepcr`, `crearpcr/savepcr`, ~~`notsick`~~, resets. **Lo leía `usuarioscrud.blade.php:53`** (botón NotSick). ✅ **DONE (2026-06-25):** removido el botón NotSick de `usuarioscrud`/`search-results`/`comcrud`, el método `notsick()` + ruta `/notsick`, y `'enfermo'` de la proyección de `SearchController`. ⏳ Falta desacoplar `FormulariosController` (síntomas→`enfermo`) y los escritores a nivel de datos (`$fillable`, seeder, `newuser` default) antes de dropear. |
| `ultimatemperatura` | **REMOVE** | `AdminController@temperature:520-526`; `QueueImport` lo mapea como `temperatura`. Checkpoint de temperatura COVID. |
| `lastpcr` | **REMOVE** | Fecha de última PCR. `resetController@Nresult:93`, `positivepcr:624`, `crearpcr/savepcr`, `newuser:300`. |
| `lastwr` | ⚠ **KEEP (reusado)** | Hoy marca el envío del **historial médico** (`newformulario:450`, `registrarformulario:82`), no PCR. `historialwr` no lo usa para PCR. Mantener mientras el formulario se reprop. |
| `encuestadiaria` | ⚠ **KEEP (reusado)** | Gatea acceso a `formulario` (`encuestasController@viewencuesta:12`). Lo resetea `encuestasTask` y `resetController@newWR/newdayRep`. Atado al historial médico, no a PCR. |
| `resultpcr` | **REMOVE** | Resultado PCR. `resetController@newTD/Nresult/CleanR`, `positivepcr`, `negativepcr/antg`, `SendEmail` job, todos los switch de cola. |
| `tested` | **REMOVE** | Marca "ya probado" en la cola. `queueController@usertested`, todos los counts de cola, `negativepcr/antg`. |
| `inline` | **REMOVE** | "en fila" para prueba. `queueController@userqueue/usertested`, `crudtest`/`antigentest`. |
| `labn` | **REMOVE** | Consecutivo de laboratorio. `queueController@userqueue:100`; usado en búsquedas (`searchqueue/searchlab/searcheckpoint/searchidcard`). ⚠ Es término de búsqueda LIKE en varias vistas que sobreviven (idcard/checkpoint) — quitar de esos `Orwhere`. |
| `daytest` | ⚠⚠ **KEEP — NO DROPEAR** | **Es el ROL** (0/1/2). `putga=0/putgb=2/putgg=1` (`AdminController:110-130`); ramifica `sidebar.blade.php:25,97,139` y `usuarioscrud.blade.php:92`. Aunque la cola PCR también lo lee, su rol de autorización lo hace **imprescindible**. |
| `age` | ⚠⚠ **KEEP — NO DROPEAR** | **Flag "tiene foto de gafete"** (no edad). `checkgft@age=1` / `uncheckgft@age=0` (`AdminController:54,62`); `PhotoReminder` y `expCsv` filtran `age=0`; `QueueImport` lo mapea como `credencialp`. Pertenece a gafetes. |

**Otros campos reusados/mal nombrados detectados:**
- `device_token`, `workstation`, `zone`, `ncreditos`, `imgperfil` — no COVID, KEEP.
- `users.inlined` (no está en `$fillable` pero lo escribe `queueController@userqueue:101` y ordena la cola) — **REMOVE con la cola** (columna COVID de facto).
- `temperatura` mapeada en `QueueImport:34` no coincide con `ultimatemperatura` del fillable → posible columna fantasma; verificar esquema real antes de tocar el importador.

---

## 3. CONTROLLERS

### 3a. `queueController.php` — ~~**REMOVE COMPLETO** (cola PCR/antígeno)~~ **✅ DONE (Lote 1, 2026-06-25): controlador ENTERO MOVIDO a `_legacy_backup/`**
Todos los métodos eran COVID: `curdtest:17`, `searchqueue:40`, `userqueue:96`, `scheduletest:107`, `antigentest:133`, `usertested:160`, `selecta/selectb/selectglobal:171-193`, `mymail:194`, `enviarCorreoTest:203`, `remindertest:225`. From de correo `covid@crewcare.tech`. **Eliminado en bloque** junto con sus 13 rutas (ver §4) y vistas (ver §5). Ningún código vivo lo referencia.

### 3b. `resetController.php` — mixto
> ℹ️ **Limpieza (Lote 2, 2026-06-25):** `resetController` tenía un `use App\Models\pcr;` **muerto** (nunca lo usaba — el archivo trabaja con `usuariopcr`/`prueba`, no con `pcr`). Se retiró como limpieza de calidad de código al remover el modelo `pcr`. Sin cambio de comportamiento.
> ✅ **DONE (Lote 3b, 2026-06-25):** removidos los 3 métodos PCR `newTD`/`CleanR`/`Nresult`. Al quedar muertos, se retiraron además los imports `use App\Models\usuariopcr;`, `use App\Models\prueba;` y `use Carbon\Carbon;` (Carbon solo lo usaba `Nresult`). El archivo pasó de 169→119 líneas; `php -l` OK. **CONSERVADOS (clínicos / gafete):** `newdayRep`, `newWR`, `PhotoReminder`, `expCsv`.

| Método | Clasif. | Por qué |
|---|---|---|
| `newTD:45` | ~~**REMOVE**~~ **✅ DONE (Lote 3b)** | Reseteaba `resultpcr/inline/tested` (cola PCR). |
| `Nresult:85` | ~~**REMOVE**~~ **✅ DONE (Lote 3b)** | Creaba `prueba`+`usuariopcr`, marcaba PCR negativo (único consumidor vivo de esos 2 modelos → su remoción los liberó). |
| `CleanR:60` (no ruteado) | ~~**REMOVE**~~ **✅ DONE (Lote 3b)** | Reseteaba `resultpcr`. |
| `newWR:74` | ⚠ **KEEP/REPURPOSE** | Resetea `encuestadiaria` (gate del historial médico). General, no PCR. |
| `newdayRep:22` | ⚠ **KEEP/REPURPOSE** | Resetea `encuestadiaria` + manda recordatorio. General (asunto "DAILY REPORT" del **cuestionario de expediente clínico** / `DailyReport`, NO COVID — ver nota ⓘ en §3c). |
| `PhotoReminder:109` | **KEEP** | Recordatorio de **foto de gafete** (filtra `age=0`). No COVID. |
| `expCsv:130` | **KEEP** | Export CSV "nophoto" de crew. No COVID. |

### 3c. `AdminController.php` — métodos PCR a **REMOVE**
~~`pcrcrud:748`, `crearpcr:756`, `editarpcr:772`, `savepcr:777`, `eliminarpcr:792`~~ **✅ DONE (Lote 2, 2026-06-25): los 5 métodos PCR CRUD ELIMINADOS de `AdminController` (reemplazados por un comentario breadcrumb); el `use App\Models\pcr;` ahora sin uso también se quitó**, ~~`positivepcr:580`, `positivepcrO:551`, `notoday:571`, `notodays:562`, `negativepcr:650`, `negativeantg:692` (usa Dompdf de certificado), `mainlab:547`, `testpcrcrud:528`~~ **✅ DONE (Lote 3, 2026-06-25): los 8 métodos del vertical lab/resultados PCR ELIMINADOS de `AdminController` (reemplazados por comentarios breadcrumb)** — `historial` (historial PCR, devolvía `componentes.historia`, join `usuariopcr`+`prueba`), `testpcrcrud` (`/listcrew`), `mainlab` (`/lab`), `positivepcrO` (no ruteado), `notoday`, `notodays`, `positivepcr`, `negativepcr`, `negativeantg` (PDF Dompdf de certificado). Se quitaron además los `use` ahora muertos: `use App\Models\usuariopcr;`, `use App\Models\prueba;`, `use Dompdf\Dompdf;`, y `use ZipArchive;` (este último ya era un import muerto previo → limpieza de calidad de código). ~~`searchlab:464`~~ **✅ DONE (2026-06-24): método + ruta `/searchlab` + vista `componentes/searchlab` ELIMINADOS** (respaldado en `_legacy_backup/`), ~~`temperature:520`~~ **✅ DONE (Lote 4, 2026-06-25): método REMOVIDO** (escribía `users.ultimatemperatura`, checkpoint de temperatura COVID), ~~`historial:328` (join `usuariopcr`+`prueba`)~~ **✅ DONE (Lote 3, 2026-06-25)**. **KEEP confirmado (sí siguen presentes):** `historialWR` (clínico), `welcomeresend`, `sendreminder`.

> ✅ **DONE (Lote 4, 2026-06-25):** removidos `checkpoint()` (devolvía `admin/checkpoint`, control de temperatura COVID) y `temperature()` (escribía `users.ultimatemperatura`); `php -l` OK. **`notsick()` se difirió al lote de desacople** (escribe `enfermo`, acoplado al botón NotSick de `usuarioscrud`).

**KEEP (no COVID) en AdminController:** ~~`checkpoint:67`~~ **✅ REMOVIDO (Lote 4 — era COVID, no se conserva)**, `checkgft/uncheckgft:51,59` (gafete=age), `idcardscrud/idcard`, CRUD de usuarios/deptos/puestos/notificaciones, `putga/putgb/putgg` (rol), `notsick:132` (resetea `enfermo` — atado al formulario), `historialWR:339` (lee `formularios` → REPURPOSE), `sendreminder:799` (recordatorio del **cuestionario de expediente clínico** / DailyReport — asunto "DAILY REPORT", filtra `encuestadiaria=0`; **NO es COVID**, ver nota ⓘ abajo), `newuser:287`/`welcomeresend:632` (bienvenida).

> ⓘ **Aclaración del dueño (2026-06-25): `DailyReport` (el cuestionario diario) = "expediente clínico" (cuestionario de expediente clínico), NO es COVID** y se **CONSERVA** durante el decommission. `sendreminder` pertenece a este flujo clínico (no a la cola PCR) y **se quedó**. No confundir con `formulario`/`formularios` (historial médico, REPURPOSE) ni con la cola PCR.
> ⚠ `checkpoint`/`searcheckpoint`: el "checkpoint" era control de acceso por temperatura COVID. Funcionalmente huérfano al irse `temperature`/`ultimatemperatura`. **REMOVE** confirmado para vista+ruta+método de checkpoint; el método `searcheckpoint` solo alimentaba esa vista.
> ✅ **DONE (2026-06-24): `searcheckpoint` (método + ruta `/searcheckpoint` + vista `componentes/searcheckpoint`) ELIMINADO** (respaldado en `_legacy_backup/`).
> ✅ **DONE (Lote 4, 2026-06-25): la página host `admin/checkpoint.blade.php` (MOVIDA a `_legacy_backup/`) + ruta `/checkpoint` + método `checkpoint` REMOVIDOS** junto con `temperature`. No existía `admin/checkpoint-mobile.blade.php`.

### 3d. Otros controllers
| Controller | Clasif. | Nota |
|---|---|---|
| `cmedicController` | **REPURPOSE** | Base de "Medical Reports". `create:31` lee `formularios`. |
| `FormulariosController` | **REPURPOSE parcial** — ✅ **métodos muertos DONE (2026-06-25)** | `newformulario:416` (ACTIVO) guarda historial médico → **KEEP/REPURPOSE (se quedó)**. `registrarformulario:24` (`/formularios/medicos`) idem → **se quedó**. **✅ Métodos muertos REMOVIDOS (Desacople 2/n, 2026-06-25):** `newformulario1` (empezaba con `DD()`), `newformulario2`, `checkform` (cuestionario de síntomas COVID `sintoma1-8`/`convivencia` + `userform` → `enfermo`); archivo **668→194 líneas**; `php -l` OK. ⏳ Queda el **repurpose** del formulario vivo: `vacci1`/`crt19` (gateado por diseño). |
| `encuestasController` | **KEEP/REPURPOSE** — ✅ **dead method DONE (2026-06-25)** | `viewencuesta:10` (`/dailyreport`, gate por `encuestadiaria`) → **se quedó**. **✅ `checkfroms` REMOVIDO (Desacople 2/n, 2026-06-25)** — era código muerto con bug (`findOrFail(id)` con la constante `id` indefinida). |
| `MailController@sendMail:9` | ~~**REMOVE**~~ **✅ DONE (Lote 3, 2026-06-25)** | Disparaba `SendEmail` (negativo PCR). Terminaba en `dd()`. **Controller ENTERO MOVIDO a `_legacy_backup/`** junto con su ruta `/negative-mail`. ℹ️ Se removió el historial PCR (`historial`) pero el clínico `historialWR` se **CONSERVA**. |
| `ReminderMailController@ReminderMail:11` | ⚠ **KEEP/REVISAR** | Dispara `ReminderEmail` (recordatorio genérico "DAILY REPORT"). No es PCR per se, pero el Job tiene bug de sintaxis (ver §6). |
| `exportController` | ~~**REMOVE**~~ **✅ DONE (Lote 1, 2026-06-25)** | Usaba `AntigenTestExport`. **MOVIDO a `_legacy_backup/`** junto con la ruta `/testexport`. |
| `importController` | ⚠ **KEEP/REPURPOSE** | Importa crew vía `QueueImport`, que mapea columnas COVID (`tested/resultpcr/labn/temperatura`). KEEP el import de crew pero **limpiar el mapeo COVID** del importador. |

---

## 4. RUTAS (`routes/web.php`)

### REMOVE (COVID/PCR/cola/temperatura/checkpoint/resets PCR)
- Resets PCR sin auth: ~~`/newTD:31`, `/Nresult:33`~~ **✅ DONE (Lote 3b, 2026-06-25): ambas rutas ELIMINADAS** (⚠ `/newWR:32` y `/newdayRep:29` = KEEP, son del formulario; `/PhotoReminder:30` = KEEP gafete — **verificado: siguen presentes**).
- Correos PCR: ~~`/negative-mail:40` (SendEmail)~~ **✅ DONE (Lote 3, 2026-06-25): ruta ELIMINADA** (con `MailController`), `/reminder-mail:41` (revisar, genérico — **KEEP**, se quedó).
- PCR CRUD: ~~`/pcrcrud:74`, `/crearpcr:75`, `/editarpcr:76`, `/savepcr:77`, `/eliminarpcr:78`~~ **✅ DONE (Lote 2, 2026-06-25): las 5 rutas ELIMINADAS**.
- Temperatura: ~~`/temperature:72`~~ **✅ DONE (Lote 4, 2026-06-25): ruta ELIMINADA** (con `checkpoint`).
- Lab/resultado: ~~`/lab:118`, `/listcrew:119`, `/positivepcr:120`, `/negativepcr:122`, `/negativeantg:123`~~ **✅ DONE (Lote 3, 2026-06-25): las 5 rutas ELIMINADAS**.
- Cola virtual: ~~`/virtualqueue:129`, `/antigentest:130`, `/crudtest:131`, `/selecta:132`, `/selectb:133`, `/selectglobal:134`, `/userqueue:135`, `/usertested:136`, `/remindertest:137`, `/searchqueue:112`~~ **✅ DONE (Lote 1, 2026-06-25): las 10 rutas ELIMINADAS**, ~~`/searchlab:109`~~ **✅ DONE (2026-06-24): ruta ELIMINADA**.
- Correos de cola: ~~`/mymail:71`, `/enviarcorreos:116`~~ **✅ DONE (Lote 1, 2026-06-25): ambas ELIMINADAS** (`mymail`, `enviarcorreos`=`enviarCorreoTest`).
- Export antígeno: ~~`/testexport:127`~~ **✅ DONE (Lote 1, 2026-06-25): ruta ELIMINADA**.
- Checkpoint (temperatura COVID): ~~`/checkpoint:57`~~ **✅ DONE (Lote 4, 2026-06-25): ruta host ELIMINADA**, ~~`/searcheckpoint:110`~~ **✅ DONE (2026-06-24): ruta ELIMINADA**.
- ~~`/historial/{id}:104` (historial PCR) — REMOVE.~~ **✅ DONE (Lote 3, 2026-06-25): ruta ELIMINADA**. ⚠ `/historialWR:105` (historial médico) = KEEP/REPURPOSE (**verificado: sigue presente**).

### KEEP
- Auth scaffolding (`Auth::routes():35`), `/`, `/home`, `/locale`, `/offline`, `/profile`, `/changepassword`, `/crop-image*`.
- Resets del formulario: `/newdayRep:29`, `/newWR:32`, `/PhotoReminder:30`, `/nophoto:198`.
- Encuesta/historial médico: `/dailyreport:37-38`, `/formularios/registro:202`, `/formularios/medicos:203`.
- Medical Reports (REPURPOSE): `/medicocrud:49`, `/consulta/{id}:50`, `/cmedica/{id_user}:51`, `/comcrud:48`.
- Crew/org: `/usuarioscrud`, `/adduser`/`/newuser`, deptos, puestos, notificaciones, idcards (`/checkgft`,`/uncheckgft`,`/idcardscrud`,`/idcard`), rol (`/putadm`,`/putmed`,`/putsup`), `/importcrew`+`/crewstore` (limpiar mapeo COVID).
- Seguridad (todo el bloque): location/location2, hazard, unsafe, accidents (injury), DSR (`/dsr-reports*`).

---

## 5. VISTAS (`resources/views/`)

### REMOVE
- ~~`virtualqueue/scheduletest.blade.php`, `virtualqueue/antigentest.blade.php`, `virtualqueue/crudtest.blade.php` (toda la carpeta `virtualqueue/`).~~ **✅ DONE (Lote 1, 2026-06-25): carpeta `virtualqueue/` completa MOVIDA a `_legacy_backup/`**.
- ~~`pcrtest/listcrew.blade.php`, `pcrtest/mainlab.blade.php` (toda `pcrtest/`).~~ **✅ DONE (Lote 3, 2026-06-25): carpeta `pcrtest/` completa MOVIDA a `_legacy_backup/`**.
- ~~`admin/pcrcrud.blade.php`, `admin/pcredit.blade.php`~~ **✅ DONE (Lote 2, 2026-06-25): ambas vistas MOVIDAS a `_legacy_backup/`**.
- ~~`admin/checkpoint.blade.php` (host)~~ **✅ DONE (Lote 4, 2026-06-25): MOVIDA a `_legacy_backup/`** (temperatura COVID; `admin/checkpoint-mobile.blade.php` no existía).
- Componentes de búsqueda PCR: ~~`componentes/searchqueue.blade.php`~~ **✅ DONE (Lote 1, 2026-06-25): MOVIDO a `_legacy_backup/`** (se fue con la cola, `queueController@searchqueue`), ~~`componentes/searchlab.blade.php`~~ **✅ DONE (2026-06-24): ELIMINADA** (respaldada en `_legacy_backup/`), ~~`componentes/searcheckpoint.blade.php`~~ **✅ DONE (2026-06-24): ELIMINADA** (respaldada en `_legacy_backup/`), ~~`componentes/historia.blade.php` (historial PCR)~~ **✅ DONE (Lote 3, 2026-06-25): MOVIDA a `_legacy_backup/`**.
- Correos COVID: ~~`correos/positive.blade.php`, `correos/negative.blade.php`, `correos/negativean.blade.php`, `correos/notifypositive.blade.php`~~ **✅ DONE (Lote 3, 2026-06-25): las 4 MOVIDAS a `_legacy_backup/`**; ~~`correos/avisosg.blade.php`, `correos/certificados.blade.php`, `correos/nuevoingreso.blade.php`, `correos/personalizado.blade.php`, `correos/pruebas.blade.php`, `correos/pruebasentrada.blade.php`, `correos/testnotification.blade.php`~~ **✅ DONE (Lote 3b, 2026-06-25): las 7 MOVIDAS a `_legacy_backup/`** (su único consumidor era `queueController@scheduletest`, ya en backup desde Lote 1). ⚠ `correos/avisos.blade.php` **se QUEDA** — aún la referencia `FormulariosController:605` (método muerto que cae en el lote de desacople).

### REPURPOSE
- `formulario.blade.php` ⚠ — formulario de historial médico; **quitar bloque COVID** (`vacci1`=COVID-19 `:357`, `crt19`=certificado COVID `:398-399,464,519`). El resto (sangre, antecedentes, alergias) es la base de Medical Reports.
- `accesos/autorizado.blade.php`, `accesos/noautorizado.blade.php` — pantallas de resultado del tamizaje. Hoy las devuelven los métodos **muertos** de síntomas; el `newformulario` activo hace `redirect('/')`. REMOVE si se confirma que solo las usan métodos muertos; si se conserva un "gate" de Medical Reports, REPURPOSE.
- `componentes/historiamr.blade.php` / `historiawr.blade.php` — historial médico (`cmedicController@index`, `historialWR`). REPURPOSE.
- `admin/cmedica.blade.php`, `admin/medicocrud.blade.php`, `admin/comcrud.blade.php` — consultas médicas. REPURPOSE.

### KEEP (pero desacoplar referencias COVID — ⚠ trampas)
- `admin/usuarioscrud.blade.php` ⚠ — Crew List. ~~Referencia `enfermo`/`notsick` (`:53-54`)~~ **✅ DONE (2026-06-25): botón NotSick + `enfermo` removidos** (también del parcial AJAX `componentes/search-results.blade.php`); `historialWR` (`:75`), `daytest` rol (`:92-125`) **se quedan**. Conservar lo demás.
- `admin/useredit.blade.php`, `admin/idcardscrud.blade.php` — limpiar campos COVID mostrados (temperatura/resultpcr/labn). *(Los parciales `componentes/searchidcard.blade.php`, `componentes/searchusers.blade.php`, `componentes/searchcom.blade.php` ya fueron ELIMINADOS como huérfanos tras la consolidación del `SearchController` — searchcom el 2026-06-24, searchidcard+searchusers el 2026-06-24; respaldados en `_legacy_backup/`.)*
- `layouts/sidebar.blade.php` ⚠ — ramifica por `daytest` (rol). **NO tocar la lógica de `daytest`**; ya no enlaza pantallas PCR.
- Archivos stale detectados: `admin/idcard.bladeOLD.php`, `componentes/searchusers.bladeBKP.php` (no COVID; limpieza aparte).

---

## 6. JOBS / COMMANDS / SCHEDULER

| Artefacto | Clasif. | Nota |
|---|---|---|
| `app/Jobs/SendEmail.php` | ~~**REMOVE**~~ **✅ DONE (Lote 3, 2026-06-25)** | Mandaba `correos.negative` a `tested=1, resultpcr=0` (resultado PCR). From `covid@crewcare.tech`. **MOVIDO a `_legacy_backup/`** (junto con `MailController` y la ruta `/negative-mail`). |
| `app/Jobs/ReminderEmail.php` | ⚠ **KEEP/REVISAR** | Recordatorio genérico (`correos.recordatorio`). **Tiene bug de sintaxis** (`:45` comilla sin cerrar `from("covid@crewcare.tech,"CrewCare")`) → no compila; from COVID. Si se conserva como recordatorio del formulario, arreglar string y cambiar el from. |
| `app/Console/Commands/encuestasTask.php` (`encuestas:task`) | ⚠ **KEEP/REPURPOSE** | Resetea `encuestadiaria` y manda `correos.recordatorio`. Es del **formulario diario**, no PCR. Pero corre **cada minuto** (`Kernel.php:27`) — revisar cadencia. |
| `app/Console/Kernel.php:27` | KEEP | Solo programa `encuestas:task`. Sin comandos COVID adicionales. |
| `app/Exports/AntigenTestExport.php` | ~~**REMOVE**~~ **✅ DONE (Lote 1, 2026-06-25)** | Renderizaba `virtualqueue/antigentest`. **MOVIDO a `_legacy_backup/`** (junto con `exportController` y la ruta `/testexport`). |
| `app/Imports/QueueImport.php` | ~~⚠ **REPURPOSE**~~ **✅ DONE (2026-06-25): mapeo COVID removido** | Import de crew; ~~**mapea columnas COVID** (`enfermo`,`temperatura`,`tested`,`resultpcr`,`inline`,`labn` `:33-39`)~~ **mapeo COVID limpiado** (`enfermo`/`temperatura`/`inline`/`tested`/`resultpcr`/`labn`). **Se queda** el import base + `age` (gafete) y `daytest` (rol legacy). ⚠ Aún trae `afterImport` muerto con `Mail` sin importar. |

---

## 7. ACOPLAMIENTOS COMPARTIDOS / RIESGOS (qué se rompe)

1. **Dashboard (`HomeController`/`inicio.blade.php`)** — KPI `totalMedicalConsults = cmedic::count()` (`HomeController:40`). Como `cmedic` se **REPROPÓSITO**, el KPI sobrevive. **No depende de PCR.** Seguro.
2. **`User` model** — quitar columnas COVID del `$fillable` (`User.php:37-47`) **rompería `acountupdate`/`newuser`/`QueueImport`** si aún las setean. Desacoplar escritores antes. ⚠ Nunca quitar `daytest`/`age` del fillable.
3. **`usuarioscrud.blade.php`** — ~~usa `enfermo`/`notsick`~~ ✅ **DONE (2026-06-25): `enfermo`/botón NotSick removidos** (vista + parcial AJAX `search-results` + tint de filas en `comcrud` + método/ruta + proyección `SearchController`). Sigue usando `historialWR`/`daytest` (se quedan). Ya desacoplada de `enfermo` a nivel de **vistas**. ✅ **El acoplamiento síntomas→`enfermo` también quedó RESUELTO (Desacople 2/n, 2026-06-25):** los métodos de síntomas de `FormulariosController` se removieron, así que ya **nadie escribe `enfermo` por flujo COVID**; solo lo escriben data-setters (`$fillable`/seeder/`newuser` default) → `enfermo` queda **dormido** hasta el lote de esquema.
4. **Sidebar / autorización** — todo el menú ramifica por `daytest` (rol). Si por error se trata `daytest` como columna PCR y se dropea, **se cae la navegación y la separación de roles** de toda la app.
5. **Búsquedas que sobreviven** (`searchidcard`, `searchusers`, idcard) hacen `Orwhere('labn', LIKE)`. Al dropear `labn` esas queries fallan → quitar el `Orwhere('labn')` primero.
6. **`message` (TinyMCE)** — solo la usaba la cola PCR (`queueController`), **ya removido (✅ Lote 1 2026-06-25)** → **ahora huérfana**; decidir REMOVE o reusar para correos del formulario. **Decisión pendiente.**
   - ℹ️ **Nota (Lote 1):** los dos enlaces a `/userqueue` en `layouts/header.blade.php` están **dentro de comentarios HTML** (inertes) → al eliminar la ruta `/userqueue` no se rompió ninguna navegación.
7. **`usuariosnotificacione`** — compartida: la usaban `positivepcr` (✅ ya removido) **y** `FormulariosController@newformulario2` (✅ ya removido, Desacople 2/n) **+** los flujos de seguridad (KEEP). Conservar el modelo; ya quedan solo los consumidores de seguridad.
8. **`accesos/autorizado·noautorizado`** — ✅ **resuelto al remover los métodos de síntomas (Desacople 2/n, 2026-06-25):** `accesos/noautorizado.blade.php` **se queda** — aún la usa el método VIVO `registrarformulario`. `accesos/autorizado.blade.php` quedó **huérfana** (solo la renderizaba el removido `checkform`) pero se **deja en su sitio por ahora** (no es claramente COVID — es una pantalla de resultado de cuestionario; decidir su destino con el repurpose de Medical Reports).
9. **`formulario` ≠ COVID** — error fácil: tratar `formularios` como tabla COVID y dropearla destruiría el historial médico (datos clínicos/legalmente sensibles). Solo se quitan `vacci1`/`crt19`.

---

## 8. (a) ORDEN DE REMOCIÓN RECOMENDADO

> Principio: cortar de afuera hacia adentro. **Vistas/rutas primero** (dejan de
> invocar), **columnas/tablas al final** (cuando ya nadie las escribe/lee).

1. **Rutas** (`routes/web.php`): comentar/eliminar el bloque PCR/cola/temperatura/checkpoint/lab y los resets `/newTD`,`/Nresult`,`/negative-mail`. (Mantener `/newWR`,`/newdayRep`,`/PhotoReminder`.)
2. **Vistas**: borrar `virtualqueue/*`, `pcrtest/*`, `admin/pcrcrud·pcredit`, `admin/checkpoint*`, `componentes/searchqueue·searchlab·searcheckpoint·historia`, correos COVID. **Desacoplar** (editar, no borrar) `usuarioscrud`, `useredit`, `idcardscrud`, `sidebar`.
3. **Jobs/Commands/Export-Import**: borrar `SendEmail`, `AntigenTestExport`; limpiar mapeo COVID de `QueueImport`; revisar `ReminderEmail`/`encuestasTask`.
4. **Controllers**: borrar `queueController`, `exportController`, `MailController`; borrar métodos PCR de `AdminController` y métodos muertos de `FormulariosController`/`encuestasController`; podar `resetController` (dejar `newWR/newdayRep/PhotoReminder/expCsv`).
5. **Modelos**: borrar `pcr`, `prueba`, `usuariopcr`, `queuevirtual`, `userform`. (Conservar `formulario`/`cmedic` repropuestos.)
6. **Migraciones / columnas (ÚLTIMO)**: dropear tablas `pcr`,`prueba`,`usuariopcr`,`testqueue`,`userform`; dropear columnas `users`: `enfermo`,`ultimatemperatura`,`lastpcr`,`resultpcr`,`tested`,`inline`,`labn`,`inlined`.
   - ⚠ **NO dropear** `daytest` (rol) ni `age` (gafete).
   - ⚠ **Conservar** `lastwr` y `encuestadiaria` (atados al historial médico repropuesto) hasta decidir el rediseño de Medical Reports.

## 8. (b) DESACOPLAR PRIMERO (antes de cualquier borrado)

1. **`daytest`**: aislar como `role` real (o al menos documentar) para que nadie lo confunda con cola PCR. Bloquea cualquier drop accidental.
2. **`age`**: documentar/renombrar a `has_badge_photo`; lo leen `PhotoReminder`/`expCsv`/`idcard`.
3. **`usuarioscrud.blade.php` + `searchusers/searchidcard`**: quitar referencias a `enfermo`, `notsick`, `labn` (LIKE) y temperatura **antes** de tocar columnas. ✅ **DONE (2026-06-25):** `enfermo`/`notsick` removidos de `usuarioscrud` + parcial AJAX `search-results` + `comcrud` (tint) + `SearchController` (proyección) + `QueueImport` (mapeo); **+ síntomas→`enfermo` en `FormulariosController` también removido** (Desacople 2/n — métodos muertos `newformulario1/2`/`checkform`). ⏳ Falta solo: `temperatura`/`resultpcr`/`labn` en `useredit`/`idcardscrud`.
4. **`User::$fillable`**: quitar columnas COVID solo después de eliminar todos sus escritores (`acountupdate`, `newuser`, `QueueImport`, resets).
5. **`usuariosnotificacione` y `message`**: separar las llamadas COVID de las de seguridad/formulario; decidir destino de `message`.
6. **`formulario.blade.php` + modelo**: extraer/retirar `vacci1`/`crt19` y confirmar que `historialWR`/`cmedicController` no dependan de ellos, antes de repropósito a Medical Reports.
7. **`accesos/autorizado·noautorizado`**: confirmar (grep) que solo los usan métodos muertos antes de borrar.
8. **Correos `from` COVID** (`covid@crewcare.tech`): centralizar/limpiar en los flujos que sobreviven (`newuser`, recordatorios) para no romper SPF al quitar el dominio COVID.

---

### Apéndice — Inventario de correos `correos/*`
✅ **DONE — MOVIDOS a `_legacy_backup/`:** `positive`, `negative`, `negativean`, `notifypositive` (Lote 3, 2026-06-25); `avisosg`, `certificados`, `nuevoingreso`, `personalizado`, `pruebas`, `pruebasentrada`, `testnotification` (Lote 3b, 2026-06-25 — barrido de huérfanos, su consumidor era `queueController@scheduletest`, ya en backup); **`avisos` (Desacople 2/n, 2026-06-25 — su único consumidor vivo era el ya removido `newformulario2`).** Con esto **TODOS los correos COVID están MOVIDOS a `_legacy_backup/`.**
KEEP: `recordatorio` (formulario), `welcomeuser`/`bienvenida`/`welcomeresend` (alta de crew), `notificaciones` (alertas de seguridad), `photo` (gafete), `CrewCareTemplate` (layout).

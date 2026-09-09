# ORG-TAXONOMY.md — Taxonomía de Departamentos y Puestos (semilla RBAC)

**Qué es esto:** la taxonomía **normalizada** de departamentos y puestos extraída de un
**call sheet real** de una producción audiovisual grande (serie, ~245 crew, Día 118 de 123),
para usarse como **semilla** de las entidades `Department` y `positions` de la fundación RBAC
([AUTH-RBAC-PLAN.md](AUTH-RBAC-PLAN.md) B.2, [DATABASE-SCHEMA.md](DATABASE-SCHEMA.md)).

> ## ⚠ ACTUALIZACIÓN 2026-08-06 (catch-up post-compact)
> La taxonomía de departamentos/puestos de abajo **sigue vigente** (es referencia del call sheet, no caduca).
> Lo que cambió: **el RBAC ya NO es "plan" — está VIVO** (`spatie/laravel-permission`, tablas `roles`,
> `permissions`, `model_has_roles`, `role_has_permissions`; `Gate::before` = super-admin pasa todo). Los puestos
> van como **datos** (`positions`), no como roles — tal cual anticipaba §0. **Catálogo real de permisos hoy**
> (de los seeders, gatean rutas vía `permission:xxx`):
> `crew.{register,view,view.contact,view.personal}`, `users.{create,update,view,deactivate}`,
> `productions.{create,update,view,delete}`, `catalogs.{view,manage}`, `documents.{create,view,sign,assign}`,
> `reports.{view,export}`, `settings.manage`, `badge.design`,
> **H&S:** `hazards.{create,view,manage}`, `hazardevents.{create,view,manage}`, `standards.{create,view,manage}`,
> `locations.{create,view,manage}`, `injury.{create,view,manage}`, `dsr.{create,view,update,export}`,
> `sds.{create,view,manage}`, `tools.inspect`, `permits.issue`, `medevac.issue`, `riskmap.issue`, `epi.view`,
> **Médico:** `medical.{create,view,update,materials,consolidate}`, `medic.credential.manage`.
> Fuente de rol médico = `User::isMedic()` (rol Spatie `medic`), no el viejo `daytest`. Detalle vivo en la **memoria**.

**Alcance / reglas:**
- Solo **lectura** del código y la BD. El único archivo escrito es este.
- **Sin PII:** se extrae solo la **estructura** (nombres de departamento y títulos de puesto).
  **Ningún nombre de persona** del call sheet aparece aquí (es crew real).
- **Deduplicado y normalizado:** los sufijos numéricos / "On Set" / "Asst." / "1·2·3" se
  colapsan a un puesto canónico (ej. "Eléctrico 1..4" → **Eléctrico**); la jerarquía
  (HOD/jefe vs asistente) se indica donde es obvia.
- **Fuente:** `TGH_Llamado_D118_081924.md` (volcado tabular desordenado) + sección
  "Canales de Radio" (la agrupación que la **propia producción** usa) como referencia de
  departamentalización.

---

## 0. TL;DR

- **~32 departamentos** identificados (consolidables a ~28 "canónicos"), frente a los **12**
  que hoy existen en la tabla `departamentos`. La BD actual cubre ~⅓ y está sesgada a
  Construction (datos de prueba).
- **~200 títulos de puesto** en el call sheet → **~150 puestos canónicos** tras deduplicar.
  Hoy la tabla `puestos` tiene **18 filas** (16 son de Construction/Greens; casi nada del resto).
- **Veredicto de roles:** los ~200 puestos **colapsan limpiamente a los 7 roles RBAC** ya
  diseñados (`super-admin`, `line-producer`, `coordinator`, `hod`, `medic`, `safety-officer`,
  `crew`). El call sheet **no obliga** a inventar roles nuevos. (Ver §4 sobre el 8.º rol,
  `auditor`, que es funcional pero no proviene del call sheet.)
- **Los PUESTOS van como DATOS** (filas en `positions`), **no como roles**. Esto es lo correcto:
  ~150 puestos no son 150 roles; son catálogo organizacional cuyo permiso se deriva del rol
  contextual en `production_user.role` ([AUTH-RBAC-PLAN.md](AUTH-RBAC-PLAN.md) B.2.3).

---

## 1. Departamentos normalizados (~32)

Agrupados por los **16 Canales de Radio** que la producción define (referencia organizativa
real del call sheet). Columna **BD** = ¿existe ya en la tabla `departamentos` (12 filas)?

> Canales de Radio (textual del call sheet): 1. Dirección · 2. Libre · 3. Producción ·
> 4. Locaciones · 5. Cámara · 6. Grips & Eléctricos · 7. VTR, DIT & Sonido · 8. Vestuario ·
> 9. Maquillaje y Peinados · 10. Set Dressing - Props · 11. Pic Cars · 12. Stunts ·
> 13. Catering · 14. Seguridad · 15. Transpo · 16. Servicios Médicos.

| # | Departamento (canónico) | Canal Radio | Bloques del call sheet que agrupa | ¿En BD? |
|---|---|---|---|---|
| 1 | **Dirección** | 1 | Director(es), Asistente de Director, Continuidad | No |
| 2 | **Asistente de Dirección (AD)** | 1 | 1er/2nd/2nd-2nd AD, Set PA, Cast PA, Key Set PA | No |
| 3 | **Producción** | 3 | Productor en Línea, Gerente de Producción/Unidad, Asst. Gerente | ✅ `Production` |
| 4 | **Oficina de Producción** | 3 | Coord. de Producción, APOC, Coord./Asst. de Viajes, Asst. de Oficina, Recepción | No |
| 5 | **Producción Ejecutiva** | 3 | Productor(a) Ejecutivo, Coord./Asst. Ejecutivo, Jefa de Equipo, Ejecutivo Creativo | No |
| 6 | **Escritores / Guion** | — | Creador/Escritor, Coord. de Guiones, Storyboardista | No |
| 7 | **Cámara** | 5 | DoP, Op. Cámara (A/B/Steadicam), 1er/2ndo AC, Cámara PA, DIT, Data Manager | No |
| 8 | **Eléctricos** | 6 | Gaffer, Supervisor Eléctricos, Eléctrico, Operador Consola | No |
| 9 | **Grips** | 6 | Key Grip, Grip, Grip Asst., Dolly Grip | No |
| 10 | **Rigging** | 6 | Rigging Gaffer, Rigging Key Grip, Electric (rig), Grip (rig) | No |
| 11 | **Sonido** | 7 | Sonido Directo, Operador de Boom, Utility | No |
| 12 | **Video / VTR** | 7 | Operador de VTR, Asistente de Video | No |
| 13 | **Maquillaje y Peinados (M&P)** | 9 | Diseñador(a) M&P, Coord. MU&H, Asst. Maquillaje/Peinados, Apoyo M&P | No |
| 14 | **Vestuario** | 8 | Diseñador(a) Vestuario, Supervisor(a), Coord., Comprador, Vestuarista, Sastrería, Costura | ✅ `Wardrobe` |
| 15 | **Arte** | 1/10 | Diseñador de Producción, Director de Arte, Coord. Arte, Diseñador de Sets/Gráfico, Artista Conceptual | ✅ `Art` |
| 16 | **Decoración / Set Dressing** | 10 | Decorador(a), Set Dresser, Coord. Decoración, Leadman, Swing Gang, Ambientador | No¹ |
| 17 | **Construcción** | 6 | Constructor, Coord. Construcción, Foreman, Carpintero, Pintura Escénica, Herrero | ✅ `Construction` |
| 18 | **Utilería / Props** | 10 | Jefe de Utilería, Utilería en Set, Asistente, Compras, Bodeguero Props | No |
| 19 | **Locaciones** | 4 | Gerente de Locaciones, Coord., Asst., PA, Soporte de Locaciones, Encargado Basecamp | ✅ `Locations` |
| 20 | **Transportación** | 15 | Coord. Transporte, Capitán, Dispatcher, Choferes (SUV/Van/Camión/Pickup), Trainee | ✅ `Transportation` |
| 21 | **Picture Cars** | 11 | Jefa Picture Cars, Coord., Asst. Picture Cars | No |
| 22 | **Efectos Especiales (SFX)** | — | Supervisor de SFX, Efectos Especiales, SFX Oficina | No |
| 23 | **Casting** | — | Director/Asociado/Coord./Asistente de Casting, Supervisora de Elenco | No |
| 24 | **Extras / BG** | — | Director de Casting Extras, Coord. de Extras | No |
| 25 | **Stunts** | 12 | Coordinador de Stunts (+ crew de Stunts) | No |
| 26 | **Música** | — | Supervisora de Música (+ depto Música) | No |
| 27 | **VFX** | — | Productor/Supervisor de VFX, Supervisor de VFX en Set | No |
| 28 | **Post Producción** | — | Supervisor/Coord./Asistente de Postproducción | No |
| 29 | **Contabilidad** | — | Contador de Producción, Asst. Contable, Contador Fiscal, Auxiliar/Asistente | ✅ `Accounting` / `Fiscal`² |
| 30 | **Catering / Craft** | 13 | Coord. de Craft, Cafetero, Crew Cocina, Asst. Cafetero | No |
| 31 | **Salud y Seguridad (S&S)** | 16 | Supervisor de S&S, Asistente, Doctor en Set, Doctor de Construcción, EFD/Ambulancia | No |
| 32 | **Sustentabilidad / Greener** | — | Jefa de Sustentabilidad, Coord., Greener en Set, Logística Greener | No³ |
| 33 | **Seguridad (vigilancia)** | 14 | Coordinador de Seguridad, Coord. HG, Elementos "Seguridad HG" | No |
| 34 | **A.N.D.A.** | — | Delegada del A.N.D.A. (representación sindical de actores) | No |
| 35 | **BTS / Foto Fija** | 2 | Foto Fija (behind-the-scenes / still photographer) | ✅ `BTS` / `Photo`⁴ |

> **Notas de cruce con la BD (12 deptos actuales):**
> 1. ¹ La BD no tiene "Decoración" explícito; los puestos de set dressing hoy caen bajo `Art`
>    (ej. `Set Decorator` está adjunto a Construction id 6 — ver §2). Recomendado: depto propio.
> 2. ² La BD tiene **dos** deptos contables: `Accounting` y `Fiscal`. El call sheet los trata
>    como **un** departamento (Contabilidad) con un puesto "Contador Fiscal". Consolidar a uno.
> 3. ³ La BD tiene `Greens` (id 10) — pero `Greens` (plantas/jardinería, parte de Arte/Construcción)
>    **no es lo mismo** que "Sustentabilidad/Greener" (sostenibilidad ambiental) del call sheet.
>    Ojo a la colisión de nombre: son departamentos distintos.
> 4. ⁴ La BD separa `BTS` y `Photo`; el call sheet solo lista "Foto Fija". Probable solapamiento.
>
> **Departamentos en la BD que NO aparecen como tales en el call sheet:** `Travel & Living`
> (id 4 — es más bien una categoría de logística; en el call sheet "Viajes" cae en Oficina de
> Producción), `Greens` (ver ³). El resto de la BD mapea a algún bloque del call sheet.

**Resumen de cobertura:** de los 12 deptos de la BD, **8 mapean** directo a bloques del call
sheet (`Production`, `Art`, `Construction`, `Wardrobe`, `Transportation`, `Locations`,
`Accounting`, `BTS/Photo`); **2** son cuestionables/colisión (`Greens`, `Travel & Living`);
**~24 departamentos del call sheet faltan** por completo en la BD (Dirección, AD, Cámara,
Eléctricos, Grips, Rigging, Sonido, Video, M&P, Decoración, Utilería, Picture Cars, SFX,
Casting, Extras, Stunts, Música, VFX, Post, Catering, S&S, Sustentabilidad, Seguridad, A.N.D.A.,
Producción Ejecutiva, Escritores, Oficina de Producción).

---

## 2. Puestos por departamento (deduplicados, con jerarquía)

Convención de jerarquía: **[HOD]** = jefe de departamento / head; **[2]** = mando intermedio /
key / supervisor; sin marca = crew/asistente. Los sufijos numéricos del call sheet se colapsan
(ej. "Eléctrico 1–4" → un puesto). Cruce con la BD se hace al final (§2.B).

### Dirección
- Director **[HOD]**, Asistente de Director **[2]**, Continuista, Asst. Continuista.

### Asistente de Dirección (AD)
- 1er AD **[HOD]**, Key 2nd AD **[2]**, 2nd AD, 2nd 2nd AD (Set/Basecamp), Key Set PA **[2]**,
  Set PA, Cast PA.

### Producción
- Productor en Línea **[HOD]**, Gerente de Producción **[2]**, Gerente de Unidad **[2]**,
  Asst. Gerente de Unidad.

### Oficina de Producción
- Coordinador(a) de Producción **[HOD]**, APOC **[2]**, Coordinador de Viajes,
  Asst. de Coordinador de Viajes, Asst. de Oficina de Producción, Recepción de Oficina.

### Producción Ejecutiva
- Productor(a) Ejecutivo **[HOD]**, Coord. Ejecutivo **[2]**, Jefa de Equipo, Ejecutivo Creativo,
  Asistente Ejecutivo, Coordinador(a) (ejecutivo).

### Escritores / Guion
- Creador/Escritor **[HOD]**, Escritor (por episodio), Coordinador(a) de Guiones, Storyboardista.

### Cámara
- DoP / Director de Fotografía **[HOD]**, Operador de Cámara (A/B/Steadicam/Trinity) **[2]**,
  1er AC, 2ndo AC, Cámara PA, DIT, Data Manager, Asistente de DoP.

### Eléctricos
- Gaffer **[HOD]**, Supervisor de Eléctricos **[2]**, Eléctrico, Operador de Consola.

### Grips
- Key Grip **[HOD]**, Grip, Grip Asst., Dolly Grip.

### Rigging
- Rigging Gaffer **[HOD]**, Rigging Key Grip **[HOD]**, Electric (rigging), Grip (rigging).

### Sonido
- Sonido Directo **[HOD]**, Operador de Boom **[2]**, Utility.

### Video / VTR
- Operador de VTR **[HOD]**, Asistente de Video.

### Maquillaje y Peinados (M&P)
- Diseñador(a) de M&P **[HOD]**, Coordinador(a) MU&H **[2]**, Asst. Maquillaje, Asst. Peinados,
  Apoyo M&P.

### Vestuario
- Diseñador(a) de Vestuario **[HOD]**, Asst. Diseñador(a) **[2]**, Supervisor(a) de Vestuario **[2]**,
  Coordinador de Vestuario, Asst. Coordinador, Comprador(a), Vestuarista (On Set / Basecamp / Extras),
  Asistente de Vestuario, Jefa de Sastrería **[2]**, Jefa de Taller de Costura **[2]**, Costurero(a),
  Diseñador Gráfico de Vestuario, Asst. de Compras.

### Arte
- Diseñador de Producción **[HOD]**, Director de Arte **[2]**, Director de Arte en Set **[2]**,
  Asst. de Dirección de Arte, Asst. de Diseño de Producción, Coordinador(a) de Arte,
  Asistente de Coord. de Arte, Asistente de Oficina de Arte, Diseñador(a) de Sets,
  Diseñador(a) Gráfico, Asst. de Diseñador Gráfico, Artista Conceptual.

### Decoración / Set Dressing
- Decorador(a) **[HOD]**, Decorador en Set **[2]**, Coordinador de Decoración **[2]**,
  Asst. Coord. de Deco, Set Dresser, Asistente Decorador en Set, Apoyo Decorador en Set,
  Compras de Decoración, Leadman **[2]**, Swing Gang, Apoyo (1..5), Asistente de Decoración.

### Construcción
- Constructor **[HOD]**, Coordinador(a) de Construcción **[2]**, Asst. Coord. Construcción,
  Foreman / Construction Foreman **[2]**, Carpintero, Jefe de Carpintería **[2]**, Herrero,
  Jefe de Pintura Escénica **[2]**, Pintor, Asistente de Pintor Escénico, Compras de Construcción,
  Apoyo Eventual de Construcción, Bodeguero, Welder¹, Gang Boss Welder¹.

### Utilería / Props
- Jefe de Utilería **[HOD]**, Utilería en Set **[2]**, Asistente de Utilería,
  Asistente de Utilería en Set, Compras de Utilería en Set, Bodeguero Props, Apoyo Eventual, Animalero².

### Locaciones
- Gerente de Locaciones **[HOD]**, Gerente Asst. de Locaciones **[2]**, Coordinador(a) de Locaciones **[2]**,
  Asst. Coordinador, Asst. de Locaciones, P.A. de Locaciones, Apoyo de Locaciones,
  Gerente de Soporte de Locaciones **[2]**, Soporte de Locaciones, Apoyo de Limpieza,
  Encargado de Basecamp, Personal de Loc.

### Transportación
- Coordinador de Transporte **[HOD]**, Capitán de Transporte **[2]**, Co Capitán **[2]**,
  Dispatcher, Asistente de Coord. de Transporte, Contador de Transporte, Asistente de Transportación,
  Trainee de Transportación, Chofer (SUV/Van/Camión/Pickup/Stakebed/Camper/Vannette/Cargo).

### Picture Cars
- Jefa de Picture Cars **[HOD]**, Coordinador de Picture Cars **[2]**, Asst. de Picture Cars.

### Efectos Especiales (SFX)
- Supervisor de Efectos Especiales **[HOD]**, Efectos Especiales (técnico), Efectos Especiales Oficina.

### Casting
- Director de Casting **[HOD]**, Asociado de Casting **[2]**, Coordinador de Casting,
  Asistente de Casting, Supervisora de Elenco.

### Extras / BG
- Director de Casting de Extras **[HOD]**, Coordinador(a) de Extras.

### Stunts
- Coordinador de Stunts **[HOD]** (+ crew de Stunts listado en bloque sin título de puesto).

### Música
- Supervisora de Música **[HOD]**.

### VFX
- Productor de VFX **[HOD]**, Supervisor de VFX **[HOD]**, Supervisor de VFX en Set **[2]**.

### Post Producción
- Supervisor de Postproducción **[HOD]**, Coordinador de Postproducción **[2]**,
  Asistente de Postproducción.

### Contabilidad
- Contador de Producción **[HOD]**, 1er/2ndo/3er Asst. Contable, Contador Fiscal **[2]**,
  Auxiliar de Contabilidad, Asistente de Contabilidad.

### Catering / Craft
- Coordinador de Craft **[HOD]**, Cafetero **[2]**, Asst. Cafetero, Crew de Cocina.

### Salud y Seguridad (S&S)
- Supervisor de Salud y Seguridad **[HOD]**, Asistente de S&S, Doctor en Set **[2]**,
  Doctor de Construcción, Ambulancia (EFD).

### Sustentabilidad / Greener
- Jefa de Sustentabilidad **[HOD]**, Coordinador de Sustentabilidad **[2]**,
  Encargado de Logística Greener, Greener en Set, Greener Adicional en Set.

### Seguridad (vigilancia)
- Coordinador de Seguridad **[HOD]**, Coordinador HG **[2]**, Elemento "Seguridad HG".

### A.N.D.A.
- Delegada del A.N.D.A. (representante sindical de actores).

### Otros / transversales
- Doble (cast), Coach de Dialecto, Coordinadora de Intimidad, Intérprete de Señas, Foto Fija (BTS).

> ¹ `Welder`/`Gang Boss Welder`/`Head Welder` están en la BD bajo Construction pero **no aparecen
> en este call sheet** (esta producción no soldó ese día) — se conservan como puestos válidos del catálogo.
> ² `Animalero` (manejo de animales) aparece suelto; encaja bajo Utilería o un depto "Animales" propio.

### 2.B — Cruce con los 18 `puestos` actuales de la BD

| `puestos` actual (id, name) | Depto BD | ¿Aparece en call sheet? | Acción recomendada |
|---|---|---|---|
| 1 `adm` | BTS | No (placeholder) | Descartar — no es un puesto real. |
| 2 `developer` | Art | No | Descartar — placeholder técnico, no de producción. |
| 3 `Construction Larbor` (sic) | Construction | Sí ("Apoyo Eventual Construcción") | Renombrar → "Construction Labor". |
| 4 / 16 `Greens Labor` (duplicado) | Greens / Construction | Parcial (Greener) | Deduplicar; reasignar a Sustentabilidad o Greens-Arte. |
| 5 `Welder` | Construction | No | Conservar (catálogo). |
| 6 `Carpenter` | Construction | Sí (Carpintero) | Conservar. |
| 7 `Construction Manager` | Construction | Sí (Constructor/Coord.) | Conservar. |
| 8 `Construction Coordinator` | Construction | Sí (Coord. de Construcción) | Conservar. |
| 9 `Bodeguero` | Construction | Sí (Bodeguero) | Conservar. |
| 10 `Buyer` | Construction | Sí (Compras de Construcción) | Conservar. |
| 11 `Foreman` | Construction | Sí (Foreman) | Conservar. |
| 12 `Head Welder` | Construction | No | Conservar (catálogo). |
| 13 `Gang Boss Welder` | Construction | No | Conservar (catálogo). |
| 14 `Green Man` | Construction | Parcial | Consolidar con Greens. |
| 15 `Greens Person` | Construction | Parcial | Consolidar con Greens. |
| 17 `Set Decorator` | Construction | Sí (Decorador/Set Dresser) | Reasignar a depto **Decoración**. |
| 18 `Costume Designer` | Wardrobe | Sí (Diseñador de Vestuario) | Conservar. |

> **Diagnóstico:** los 18 puestos actuales son **casi todos de Construction/Greens** (test data)
> más 2 placeholders (`adm`, `developer`) a descartar y 1 typo (`Larbor`). **No existe ni un solo
> puesto** de Dirección, Cámara, Eléctricos, Grips, Sonido, AD, M&P, Producción, etc. → el catálogo
> de `positions` debe **reconstruirse casi por completo** desde §2 (este call sheet es la mejor semilla).

---

## 3. Leyenda de estatus / Nomenclatura del call sheet

Estos códigos describen el **estado de llamado** de cada crew para ese día — **dónde está, si
se reporta, y desde dónde sale**. **NO son roles** ni permisos. Se documentan aquí solo para
trazabilidad; **no deben modelarse en RBAC**.

| Código | Significado (interpretado) |
|---|---|
| **O/C** | On Call — disponible/de guardia (no necesariamente físicamente en set). |
| **D/C** | Day Call — llamado normal del día (se reporta). |
| **N/A** | No Aplica — la columna no aplica a ese crew. |
| **N/C** | No Call — no llamado ese día. |
| **S/D** | Stand By / Sin Definir — pendiente de definir hora/lugar. |
| **Per Loc** | Personal puesto **Por Locaciones** (lo provee el depto de Locaciones). |
| **Per Transpo** | Provisto **Por Transportación** (choferes y vehículos). |
| **Per ALOSA** | Provisto **Por ALOSA** (proveedor de catering/alimentación). |
| **Sume** | Punto de encuentro: **Sumesa** (Av. Centenario, Coyoacán). |
| **Zapa** | Punto de encuentro: **Metro Zapata**. |
| **Casa** | Sale desde **domicilio particular**. |

> **Recomendación de modelado (anotación, NO diseño):** si en el futuro se construye un módulo
> de **Hojas de Llamado (Call Sheets)** ([ROADMAP.md](ROADMAP.md) Fase 1, "operación de set"),
> estos códigos serían un **enum de "estado de llamado" por crew-por-día** (una tabla
> `call_sheet_entries` con campos `pickup_status`, `report_location`, etc.), **nunca** roles ni
> departamentos. Los puntos de encuentro (`Sume`/`Zapa`/`Casa`) y proveedores
> (`Per Loc`/`Per Transpo`/`Per ALOSA`) serían catálogos de "ubicación de pickup" / "proveedor".
> **Fuera de alcance de la fundación RBAC** — solo se deja anotado.

---

## 4. Mapeo PUESTO → ROL de la app (los ~200 puestos → 7 roles)

La prueba clave de la fundación: **muchísimos puestos, pocos roles.** El permiso real no lo da
el título del puesto sino el **rol contextual** en `production_user.role`
([AUTH-RBAC-PLAN.md](AUTH-RBAC-PLAN.md) B.2.3). Regla de colapso:

| Rol RBAC | Criterio de mapeo | Ejemplos de puestos del call sheet |
|---|---|---|
| **super-admin** | Acceso total a la instancia (operador del sistema, no del set). | (No surge del call sheet — es rol de plataforma; ej. dueño/IT.) |
| **line-producer** | Mando de producción: gestiona producciones, crew, catálogos, presupuesto. | Productor en Línea, Gerente de Producción, Gerente de Unidad, Productor(a) Ejecutivo, Coordinador(a) de Producción, APOC. |
| **coordinator** | Coordina crew/documentos dentro de su producción/departamento. | Coordinador de Vestuario/Arte/Decoración/Construcción/Locaciones/Transporte/Extras/Casting/Picture Cars/Craft, Coord. de Viajes, Coord. de Guiones, Coord. de Postproducción. |
| **hod** | Jefe de departamento (Head of Department) — ve/gestiona **su** depto. | Director, 1er AD, DoP, Gaffer, Key Grip, Rigging Gaffer, Diseñador de Producción, Diseñador(a) de Vestuario, Diseñador(a) de M&P, Sonido Directo, Operador de VTR, Decorador(a), Jefe de Utilería, Gerente de Locaciones, Coordinador de Transporte, Jefa de Picture Cars, Supervisor de SFX, Director de Casting, Coordinador de Stunts, Supervisor de VFX, Supervisor de Postproducción, Contador de Producción, Jefa de Sustentabilidad, Coordinador de Seguridad, Constructor. |
| **medic** | Personal médico — acceso a reportes médicos. | Doctor en Set, Doctor de Construcción, Ambulancia (EFD). |
| **safety-officer** | Responsable de H&S (lesiones, peligros, locación, DSR). | Supervisor de Salud y Seguridad, Asistente de S&S. |
| **crew** | Todo lo demás: self-service (perfil, sus documentos, su encuesta). | 1er/2ndo AC, Eléctrico, Grip, Carpintero, Pintor, Vestuarista, Asst. Maquillaje, Set Dresser, Swing Gang, Chofer, Cafetero, PA, Asistente (cualquiera), Animalero, etc. — **la gran mayoría (~85% del crew).** |

> **Sobre "Seguridad" (vigilancia / Coordinador HG):** el departamento **Seguridad** (canal 14,
> guardias "Seguridad HG") **NO** es `safety-officer`. `safety-officer` = Salud & Seguridad
> (H&S laboral / módulo de lesiones). La vigilancia física es **crew** (o, si gestiona, `coordinator`).
> Trampa de nombres a evitar al sembrar.
>
> **A.N.D.A. / Delegada sindical, Coach de Dialecto, Coord. de Intimidad, Intérprete de Señas:**
> son **crew** (especialistas sin privilegios de sistema). No requieren rol nuevo.

### ¿El call sheet sugiere algún ROL adicional más allá de los definidos?

**No.** Los ~200 puestos colapsan **sin residuo** a los **7 roles** ya diseñados en
[AUTH-RBAC-PLAN.md](AUTH-RBAC-PLAN.md) B.1.1 (`super-admin`, `line-producer`, `coordinator`,
`hod`, `medic`, `safety-officer`, `crew`). Mantener roles **gruesos (coarse-grained)** es lo
correcto: la granularidad fina (qué hace exactamente un Gaffer vs un Eléctrico) se expresa con
**permisos** + el **puesto como dato** + el **departamento** del usuario, **no** multiplicando roles.

- **Sobre el 8.º rol `auditor`** (mencionado como posible octavo): **no proviene del call sheet**
  — ningún puesto de set es "auditor". Es un rol **de sistema** (lectura/auditoría transversal,
  útil para cumplimiento/cliente). Si se adopta, es por necesidad de la plataforma, **no** porque
  la taxonomía organizativa lo exija. Recomendación: **opcional**; los 7 bastan para modelar todo
  el organigrama del call sheet. Si el cliente (estudio/Amazon-style) exige un perfil de
  solo-lectura para compliance, añadir `auditor` como permiso-de-solo-lectura; de lo contrario,
  no hace falta.

**Veredicto:** **7 roles bastan.** Mantenerlos gruesos es la decisión correcta.

> **Nota 2026-06-24:** la matriz **permiso×rol** vigente (qué permisos finos lleva cada rol, incl. el
> `auditor` solo-lectura) se alineó a la imagen del owner. La **fuente de verdad** de esa matriz es
> [AUTH-RBAC-PLAN.md §B.1.3](AUTH-RBAC-PLAN.md) (no se duplica aquí). Este documento solo mapea
> **puesto → rol**; los permisos por rol viven en el plan RBAC.

---

## 5. Recomendación de seed para `departments` y `positions`

### 5.A — `departments` (sembrar ~28 filas canónicas)
Sembrar los departamentos de §1 (consolidando las notas de cruce). Filas sugeridas (slug interno
opcional; `active=true`):

Dirección · Asistente de Dirección · Producción · Oficina de Producción · Producción Ejecutiva ·
Escritores · Cámara · Eléctricos · Grips · Rigging · Sonido · Video/VTR · Maquillaje y Peinados ·
Vestuario · Arte · Decoración · Construcción · Utilería · Locaciones · Transportación ·
Picture Cars · Efectos Especiales · Casting · Extras · Stunts · Música · VFX · Post Producción ·
Contabilidad · Catering · Salud y Seguridad · Sustentabilidad · Seguridad · A.N.D.A.

- **Reusar** las 8 filas que ya existen y mapean (`Production`, `Art`, `Construction`, `Wardrobe`,
  `Transportation`, `Locations`, `Accounting`, `BTS/Photo`) — `firstOrCreate` por nombre, no duplicar.
- **Consolidar** `Accounting` + `Fiscal` → un depto "Contabilidad" (con puesto "Contador Fiscal").
- **Resolver** la colisión `Greens` (jardinería) vs "Sustentabilidad/Greener": son distintos;
  decidir si `Greens` se pliega bajo Arte/Construcción y se crea "Sustentabilidad" aparte.
- **Revisar** `Travel & Living`: en el call sheet "Viajes" vive en Oficina de Producción; decidir
  si se conserva como depto o se absorbe.

### 5.B — `positions` (sembrar desde §2; los PUESTOS son DATOS, no roles)
Sembrar los puestos canónicos de §2 con `department_id` FK al depto correspondiente
(`firstOrCreate` por `(name, department_id)`). Normas:
- **Colapsar sufijos numéricos** ("Eléctrico 1..4" → "Eléctrico"; "Carpintero ×N" → "Carpintero";
  cada chofer "Van X" → "Chofer" o se modela como recurso de Transporte, no como puesto distinto).
- **Marcar jerarquía** con un flag/columna opcional (`is_lead` / `tier`) reutilizando las marcas
  **[HOD]** / **[2]** de §2 — esto alimenta `production_user.is_lead`
  ([AUTH-RBAC-PLAN.md](AUTH-RBAC-PLAN.md) B.2.3).
- **Descartar** los placeholders `adm` (id 1) y `developer` (id 2); **corregir** `Construction Larbor`;
  **deduplicar** `Greens Labor` (ids 4 y 16).
- **Conservar** los puestos de Construction/Welding que no salen en este call sheet (catálogo válido).

### 5.C — Nota clave: PUESTOS ≠ ROLES
- Los ~150 puestos se siembran como **filas de datos** en `positions` (catálogo organizacional).
- El **permiso** lo da el **rol** (`production_user.role` + roles spatie), **no** el puesto.
- Relación: cada usuario en `production_user` lleva `department_id` + `position_id` (su puesto en
  esa producción) **y** `role` (su rol RBAC) + `is_lead` (¿HOD aquí?). Así un mismo "Gaffer"
  puede ser `hod` en una producción y `crew` en otra **sin** crear puestos ni roles nuevos.

---

## Apéndice — Conteos de extracción

- **Departamentos extraídos del call sheet:** ~32–35 (consolidables a ~28 canónicos para seed).
- **Departamentos en la BD hoy:** 12 — de los cuales 8 mapean, 2 colisionan/dudan, y **~24 faltan**.
- **Puestos (títulos) en el call sheet:** ~200 brutos → **~150 canónicos** tras deduplicar.
- **Puestos en la BD hoy:** 18 — 16 de Construction/Greens, 2 placeholders a descartar, 1 typo;
  **0 puestos** de los demás departamentos → catálogo a reconstruir.
- **Roles necesarios:** **7** (los ya diseñados). El call sheet **no** exige roles nuevos;
  `auditor` (si se adopta) es de plataforma, no del organigrama.
- **Estatus/nomenclatura** (O/C, D/C, N/A, N/C, S/D, Per Loc/Transpo/ALOSA, Sume/Zapa/Casa):
  **no son roles** — futuro módulo de Call Sheets, fuera de alcance RBAC.

---

## 6. Huecos detectados / alias pendientes (del backfill 2026-06-24)

> Anotación surgida al correr `BackfillUserPositionsSeeder` (título→departamento/puesto) sobre los datos reales.
> **Resultado inicial:** 70/88 mapeados; 18 sin match. **Tras la resolución del paso (b) (abajo): 75/88**
> (+5 usuarios salieron de NULL). Esto **no reestructura** la taxonomía de arriba; solo resuelve los títulos
> planos que el matcher no podía atar al catálogo, agregando puestos faltantes y aliases — **sin inventar
> vínculos dudosos** (los ambiguos/no-crew se dejan NULL para decisión del owner).

### 6.A — Resueltos (puestos agregados al catálogo global + alias) — `firstOrCreate`, idempotente

Cuatro huecos reales del catálogo se cerraron sembrando puestos GLOBALES nuevos (`production_id = NULL`,
`is_hod = false`) en `OrgCatalogSeeder.php`, con su alias correspondiente en `BackfillUserPositionsSeeder.php`:

| Título legacy | Puesto agregado | Departamento | Nota |
|---|---|---|---|
| `Scouter` (x2) | **Scouter** | Locaciones | Location scout; no había equivalente. |
| `Bodeguero Decoración` | **Bodeguero** | Decoración | Distinto del `Bodeguero` de Construcción y del `Bodeguero Props` de Utilería. |
| `Coordinacion Utileria` | **Coordinador de Utilería** | Utilería | El depto no tenía puesto coordinador. |
| `Sastre Vestuario` | **Sastre** | Vestuario | Sastre (tailor), distinto de `Jefa de Sastrería` (jefe) y `Costurero` (costura). |

> **Nota técnica (colisión de nombre):** "Bodeguero" ahora existe bajo **dos** departamentos. El matcher
> resolvía por nombre normalizado (colisión → ganaba el último). Se extendió el alias map para aceptar la
> forma `"Puesto @ Departamento"` y se construyó un índice `nombre|departamento`; los aliases ambiguos se
> escriben así (`'bodeguero construccion' => 'Bodeguero @ Construcción'`, `'bodeguero decoracion' => 'Bodeguero @ Decoración'`).

### 6.B — Diferidos al owner (se dejan NULL — decisión de campo, no se inventó puesto)

13 usuarios siguen NULL. Recomendación una-línea por título (conservador gana):

| Título legacy | Recomendación |
|---|---|
| `Cast` (x2) | **Descartar como puesto de crew.** Son actores/elenco; no pertenecen al catálogo de crew (modelar aparte si se necesita). |
| `COVID TEST MANAGER` | **Descartar.** Artefacto legacy del protocolo COVID; no agregar al catálogo. |
| `Arte` (x2) | **Pedir puesto al owner.** Es nombre de DEPARTAMENTO, no de puesto. Probable que sea genérico de "personal de Arte"; sin puesto canónico claro. |
| `Sonido` | **Pedir puesto al owner.** Nombre de departamento. Si fuese el HOD sería "Sonido Directo", pero no hay evidencia → no asumir. |
| `Supervisor` | **Pedir depto al owner.** Genérico sin departamento; existen ~6 "Supervisor de X" distintos en el catálogo. Ambiguo. |
| `Segundo Asistente` | **Pedir contexto al owner.** Ambiguo: 2nd AD (Dirección) vs 2ndo AC (Cámara) vs 2º asistente de otro depto. |
| `Ambientador Vestuario` | **Pedir al owner.** Señales en conflicto: "Ambientador" sugiere Decoración/Set Dressing pero "Vestuario" es otro depto; no hay puesto "Ambientador" en el catálogo. |
| `Secretario Produccion` | **Pedir al owner.** No hay "Secretario" en el catálogo; podría mapear a "Asst. de Oficina de Producción" o "Recepción de Oficina", pero no es 1:1 → no asumir. |
| `Oficina Transpo` | **Pedir al owner.** Genérico de "oficina de Transportación"; no es un puesto específico del catálogo (≠ "Coordinador de Transporte"). |
| `Peon` | **Pedir al owner.** Mano de obra eventual; candidato a "Apoyo Eventual de Construcción/Utilería" pero sin depto claro → no asumir. |
| `Limpieza` | **Pedir al owner.** Existe "Apoyo de Limpieza" bajo Locaciones, pero "Limpieza" a secas es ambiguo (servicios generales vs Locaciones) → diferir. |

> Re-correr es seguro: `OrgCatalogSeeder` (firstOrCreate) y luego `BackfillUserPositionsSeeder` (UPDATE idempotente
> de los 2 FKs en `production_user`). Subir más allá de 75/88 requiere decisiones de campo del owner sobre los 13 de §6.B.

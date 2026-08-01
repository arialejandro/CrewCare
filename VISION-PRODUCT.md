# VISION-PRODUCT.md — Visión de producto (estrategia)

> **Naturaleza de este documento:** registra la **dirección ESTRATÉGICA de producto**
> decidida por el owner, con base en su experiencia de campo en sets LATAM. Es estrategia
> de **producto y mercado**, NO el roadmap técnico de saneamiento. Para el plan por fases y
> la ejecución técnica ver [ROADMAP.md](ROADMAP.md) y [RECIPE.md](RECIPE.md); aquí se decide
> **qué se vende, en qué orden y por qué**. Decisión fijada: **2026-06-24**.

---

## Visión

CrewCare evoluciona hacia una **plataforma de administración / coordinación de producción**
para cine y TV en LATAM — estilo **Scenechronize, pero más accesible y aterrizado a LATAM**.
Funciones objetivo:
- Contratos y **firmas digitales**.
- Formatos de contratación.
- Calendarios y **recordatorios de pago**.
- Manejo de **guiones / call sheets / sides**.

El producto se construye para resolver el dolor real de **Coordinación de Producción**, que
es quien define qué se necesita primero.

---

## Posicionamiento: administración como host, H&S como capa embebida

Los **módulos de Salud y Seguridad (H&S) actuales son el NÚCLEO técnico**, pero van
**EMBEBIDOS como una capa siempre presente** dentro del producto: reportes de seguridad,
trazabilidad médica y alertamiento, **montados sobre los datos que el producto ya captura**
para la administración de la producción. Así el H&S **no se percibe como un gasto extra**.

Estrategia tipo **"caballo de Troya"**: se **vende lo que la producción quiere**
(administración/coordinación) y se **entrega además el H&S** como capa incluida.

---

## Por qué NO liderar con H&S (lectura de mercado LATAM)

Corrige la suposición previa de "liderar con H&S". En LATAM el **H&S NO se percibe como
indispensable** — muchas producciones operan sin un protocolo real — por lo que **no es la
cuña de venta**. La cuña es la **necesidad administrativa / de coordinación de producción**.
El H&S llega "de regalo", incluido en el producto que la producción sí estaba buscando.

---

## Moat / ventaja del owner

La ventaja competitiva es el **conocimiento de campo del owner**: experiencia directa en los
departamentos de **dirección, producción, locaciones y H&S**, y su **relación directa con
Coordinación de Producción**. Esa cercanía define **qué ofertar y en qué orden**, algo que un
competidor sin pisar set no puede replicar.

---

## Método de construcción (incremental, NO rewrite)

- **Método FIRME:** **reconstrucción incremental tipo estrangulamiento** sobre la base nueva
  ya montada (RBAC / producciones / departamentos). **NO reescritura desde cero.**
- El owner quiere **ENTENDER su propio código** y poder **adaptarlo a necesidades inmediatas
  de set**; por eso **se explica el código a medida que se reconstruye**, avanzando **por
  verticales**.
- Principio: **"que lo que funciona, funcione mejor"** + mejorar lo que no se logró antes.

---

## Modelo de negocio

Construir un **negocio vía CrewCare** que genere ingresos suficientes para **contratar
personal técnico** que mantenga la app **operativa y escalable** en el tiempo.

---

## Cautelas legales y de alcance (registradas honestamente)

1. **Contratos / firmas / pagos tienen peso legal-fiscal POR PAÍS** (México: CFDI/SAT, IMSS,
   NOM-151 / firma avanzada). **Empezar por gestión documental + ruteo + registro +
   recordatorios** (bajo riesgo); dejar la **firma legal-grade para una etapa con proveedor
   especializado**.
2. **Recordatorios y trazabilidad de pago = seguro.** **MOVER dinero = NO** por ahora;
   integrar después con un proveedor de pagos.
3. **Disciplina de alcance:** elegir **UN solo flujo** — el que más le duela a Coordinación —
   y **poseerlo de punta a punta** antes de pasar al siguiente.
4. **H&S embebido pero first-class en datos:** la integración no debe **diluir la trazabilidad
   médica / de seguridad**; los datos de H&S se tratan como ciudadanos de primera clase.

---

## 🅿️ Nota de scheduling: rediseño del dashboard = entregable FINAL (2026-06-24)

> **Decisión del owner:** el **rediseño visual del dashboard del Home** (cards/iconografía, replanteo de qué métricas
> muestra, y eliminar la gráfica "Tendencia Semanal de Reportes Inseguros") se hace al **FINAL del proyecto**, como
> **entregable de pulido final** — **NO ahora**. Razón de dependencia: las métricas objetivo de
> **producción/rodaje** (días de rodaje, hojas filmadas, call sheets, calendario) **dependen de crear primero sus
> tablas** (incl. `productions`); hoy no existen como datos (el "Días de rodaje" actual es un literal hardcodeado y
> "Días de producción" es un proxy). La mitad **H&S** del dashboard sí es cableable con datos reales hoy, pero el
> rediseño se aborda **completo y al cierre**, no por mitades. Detalle de contexto y el pendiente cercano del fix de
> iconos en [PROGRESS.md](PROGRESS.md) y [RECIPE.md](RECIPE.md).

---

## Cómo valida la fundación ya construida

La fundación ya montada —**multi-producción + roles flexibles por producción + catálogo de
departamentos/puestos**— **ES el backbone multi-tenant que este producto necesita**: cada
producción es su propio espacio / crew / contratos. **Nada se construyó de más:** lo ya hecho
es exactamente la base sobre la que se levantan los verticales de administración con H&S
embebido. Detalle técnico de esa fundación en [PROGRESS.md](PROGRESS.md) y
[AUTH-RBAC-PLAN.md](AUTH-RBAC-PLAN.md).

# Señalética (SVG fuente) — pictogramas del Mapeo de riesgos

Deja aquí los SVG que quieras usar como iconos de recurso/peligro. **No se sirven
al navegador desde esta carpeta**: son la FUENTE. Yo los **incrusto** (inline) en
`resources/views/componentes/_rm-icon.blade.php`, normalizados a `currentColor`,
para que conserven el recoloreo (peligro = negro sobre amarillo, recurso = blanco),
la impresión y el funcionamiento offline (la CSP no deja CDNs).

## Cómo entregarlos

- **Un archivo por clave**, nombrado exactamente `<clave>.svg` (ver lista abajo).
- Formato ideal: `viewBox="0 0 24 24"`, **un solo color** (negro o `currentColor`),
  sin `width`/`height` fijos, sin `<image>`/fuentes/refs externas. Si vienen a color
  o con otro viewBox yo los normalizo.
- **Licencia:** solo libres — Wikimedia "ISO 7010" (dominio público), Material
  Symbols (Apache-2.0), Tabler (MIT), Lucide (ISC). **Evita los SVG oficiales de
  ISO** (son de pago / con copyright).

## Claves (27)

Recursos (símbolo blanco):
`extintor`, `salida_emergencia`, `botiquin`, `punto_alarma`, `manguera_hidrante`,
`tablero_electrico`, `punto_reunion`, `acceso_ambulancia`

Peligros (símbolo negro sobre amarillo):
`haz-warn`, `haz-bolt`, `haz-flame`, `haz-fall`, `haz-fallobj`, `haz-suspended`,
`haz-collapse`, `haz-slip`, `haz-temp`, `haz-water`, `haz-vehicle`, `haz-people`,
`haz-bio`, `haz-toxic`, `haz-animal`, `haz-drone`, `haz-firearm`, `haz-explosive`,
`haz-exit`

No hace falta entregarlos todos de golpe: los que dejes, los cambio; los que no,
se quedan con el glifo actual.

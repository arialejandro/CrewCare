# Retiro de `/comcrud` — 2026-07-19

## Qué se retiró
- `resources/views/admin/comcrud.blade.php` → movida aquí (esta carpeta).
- `routes/web.php` → eliminada `Route::get('/comcrud', …)->name('comcrud');`
- `app/Http/Controllers/CrewListController.php` → eliminado el método `comcrud()`.

## Por qué
La pantalla era **huérfana y redundante**, y sus botones llevaban tiempo rotos.

1. **Sin enlaces.** No aparecía en el sidebar ni en ninguna vista. Solo se alcanzaba tecleando la URL.
2. **Botones rotos (404).** Sus formularios posteaban a `url('/putga/'.$id)` y `url('/putgg/'.$id)`, que **no son URIs sino NOMBRES de ruta**. Las URIs reales son `/putadm/{id}` y `/putsup/{id}`. Nadie lo notó porque nadie llegaba a la pantalla.
3. **Duplicaba a `usuarioscrud`, que lo hace mejor.** Consulta idéntica (`activo=1` + `User::applyDepartmentScope` + `paginate(50)`); `usuarioscrud` muestra más columnas (F.Nac., Sexo), incluye el mismo botón "Exportar Crewlist" (`/nophoto`) y sobre todo usa el parcial `componentes/_group-toggles.blade.php`, que **sí** invoca `route()` correctamente y funciona.
4. Lo único exclusivo era la columna "Grupo" (badges Espc/Cord/Glob), residuo de las colas A/B/G de la app COVID. Su caso `Espc` (`daytest = 2`) ya se había retirado en el PASO A del mismo día, y el resto solo distinguía a **1 usuario** (`daytest = 3` → "Cord").

## Qué NO se tocó
`usuarioscrud`, `medicocrud`, el parcial `_group-toggles`, las rutas `putga`/`putgg` (`/putadm`, `/putsup`) ni el export `/nophoto`. La columna `users.daytest` sigue viva: 6 usuarios en 0 y 34 en 1, leídos por `_group-toggles` y proyectados por `SearchController`.

## Reversión
La app es repositorio git: `git revert` del commit correspondiente. Si se prefiere a mano, basta con devolver el `.blade.php` a `resources/views/admin/`, restaurar la línea de ruta y el método del controlador — **y de paso arreglar los `url()` por `route()`**, o los botones volverán a dar 404.

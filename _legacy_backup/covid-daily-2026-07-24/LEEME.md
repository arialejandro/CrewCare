# Retiro del cuestionario COVID diario (2026-07-24)

Estas tres piezas eran el último resto vivo del cuestionario COVID diario. Se retiran al convertir
el módulo en EXPEDIENTE CLÍNICO, que se llena UNA VEZ por producción y no se re-pide cada mañana.

## Qué hacía cada una

- **`encuestasTask.php`** (`encuestas:task`, agendado en Kernel a las 04:00): ponía
  `encuestadiaria = 0` a TODOS los usuarios activos y después mandaba el correo "DAILY REPORT".
- **`CrewDailyReminder.php`** (`crew:daily-reminder`, manual): reenviaba el MISMO correo a los que
  seguían pendientes.
- **`ReminderMailController.php` + `ReminderEmail.php`** (`GET /reminder-mail`, gateada con
  `admin`): **tercera copia de lo mismo**. Encolaba el MISMO correo a todos los activos. Apareció
  al rastrear quién más usaba la vista; sin retirarla, la ruta habría quedado apuntando a una
  vista inexistente.
- **`recordatorio.blade.php`**: la vista del correo, usada por las tres.

## Por qué no se "arregla" el correo, se retira

La vista NO es genérica ni reutilizable:

1. **Está rota desde siempre.** Usa `{{ $email }}` pero los dos comandos sólo pasan `nombre` →
   `ErrorException: Undefined variable: email`. El correo revienta en el PRIMER destinatario, así
   que en la práctica nunca salió. Lo que sí corría todas las noches era el reset del flag.
2. **Filtra una contraseña en claro:** el cuerpo dice literalmente `Contraseña: 123`.
3. **Está clavada a una producción** ("Pedro Páramo") y a un protocolo COVID de síntomas.
4. **Publica datos personales de terceros**: nombre, Gmail personal y celular del "Gerente COVID"
   y del "Coordinador COVID", más un contacto `covid@crewcare.tech`.

Cualquiera de los cuatro puntos bastaría para no volver a enviarla. Si algún día hace falta un
recordatorio, se escribe uno nuevo sobre el patrón vivo de la app (`Mail::send` con vista propia,
blindado con `\Throwable`), no se revive éste.

## Qué SIGUE funcionando

La reapertura MANUAL del cuestionario: el toggle "Activar encuesta" en `usuarioscrud`
(`CrewStatusController@activarencuesta`, `POST /activarencuesta/{id}`), que pone `encuestadiaria = 0`
para UN usuario concreto y por decisión de una persona.

## Reversión

`git mv` de vuelta a `app/Console/Commands/` y `resources/views/correos/`, y reponer en
`app/Console/Kernel.php` la línea `$schedule->command('encuestas:task')->dailyAt('04:00');` y las
dos entradas de `$commands`.

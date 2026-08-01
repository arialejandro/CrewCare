@extends('layouts.app')

@section('content')

{{-- AVISO DE PRIVACIDAD — paso 0 antes de recabar datos de salud (2026-07-24 · PIEZA 3).

     El cuerpo de abajo es el TEXTO LEGAL DEFINITIVO (KeyCare S.A.S. de C.V., LFPDPPP), copiado
     literal y va en español: un aviso de privacidad mexicano es autoritativo en ese idioma, por
     eso NO pasa por las claves i18n como el resto de la vista. Los rótulos de la UI (título,
     casilla, botones) sí siguen traducidos.

     DEFINITIVO Y VIGENTE (2026-07-24): se definió el correo de derechos ARCO del apartado IX
     (contacto@crewcare.mx) y PrivacyNotice::VERSION quedó sin el sufijo «-borrador», por lo que
     la advertencia de borrador ya no se pinta (sale de PrivacyNotice::esBorrador(), que mira el
     sufijo de la versión — no está escrita a mano). Publicar una versión futura = reescribir el
     cuerpo + cambiar VERSION: eso vuelve a pedir el consentimiento a todos y las aceptaciones
     anteriores quedan intactas, probando qué aceptó cada quien. --}}

@include('componentes._form-kit')

<div class="container py-4" style="max-width: 820px;">

    <div class="d-flex align-items-center gap-3 mb-3">
        <span class="cc-form-ico">
            @include('componentes._icon', ['name' => 'shield-check', 'class' => 'cc-ico-20', 'label' => null])
        </span>
        <div>
            <h1 class="h4 fw-bold mb-0">{{ __('health.privacy_title') }}</h1>
            <div class="cc-muted small">{{ __('health.privacy_version', ['version' => $version]) }}</div>
        </div>
    </div>

    @include('componentes._form-feedback')

    @if($esBorrador)
        <div class="alert alert-warning d-flex align-items-start" style="gap:8px">
            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'w-4 h-4'])
            <span style="font-size:.88rem"><strong>{{ __('health.privacy_draft_title') }}</strong>
                {{ __('health.privacy_draft_body') }}</span>
        </div>
    @endif

    <div class="cc-form-card">
        <div class="cc-form-card__body" style="line-height:1.65">

            <h2 class="h5 fw-bold text-center mb-3">Aviso de Privacidad Integral</h2>
            <p>El presente Aviso de Privacidad se emite en cumplimiento a lo dispuesto por la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP) y su normatividad aplicable.</p>

            <h2 class="h6 fw-bold mt-4">I. Identidad y domicilio del responsable</h2>
            <p>KeyCare S.A.S. de C.V., representada legalmente por Ari Alejandro Rómulo Guerrero (en adelante "El Responsable"), con domicilio para oír y recibir notificaciones en Av. Valle de Bravo 129, Loc. Santa María del Monte, Zinacantepec, Estado de México, es la entidad responsable del tratamiento, uso y protección de sus datos personales, mismos que serán recabados y gestionados a través de la plataforma tecnológica CrewCare.</p>

            <h2 class="h6 fw-bold mt-4">II. Datos personales que serán sometidos a tratamiento</h2>
            <p>Para cumplir con las finalidades señaladas en el presente aviso, recabaremos las siguientes categorías de datos personales directamente a través de los cuestionarios y formularios de perfil en la plataforma CrewCare, o bien, a través del personal médico y de seguridad durante la producción:</p>
            <p><strong>Datos de Identificación y Contacto:</strong> Nombre completo, RFC, CURP, teléfono, correo electrónico, puesto, departamento, empresa contratante y fecha de nacimiento.</p>
            <p><strong>Datos de Terceros (Contacto de Emergencia):</strong> Nombre, parentesco y teléfono. <em>Nota:</em> Al proporcionar esta información, el titular garantiza que ha informado al tercero sobre la entrega de sus datos y ha obtenido su consentimiento para ser contactado en caso de emergencia.</p>
            <p><strong>Datos de Terceros (Testigos de un accidente):</strong> Nombre, teléfono y declaración de las personas que presenciaron un accidente de trabajo, recabados por el personal de seguridad al documentar el evento con la finalidad de acreditar las circunstancias en que ocurrió. Estos datos se tratan exclusivamente para la investigación del accidente y para los avisos que deban rendirse ante la autoridad.</p>
            <p><strong>Datos de evidencia de firma y consentimiento:</strong> Dirección IP, navegador, fecha y hora en que usted firma un documento o acepta el presente aviso. Se recaban con la única finalidad de acreditar la autenticidad de esos actos y la integridad de los documentos sellados, y no se utilizan para ningún otro fin.</p>
            <p><strong>Registros fotográficos y de geolocalización:</strong></p>
            <ul>
                <li><em>Fotografía de rostro:</em> Recabada con la finalidad de emitir la credencial de identificación de la producción.</li>
                <li><em>Fotografías de evidencia en reportes de seguridad:</em> Capturadas para documentar incidentes laborales, actos o condiciones inseguras. En dichas imágenes pueden aparecer terceros identificables que se encuentren en el entorno.</li>
                <li><em>Fotografía en gran angular de la reunión de seguridad (safety meeting):</em> Capturada para dejar evidencia documental de la realización del protocolo y la asistencia del personal.</li>
                <li><em>Coordenadas GPS:</em> Recabadas con la finalidad de registrar la ubicación exacta del lugar donde se levanta un reporte de seguridad, incidente o atención médica en la locación. La geolocalización se asocia al reporte, no a la persona, y no se realiza seguimiento continuo de su ubicación.</li>
            </ul>

            <h2 class="h6 fw-bold mt-4">III. Datos personales sensibles</h2>
            <p>Le informamos que, para cumplir con las finalidades previstas en este aviso, serán recabados y tratados datos personales sensibles, los cuales requieren especial protección. Nos comprometemos a que los mismos serán tratados bajo las más estrictas medidas de seguridad que garanticen su confidencialidad:</p>
            <p><strong>Datos Clínicos Base y Antropométricos:</strong> Tipo de sangre, peso, talla e Índice de Masa Corporal (IMC).</p>
            <p><strong>Antecedentes Patológicos y No Patológicos:</strong> Hospitalizaciones, cirugías, enfermedades diagnosticadas, alergias, lesiones traumáticas, consumo de tabaco, alcohol y toxicomanías.</p>
            <p><strong>Esquema de Vacunación:</strong> Historial de vacunas (COVID-19, influenza, tétanos, neumococo, hepatitis B) y fechas de aplicación.</p>
            <p><strong>Datos Ginecológicos:</strong> Ritmo menstrual y estado de embarazo.</p>
            <p><strong>Antecedentes Heredo-Familiares:</strong> Estado vital y antecedentes de salud (diabetes, hipertensión, cardiopatías, nefropatías, neoplasias) del padre y de la madre. <em>Nota:</em> Estos son datos personales sensibles de terceros. Se recaban del propio titular por ser indispensables para valorar su riesgo clínico y prestarle atención médica segura; se tratan únicamente con esa finalidad, no identifican al tercero más allá de su parentesco y no se transfieren de manera individualizada. Al proporcionarlos, el titular manifiesta que cuenta con la información necesaria para hacerlo.</p>
            <p><strong>Datos de Atención Médica en Set:</strong> Fecha y hora de consulta, diagnósticos, medicamentos administrados (nombre, dosis, presentación, cantidad), manejo clínico (valoración, reposo, retiro, curación, referencia hospitalaria), observaciones e indicaciones, así como vinculación a un accidente laboral cuando aplique.</p>
            <p><strong>Datos de Accidentes Laborales y Seguridad:</strong> Tipo de lesión, parte del cuerpo afectada, uso de equipo de protección personal, nivel de atención médica, días de ausencia o restricción, mecanismo de lesión, análisis de causa raíz, y firma de aceptación de la narrativa por parte del lesionado.</p>

            <h2 class="h6 fw-bold mt-4">IV. Finalidades del tratamiento</h2>
            <p>Todas las finalidades que se enuncian a continuación son necesarias para la relación jurídica y laboral y para el cumplimiento de las obligaciones en materia de seguridad y salud en el trabajo:</p>
            <ol>
                <li>Prestar atención médica en set y contar con los antecedentes que la hacen segura (principalmente alergias, para no administrar un medicamento contraindicado).</li>
                <li>Cumplir obligaciones de seguridad e higiene en el trabajo conforme a la normatividad mexicana (NOM de la STPS) y, cuando el proyecto lo requiere, a marcos internacionales (OSHA, CSATF).</li>
                <li>Documentar e investigar accidentes de trabajo, incluida la información necesaria para dar aviso a IMSS y STPS.</li>
                <li>Emitir credenciales de identificación para el personal.</li>
                <li>Acreditar la habilitación profesional del personal médico.</li>
                <li><strong>Gestión de reportes de seguridad:</strong> Notificar actos inseguros con fines estrictamente de corrección y prevención. El sistema no tiene carácter sancionatorio; por ello, en los reportes generales no se publica el nombre del involucrado, identificando únicamente al departamento al que pertenece. El nombre del titular viaja de manera exclusiva y confidencial en un aviso dirigido únicamente a su jefe inmediato, con el objetivo de que este pueda aplicar las medidas preventivas y correctivas necesarias en el set.</li>
                <li>Elaborar el reporte final de seguridad de la producción, con estadística e indicadores de siniestralidad, capacitación e inspecciones, que la producción debe rendir a su cliente y conservar como constancia de cumplimiento.</li>
                <li>Detectar oportunamente patrones de salud en el rodaje —por ejemplo, un brote gastrointestinal o respiratorio a partir de la frecuencia de diagnósticos o medicamentos— con el fin de prevenir un daño mayor a la salud del personal.</li>
            </ol>
            <p><strong>Mejora de la plataforma.</strong> Cuando el sistema realice análisis del uso de la plataforma con el fin de mejorarla, dicho tratamiento se llevará a cabo exclusivamente sobre información estadística, agregada y disociada, que no permite identificarle ni reconstruir su identidad. Esta previsión no comprende los registros de evidencia de firma y consentimiento señalados en el apartado II, que sí le identifican y se tratan únicamente para la finalidad ahí expresada.</p>

            <h2 class="h6 fw-bold mt-4">V. Transferencia de datos personales</h2>
            <p>Sus datos personales son almacenados de forma segura en las bases de datos de la plataforma CrewCare. Se realizarán transferencias de información a terceros en los siguientes casos:</p>
            <ol>
                <li><strong>Al Estudio (cliente que financia el proyecto):</strong> Se entrega de manera semanal una bitácora de atención médica que incluye el nombre del titular, fecha de atención, diagnóstico y, en su caso, la vinculación con un accidente de trabajo. Debido a que el Estudio suele ser una entidad extranjera, esta acción puede constituir una transferencia internacional de datos personales y sensibles, indispensable para la rendición de cuentas del proyecto.</li>
                <li><strong>A la casa productora (patrón o contratante) y a los proveedores de seguros:</strong> Exclusivamente para fines de cobertura de pólizas, gestión de incapacidades y auditorías de seguridad en locación.</li>
                <li><strong>A instituciones médicas, cuerpos de rescate, autoridades de protección civil, IMSS y STPS:</strong> Cuando la transferencia sea indispensable para la atención médica, la prevención de un daño a la salud, o por requerimientos normativos y legales en caso de accidentes laborales.</li>
            </ol>
            <p>Los receptores de sus datos asumen las mismas obligaciones de protección que corresponden al Responsable. Fuera de los supuestos anteriores, sus datos no se transfieren, comparten ni comercializan con terceros.</p>

            <h2 class="h6 fw-bold mt-4">VI. Personas que no acceden a la plataforma</h2>
            <p>Determinadas personas participan en la producción sin llegar a registrarse en la plataforma: extras, visitantes, proveedores y personal de un solo día. Cuando una de ellas requiere atención médica en set, el personal médico recaba y registra los datos indispensables para prestarla —identificación, motivo de consulta, diagnóstico y manejo clínico— y le hace de su conocimiento el presente aviso en su versión simplificada, de forma verbal o impresa, dejando constancia de ello en el registro de la atención.</p>
            <p>Esa información se trata únicamente para prestar la atención, documentarla y, en su caso, dar los avisos que la normatividad laboral exige. Su incorporación a los reportes agregados de la producción se realiza de forma disociada.</p>

            <h2 class="h6 fw-bold mt-4">VII. Menores de edad</h2>
            <p>Cuando participe talento infantil o cualquier persona menor de edad, sus datos personales y datos personales sensibles se recaban y tratan por conducto de quien ejerce la patria potestad o la tutela, quien otorga el consentimiento correspondiente y a quien se dirige el presente aviso. El cuestionario de salud es requisitado por dicha persona, y la atención médica en set se presta en su presencia, salvo caso de urgencia en que resulte materialmente imposible, supuesto en el que se le informará de inmediato.</p>

            <h2 class="h6 fw-bold mt-4">VIII. Plazo de conservación</h2>
            <p>Sus datos se conservan durante el tiempo necesario para cumplir las finalidades señaladas y las obligaciones legales derivadas de ellas, conforme a los siguientes plazos:</p>
            <ul>
                <li><strong>Expediente clínico y registros de atención médica:</strong> mínimo cinco años contados a partir de la fecha del último acto médico, conforme a la Norma Oficial Mexicana del expediente clínico.</li>
                <li><strong>Registros de accidentes de trabajo, capacitación e inspecciones de seguridad:</strong> tres años a partir de su generación, conforme a los estándares de la industria y a la normatividad laboral aplicable.</li>
                <li><strong>Registros de evidencia de firma y de aceptación del presente aviso:</strong> por todo el tiempo que subsista el tratamiento, como constancia de los actos que acreditan.</li>
            </ul>
            <p>Concluidos los plazos anteriores, y una vez agotadas las obligaciones legales de conservación, sus datos son suprimidos o disociados de forma irreversible, conservándose únicamente información estadística que no permite identificarle.</p>

            <h2 class="h6 fw-bold mt-4">IX. Mecanismos para el ejercicio de derechos ARCO</h2>
            <p>Usted tiene derecho a conocer qué datos personales tenemos de usted (Acceso), solicitar la corrección de su información (Rectificación), pedir su eliminación (Cancelación) u oponerse a su uso (Oposición).</p>
            <p><strong>Mecanismo de Acceso.</strong> Al completar su registro y el cuestionario médico, el sistema no permite la descarga directa desde el perfil; en su lugar, envía automáticamente el expediente clínico completo en formato PDF al correo electrónico proporcionado. Este documento cuenta con un sello de integridad y es verificable. En caso de requerirlo nuevamente, el titular podrá solicitar el reenvío de este documento.</p>
            <p><strong>Mecanismo de Rectificación.</strong> Por la naturaleza médico-legal y probatoria de los datos recabados, el expediente clínico es inmutable una vez presentado. No es posible modificar la información directamente en el sistema, a fin de garantizar el valor probatorio sobre qué se declaró y en qué fecha. Para solicitar una corrección, el titular deberá dirigir su petición al médico de la producción, quien valorará la solicitud. De proceder la rectificación, el médico asentará un anexo que incluirá la fecha, un sello de integridad, así como su nombre y cédula profesional. La declaración original nunca se suprime ni se sustituye; el anexo correspondiente se conservará y se mostrará siempre en conjunto con ella. Si la solicitud no procede a juicio del médico tratante, se le comunicará el motivo de la determinación.</p>
            <p>Sus datos de identificación y contacto no forman parte del expediente clínico sellado, pero tampoco son de autoservicio: desde su perfil usted puede modificar únicamente su fotografía y su contraseña. Para corregir su nombre, correo electrónico, teléfono, fecha de nacimiento, sexo, puesto o departamento, deberá solicitarlo al personal administrativo de la producción o por los medios señalados en este apartado.</p>
            <p><strong>Mecanismo de Cancelación y Oposición.</strong> No procederá la cancelación de sus datos médicos y de seguridad mientras exista una relación laboral o contrato vigente con la producción, dado que esta información es de carácter vital para su atención médica de emergencia y para el cumplimiento de obligaciones en materia de salud y seguridad. Concluida la relación, la cancelación procederá una vez transcurridos los plazos de conservación señalados en el apartado VIII.</p>
            <p><strong>Presentación de solicitudes.</strong> Para ejercer cualquier derecho ARCO o solicitar un nuevo envío de su expediente, deberá enviar una solicitud por escrito al correo electrónico <a href="mailto:contacto@crewcare.mx">contacto@crewcare.mx</a>, o presentarla en el domicilio señalado en el apartado I. Este mecanismo está disponible también para quienes ya no forman parte de la producción o nunca contaron con una cuenta en la plataforma.</p>

            <h2 class="h6 fw-bold mt-4">X. Verificación pública de documentos</h2>
            <p>La plataforma permite que un tercero —por ejemplo, una auditoría o una autoridad— compruebe mediante un código QR que un documento emitido por el sistema existe, la fecha en que fue sellado y si ha sido alterado. Esta consulta es accesible sin autenticación y <strong>no expone ningún dato personal</strong>: únicamente muestra el tipo genérico de documento, su folio, su identificador, la fecha de sellado y el resultado de la verificación de integridad.</p>

            <h2 class="h6 fw-bold mt-4">XI. Modificaciones al aviso de privacidad</h2>
            <p>El presente aviso puede sufrir modificaciones, cambios o actualizaciones derivadas de nuevos requerimientos legales, de nuestras propias necesidades por los servicios que ofrecemos, o por mejoras en la plataforma CrewCare. Le mantendremos informado a través de notificaciones dentro de la aplicación o vía correo electrónico. Al publicarse una versión nueva, la plataforma solicita nuevamente su aceptación antes de permitirle continuar, y conserva el registro de las aceptaciones anteriores.</p>

            <hr class="my-4">

            <h2 class="h6 fw-bold mt-4">Consentimiento expreso para el tratamiento de datos sensibles</h2>
            <p><em>(Al tratarse de datos de salud e historiales clínicos, la normatividad exige la firma autógrafa, electrónica o mecanismo de autenticación del titular)</em></p>
            <p>Otorgo mi consentimiento expreso para que mis datos personales y datos personales sensibles (clínicos, médicos y de seguridad) sean tratados y transferidos conforme a los términos y condiciones del presente Aviso de Privacidad.</p>
            <p><strong>Modalidad electrónica (plataforma CrewCare).</strong> La aceptación se otorga desde su cuenta personal, previa autenticación con sus credenciales. El sistema conserva, como constancia del consentimiento: su identidad autenticada, la fecha y hora exactas, la versión del aviso aceptada, la dirección IP y el navegador desde el que se otorgó.</p>

        </div>
    </div>

    <form method="POST" action="{{ route('privacidad.aceptar') }}">
        @csrf

        {{-- La casilla NO viene premarcada, a propósito: un consentimiento otorgado por omisión
             no es EXPRESO, y expreso es justo lo que exige un dato personal sensible. --}}
        <label class="cc-check mb-3" style="min-height:52px">
            <input class="form-check-input @error('acepto') is-invalid @enderror" type="checkbox" name="acepto" value="1" required>
            <span class="form-check-label">{{ __('health.privacy_checkbox') }}</span>
        </label>

        <div class="d-flex flex-wrap justify-content-end gap-2">
            <a href="{{ url('/home') }}" class="btn btn-outline-secondary">{{ __('health.privacy_later') }}</a>
            <button type="submit" class="btn btn-primary cc-cta">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18', 'label' => null])
                {{ __('health.privacy_accept') }}
            </button>
        </div>
        <p class="cc-muted small mt-2 mb-0 text-end">{{ __('health.privacy_later_hint') }}</p>
    </form>

</div>
@endsection

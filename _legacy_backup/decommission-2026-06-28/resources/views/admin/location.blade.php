@extends('layouts.app')

@section('content')
    <style>
       @media print {
    /* Oculta elementos no deseados */
    .no-print { 
        display: none !important; 
    }
    
    /* Reset de márgenes */
    body {
        margin: 0;
        padding: 10mm 15mm; /* Márgenes estándar para PDF */
        font-size: 12pt;
        line-height: 1.4;
        background: white !important;
        color: black !important;
    }
    
    /* Evita que las tablas se corten */
    table {
        page-break-inside: avoid;
        break-inside: avoid;
        width: 100%;
    }
    
    /* Controla saltos de página */
    .page-break {
        page-break-before: always;
    }
    
    /* Asegura que los encabezados no queden solos */
    h1, h2, h3 {
        page-break-after: avoid;
    }
    
    /* Fuerza fondos blancos (útil si tienes modos oscuros) */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
        /* Estilos generales del header */
        .report-header {
            position: relative;
            width: 100%;
            min-height: 250px;
            background-size: cover;
            background-position: center;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            font-family: 'Poppins', sans-serif;
            font-weight: 300;
        }

        .report-header h1 {
            font-size: 3em;
            margin-bottom: 5px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.6);
           text-transform: uppercase;
            /* font-weight: bold;  Nombre de la locación en negrita */
            font-family: 'Poppins', sans-serif;
            position: relative; /* Para apilar el blur/transparencia */
        }

        .header-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%; /* Ajusta el ancho del overlay */
            height: 4.8em; /* Ajusta la altura según el contenido */
            padding: 10px;
            background-color: rgba(0, 0, 0, 0.1); /* Fondo negro con transparencia */
            backdrop-filter: blur(10px); /* efecto de desenfoque */
      -webkit-backdrop-filter: blur(10px); /* para Safari */
            z-index: 1; /* Asegura que esté detrás del texto principal */
        }

        .report-header h1 span {
            position: relative; /* Para estar encima del overlay */
            z-index: 2;
        }

        .report-header p {
            font-size: 0.9em;
            margin-bottom: 10px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.6);
            position: relative; /* Para estar encima del overlay */
            z-index: 2;
        }

        .header-info {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background-color: rgba(0, 0, 0, 0.5);
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            text-align: right;
            z-index: 2; /* Asegura que esté encima del overlay */
        }

        /* Estilos para las secciones y cards */
        .report-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
            font-family: 'Poppins', sans-serif;
        }

        .report-section h2 {
            font-size: 1.5em;
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .row {
            margin-left: -10px;
            margin-right: -10px;
        }

        .col-md-6, .col-md-12, .col-md-4, .mb-3 {
            padding-left: 10px;
            padding-right: 10px;
        }

        .card {
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 15px;
        }

        .card-body {
            padding: 15px;
        }

        .card-title {
            font-size: 1.1em;
            color: #555;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .card-text {
            font-size: 0.95em;
        }

        .details-text {
            font-style: italic;
            color: #777;
            display: block; /* Para que ocupe toda la línea si es necesario */
            margin-top: 5px; /* Espacio con el badge */
        }

        .yes-no-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: bold;
            margin-right: 5px;
        }

        .yes-badge {
            background-color: #4CAF50;
            color: white;
        }

        .no-badge {
            background-color: #f44336;
            color: white;
        }

        .emergency-item {
            display: inline-block;
            margin-right: 10px;
            padding: 5px 10px;
            border-radius: 5px;
            background-color: #e0f7fa;
            color: #00acc1;
            border: 1px solid #b2ebf2;
            font-size: 0.9em;
        }

        .additional-risks, .additional-considerations {
            white-space: pre-line;
        }

        .signature-info {
            margin-top: 20px;
            font-size: 0.9em;
            color: #555;
        }

        .list-point {
            margin-bottom: 5px;
        }
    </style>

<div class="main-rl">
		<div class="row head-rl">
			<div class="col-3 client-hm" align="center"> <img src="{{ URL::asset('img/redrum.png') }}" width="130rem"></div>
			<div class="col-6 title-hm"><span><strong>REPORTE LOCACIÓN</strong>&nbsp </span></div>
			<div class="col-3 cclogo-hm" align="center"> <img src="{{ URL::asset('img/logo-cc-report.svg') }}" width="130rem"></div>
		</div>
	</div>
    <hr>
    <div class="report-header" style="background-image: url('{{ $report->main_image_path ?? asset('images/default-location.jpg') }}');">
        <div class="header-overlay"></div>
        <h1><span>{{ strtoupper($report->name_loc) }}</span></h1>
        <p><span>
            Prep: {{ $report->date_prep ? \Carbon\Carbon::parse($report->date_prep)->format('d-m-Y') : 'N/A' }}  | 
            <strong>Shoot: {{ $report->date_shoot ? \Carbon\Carbon::parse($report->date_shoot)->format('d-m-Y') : 'N/A' }}</strong>  | 
            Wrap: {{ $report->date_wrap ? \Carbon\Carbon::parse($report->date_wrap)->format('d-m-Y') : 'N/A' }}
        </span></p>
        <div class="header-info">
            {{ $report->production_name }} |
            Llamado:
            @if ($report->loc_type == 0) Interior
            @elseif ($report->loc_type == 1) Exterior
            @elseif ($report->loc_type == 2) Int/Ext
            @endif
            
            
            @if ($report->shoot_time == 0) Diurno
            @elseif ($report->shoot_time == 1) Nocturno
            @elseif ($report->shoot_time == 2) Mixto
            @endif
        </div>
    </div>

    <div class="report-section">
        <h2>SECCIÓN 1 - PREGUNTAS PARA EL ADMINISTRADOR / PROPIETARIO DE LA LOCACIÓN</h2>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">1. ¿Informó a los administradores/propietarios de la locación sobre las actividades que realizará la compañía de producción?</h5>
                        <p class="card-text">
                            @if ($report->owner_1 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->owner_1_details) <span class="details-text">{{ $report->owner_1_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">2. ¿Los administradores/propietarios están al tanto de algún riesgo existente asociado a la locación?</h5>
                        <p class="card-text">
                            @if ($report->owner_2 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->owner_2_details) <span class="details-text">{{ $report->owner_2_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">3. ¿Se ha realizado una evaluación de riesgos en el pasado?</h5>
                        <p class="card-text">
                            @if ($report->owner_3 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->owner_3_details) <span class="details-text">{{ $report->owner_3_details }}</span> @endif
                        </p>
                        @if ($report->owner_3 == 1)
                            @if ($report->owner_3_1) <p class="card-text details-text">3.1 ¿Cuando se completó esta evaluación?: {{ $report->owner_3_1 }}</p> @endif
                            <p class="card-text">
                                3.2 ¿Está disponible para el uso de esta producción?:
                                @if ($report->owner_3_2 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                                @else <span class="yes-no-badge no-badge">No</span> @endif
                                @if ($report->owner_3_2_details) <span class="details-text">{{ $report->owner_3_2_details }}</span> @endif
                            </p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">4. ¿Hay informes de ingeniería y planos que describan puntos de anclaje, cargas de peso y problemas estructurales disponibles?</h5>
                        <p class="card-text">
                            @if ($report->owner_4 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->owner_4_details) <span class="details-text">{{ $report->owner_4_details }}</span> @endif
                        </p>
                        @if ($report->owner_4 == 1)
                            <p class="card-text">
                                4.1 Si es así: ¿Están disponibles para el uso de esta producción?:
                                @if ($report->owner_4_1 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                                @else <span class="yes-no-badge no-badge">No</span> @endif
                                @if ($report->owner_4_1_details) <span class="details-text">{{ $report->owner_4_1_details }}</span> @endif
                            </p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">5. Si la locación es una instalación operativa, ¿hay procedimientos específicos de emergencia o evacuación que la producción deba seguir?</h5>
                        <p class="card-text">
                            @if ($report->owner_5 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->owner_5_details) <span class="details-text">{{ $report->owner_5_details }}</span> @endif
                        </p>
                        @if ($report->owner_5 == 1 && $report->owner_5_1)
                            <p class="card-text details-text">5.1 Si es así ¿Cuando cuando estarán disponibles para la producción?: {{ $report->owner_5_1 }}</p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">6. ¿Hay algún material peligroso como pinturas con plomo, asbesto o moho?</h5>
                        <p class="card-text">
                            @if ($report->owner_6 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->owner_6_details) <span class="details-text">{{ $report->owner_6_details }}</span> @endif
                        </p>
                        @if ($report->owner_6 == 1)
                            <p class="card-text">
                                6.1 Si es así: ¿Todos los materiales peligrosos existentes están almacenados y/o asegurados correctamente?:
                                @if ($report->owner_6_1 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                                @else <span class="yes-no-badge no-badge">No</span> @endif
                                @if ($report->owner_6_1_details) <span class="details-text">{{ $report->owner_6_1_details }}</span> @endif
                            </p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">7. ¿La locación contiene materiales PCB (ej. transformadores eléctricos)?</h5>
                        <p class="card-text">
                            @if ($report->owner_7 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->owner_7_details) <span class="details-text">{{ $report->owner_7_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">8. ¿Se ha utilizado esta locación para un propósito que haya generado polvo o particulas en exceso?</h5>
                        <p class="card-text">
                            @if ($report->owner_8 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->owner_8_details) <span class="details-text">{{ $report->owner_8_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">9. Si la locación es una instalación operativa, ¿hay hojas de datos de seguridad en el archivo de la locación para todos los materiales peligrosos utilizados/almacenados en el sitio?</h5>
                        <p class="card-text">
                            @if ($report->owner_9 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->owner_9_details) <span class="details-text">{{ $report->owner_9_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">10. ¿La corriente alterna (A.C.) está conectada a tierra?</h5>
                        <p class="card-text">
                            @if ($report->owner_10 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->owner_10_details) <span class="details-text">{{ $report->owner_10_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">11. ¿Hay algún riesgo eléctrico potencial (cableado expuesto, cajas eléctricas, etc.) en la locación?</h5>
                        <p class="card-text">
                            @if ($report->owner_11 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->owner_11_details) <span class="details-text">{{ $report->owner_11_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">12. ¿Hay suficiente suministro eléctrico para la demanda requerida? (*Aplica sólo si la producción se conectara a la locación)</h5>
                        <p class="card-text">
                            @if ($report->owner_12 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->owner_12_details) <span class="details-text">{{ $report->owner_12_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">13. ¿Hay agua potable sanitaria en el sitio y suficiente agua corriente para departamentos como construcción, pintura, etc.?</h5>
                        <p class="card-text">
                            @if ($report->owner_13 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->owner_13_details) <span class="details-text">{{ $report->owner_13_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">14. ¿Hay algún riesgo relacionado con el agua, como fugas en techos tuberias?</h5>
                        <p class="card-text">
                            @if ($report->owner_14 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->owner_14_details) <span class="details-text">{{ $report->owner_14_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">15. ¿Hay seguridad en el sitio, especialmente para quienes trabajarán solos de noche?</h5>
                        <p class="card-text">
                            @if ($report->owner_15 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->owner_15_details) <span class="details-text">{{ $report->owner_15_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="report-section">
        <h2>SECCIÓN 2: INSPECCIÓN VISUAL - INSTALACIONES</h2>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">16. ¿La locación contiene una cantidad visible de polvo o particulas?</h5>
                        <p class="card-text">
                            @if ($report->inst_16 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->inst_16_details) <span class="details-text">{{ $report->inst_16_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">17. ¿Puede ver algo, como moho o humedad, que pueda llevar a niveles potencialmente peligrosos de exposición a contaminantes microbianos, como bacterias, levaduras, moho, hongos, virus, priones, protozoos o toxinas?</h5>
                        <p class="card-text">
                            @if ($report->inst_17 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->inst_17_details) <span class="details-text">{{ $report->inst_17_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">18. Si es así: Asegúrese de realizar pruebas de calidad del aire y otras pruebas aplicables.</h5>
                        <p class="card-text">{{ $report->inst_18_details ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">19. ¿Existe riesgo de exposición a contaminantes biológicos, como sangre, orina, heces, restos de animales?</h5>
                        <p class="card-text">
                            @if ($report->inst_19 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->inst_19_details) <span class="details-text">{{ $report->inst_19_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">20. ¿Las salidas, pasillos y escaleras están iluminados?</h5>
                        <p class="card-text">
                            @if ($report->inst_20 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->inst_20_details) <span class="details-text">{{ $report->inst_20_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">21. ¿Las salidas de emergencia están claramente marcadas y despejadas?</h5>
                        <p class="card-text">
                            @if ($report->inst_21 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->inst_21_details) <span class="details-text">{{ $report->inst_21_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">22. ¿Las escaleras tienen antideslizantes y hay pasamanos?</h5>
                        <p class="card-text">
                            @if ($report->inst_22 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->inst_22_details) <span class="details-text">{{ $report->inst_22_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">23. ¿Hay medios adecuados de salida de emergencia y comunicaciones, como luces, salidas de emergencia, líneas telefónicas operativas y señales?</h5>
                        <p class="card-text">
                            @if ($report->inst_23 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->inst_23_details) <span class="details-text">{{ $report->inst_23_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">24. ¿Hay áreas adecuadas para almacenar equipos que no obstruyan las salidas de emergencia, etc.?</h5>
                        <p class="card-text">
                            @if ($report->inst_24 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->inst_24_details) <span class="details-text">{{ $report->inst_24_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="report-section">
        <h2>INSPECCIÓN VISUAL - VENTILACIÓN</h2>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">25. ¿La producción utilizará químicos, pinturas o humo y niebla que requieran controles de ventilación y/o cabinas de pulverización?</h5>
                        <p class="card-text">
                            @if ($report->vent_25 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->vent_25_details) <span class="details-text">{{ $report->vent_25_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">26. ¿El edificio tiene un sistema de ventilación general que esté en funcionamiento?</h5>
                        <p class="card-text">
                            @if ($report->vent_26 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->vent_26_details) <span class="details-text">{{ $report->vent_26_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">27. ¿Hay áreas cerradas (ej. túneles) que puedan requerir ventilación suplementaria?</h5>
                        <p class="card-text">
                            @if ($report->vent_27 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->vent_27_details) <span class="details-text">{{ $report->vent_27_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">28. ¿Hay calefacción/aire acondicionado adecuadamente instalado?</h5>
                        <p class="card-text">
                            @if ($report->vent_28 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->vent_28_details) <span class="details-text">{{ $report->vent_28_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">29. ¿Se pueden traer calentadores y ventiladores sin comprometer la calidad del aire y la seguridad contra incendios?</h5>
                        <p class="card-text">
                            @if ($report->vent_29 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->vent_29_details) <span class="details-text">{{ $report->vent_29_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="report-section">
        <h2>INSPECCIÓN VISUAL - AMENIDADES</h2>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">30. ¿Hay baños higiénicos y funcionales para el número previsto de crew?</h5>
                        <p class="card-text">
                            @if ($report->batr_30 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->batr_30_details) <span class="details-text">{{ $report->batr_30_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">31. ¿La iluminación exterior es adecuada?</h5>
                        <p class="card-text">
                            @if ($report->batr_31 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->batr_31_details) <span class="details-text">{{ $report->batr_31_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="report-section">
        <h2>INSPECCIÓN VISUAL - CONTROL DE TRÁFICO</h2>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">32. ¿Es necesario organizar control de tráfico?</h5>
                        <p class="card-text">
                            @if ($report->trafic_32 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->trafic_32_details) <span class="details-text">{{ $report->trafic_32_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">32.2 ¿Se requiere un plan de gestión de tráfico?</h5>
                        <p class="card-text">
                            @if ($report->trafic_32_2 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                                @else <span class="yes-no-badge no-badge">No</span> @endif
                                @if ($report->trafic_32_2_details) <span class="details-text">{{ $report->trafic_32_2_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">33. ¿Es necesario desviar coches o peatones de manera segura alrededor del área de rodaje?</h5>
                        <p class="card-text">
                            @if ($report->trafic_33 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->trafic_33_details) <span class="details-text">{{ $report->trafic_33_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">34. ¿Hay circunstancias especiales (acrobacias o efectos especiales) que requerirán posiciones de bloqueo adicionales?</h5>
                        <p class="card-text">
                            @if ($report->trafic_34 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->trafic_34_details) <span class="details-text">{{ $report->trafic_34_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="report-section">
        <h2>INSPECCIÓN VISUAL - TRABAJO EN ALTURAS / PROTECCIÓN CONTRA CAÍDAS</h2>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">35. ¿Hay bordes sin protección (más altos de 3 metros sin barandillas ni tablas de pie) que puedan representar un riesgo de caída o de que se caigan objetos desde altura?</h5>
                        <p class="card-text">
                            @if ($report->height_35 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->height_35_details) <span class="details-text">{{ $report->height_35_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">36. ¿Hay riesgos potenciales de resbalones, tropiezos o caídas en la locación, como limpieza, cables eléctricos, agujeros en el piso?</h5>
                        <p class="card-text">
                            @if ($report->height_36 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->height_36_details) <span class="details-text">{{ $report->height_36_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">37. ¿Se requiere señalización para identificar áreas peligrosas de caída (riesgos potenciales de caída como bordes no marcados, áreas cubiertas o estructuralmente comprometidas) para otros en la producción? En caso de duda, consideren consultar a un ingeniero estructural.</h5>
                        <p class="card-text">
                            @if ($report->height_37 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->height_37_details) <span class="details-text">{{ $report->height_37_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="report-section">
        <h2>INSPECCIÓN VISUAL - ESPACIOS CONFINADOS</h2>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">38. ¿Hay espacios confinados asociados con o en la locación? (Ej. alcantarillas, cámaras subterráneas, silos)</h5>
                        <p class="card-text">
                            @if ($report->confi_38 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->confi_38_details) <span class="details-text">{{ $report->confi_38_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="report-section">
        <h2>CONSIDERACIONES CLIMÁTICAS</h2>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">39. ¿Las condiciones climáticas, como lluvia o viento, podrían aumentar los riesgos?</h5>
                        <p class="card-text">
                            @if ($report->wheat_39 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->wheat_39_details) <span class="details-text">{{ $report->wheat_39_details }}</span> @endif
                        </p>
                        @if ($report->wheat_39 == 1 && $report->wheat_39_1)
                            <p class="card-text details-text"> {{ $report->wheat_39_1 }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="report-section">
        <h2>AVISOS DE SEGURIDAD</h2>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">40. ¿Hay avisos de seguridad, boletines o prácticas de trabajo seguras específicas que la producción deba publicar o adjuntar a la hoja de llamado?</h5>
                        <p class="card-text">
                            @if ($report->adv_40 == 1) <span class="yes-no-badge yes-badge">Sí</span>
                            @else <span class="yes-no-badge no-badge">No</span> @endif
                            @if ($report->adv_40_details) <span class="details-text">{{ $report->adv_40_details }}</span> @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="report-section">
        <h2>RESPUESTA A EMERGENCIAS EN LOCACIÓN</h2>
        <div>
            @if ($report->emer_resp_1) <span class="emergency-item">Hidrantes</span> @endif
            @if ($report->emer_resp_2) <span class="emergency-item">Ambulancia</span> @endif
            @if ($report->emer_resp_3) <span class="emergency-item">Sala de primeros auxilios</span> @endif
            @if ($report->emer_resp_4) <span class="emergency-item">Bomberos</span> @endif
            @if ($report->emer_resp_5) <span class="emergency-item">Extintores</span> @endif
            
            @if ($report->emer_resp_7) <span class="emergency-item">Aspersores</span> @endif
            @if ($report->emer_resp_8) <span class="emergency-item">Paramédicos</span> @endif
            @if ($report->emer_resp_6 && $report->emer_resp_hosp) <span class="emergency-item">Hospital más cercano: <strong> {{ $report->emer_resp_hosp }} </strong></span> @elseif ($report->emer_resp_6) <span class="emergency-item"> <strong>Hospital más cercano: No especificado</strong></span> @endif
        </div>
    </div>

    <div class="report-section">
        <h2>RIESGOS ADICIONALES IDENTIFICADOS</h2>
        <p class="additional-risks">{{ $report->aditional_risk ?? 'Ninguno identificado.' }}</p>
    </div>

    <div class="report-section">
        <h2>Consideraciones:</h2>
        <p class="additional-considerations">{{ $report->aditional_cons ?? 'Ninguna consideración.' }}</p>
    </div>

    @if ($report->additional_images_paths)
        <div class="report-section">
            <h2>Imágenes Adicionales</h2>
            <div class="row">
                @foreach ($report->additional_images_paths as $imagePath)
                    <div class="col-md-4 mb-3">
                        <img src="{{ $imagePath }}" alt="Imagen Adicional" class="img-fluid rounded" style="max-height: 200px; object-fit: cover;">
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="report-section" style="text-align: center;">
        <h2>Fin del reporte</h2>
        <p class="signature-info">Realizado por: {{ $report->make_by }} |
         {{ $report->make_date ? \Carbon\Carbon::parse($report->make_date)->format('d-m-Y') : 'N/A' }}</p>
    </div>

    <div class="mt-4">
        <a href="{{ route('location.view') }}" class="btn btn-secondary">Volver a la Lista</a>
        <button class="btn btn-info no-print" onclick="window.print()">Imprimir Reporte</button>
    </div>

@endsection
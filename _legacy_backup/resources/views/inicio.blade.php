<meta name="_token" content="{{ csrf_token() }}">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/css/bootstrap.min.css"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.6/cropper.css"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.6/cropper.js"></script>
<style type="text/css">
.imgs {
  display: block;
  max-width: 100%;
}
.preview {
  overflow: hidden;
  width: 160px; 
  height: 160px;
  margin: 10px;
  border: 1px solid red;
}
.modal-lg{
  max-width: 1000px !important;
}
/* -------------- Dashboard Cards -------------- */
.metric-card {
        position: relative;
        overflow: hidden;
        border-radius: 12px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        background: #fff;
        padding: 1rem 1.25rem;
        margin-bottom: 1rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .metric-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }

    .metric-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.4rem;
        color: #555;
    }

    .metric-value {
        font-size: 1.8rem;
        font-weight: bold;
        color: #222;
    }

    .dashboard-wrapper {
        padding-top: 4rem;
        padding-bottom: 2rem;
    }

    .chart-containeredit {
        max-width: 900px;
        margin: auto;
    }
</style>

@extends('layouts.app')
@section('content')

@if(auth()->user()->admin)
<div class="container">
    <!--<div class="row">
        <div class="col">
        @if(auth()->user()->encuestadiaria === 1)
            <h1 class="display-4 main-msg">Hola {{auth()->user()->name}} bienvenido al panel de administración.</h1>
        @else
        <h1 class="display-4 main-msg">Hola {{auth()->user()->name}} bienvenido al panel de administración.</h1>
        
        
        </div>
        <a class="btn btn-outline-info" href="/dailyreport">Cuestionario de salud</a>

        @endif-->

        <div class="container mt-4">
    <div class="row mb-3">
        <div class="col-md-7">
            <div class="dashboard-card p-3">
                @if(auth()->user()->encuestadiaria)
                    <h1 class="h5 content-wr">Hola {{auth()->user()->name}}. Gracias por responder su cuestionario de salud.</h1>
                @else
                    <h1 class="h5 content-wr">Hola {{auth()->user()->name}}. Por favor responda su cuestionario.</h1>
                    <div>
                        <a class="btn btn-outline-info btn-sm mt-2" href="/dailyreport">Responder cuestionario</a>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-md-5">
            <div class="row">
                <div class="col-md-6 hide-icon">
                
                    <div class="dashboard-card metric-card text-end p-3">
                    <svg class="fondo-svg" viewBox="0 0 100 100" preserveAspectRatio="none">
                  <path d="M28.998 8.531l-2.134-2.134c-0.394-0.393-1.030-0.393-1.423 0l-12.795 12.795-6.086-6.13c-0.393-0.393-1.029-0.393-1.423 0l-2.134 2.134c-0.393 0.394-0.393 1.030 0 1.423l8.924 8.984c0.393 0.393 1.030 0.393 1.423 0l15.648-15.649c0.393-0.392 0.393-1.030 0-1.423z" fill="rgba(30, 221, 74, 0.38)"></path>
                </svg>
                        <div class="metric-title small text-muted mb-1">Días sin accidentes</div>
                        <div class="metric-value fw-bold">{{ $daysSinceLastAccident }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="dashboard-card metric-card text-end p-3">
                    <svg class="fondo-svg-loc" viewBox="0 0 100 100" preserveAspectRatio="none">
<path d="M32,0C18.746,0,8,10.746,8,24c0,5.219,1.711,10.008,4.555,13.93c0.051,0.094,0.059,0.199,0.117,0.289l16,24
	C29.414,63.332,30.664,64,32,64s2.586-0.668,3.328-1.781l16-24c0.059-0.09,0.066-0.195,0.117-0.289C54.289,34.008,56,29.219,56,24
	C56,10.746,45.254,0,32,0z M32,32c-4.418,0-8-3.582-8-8s3.582-8,8-8s8,3.582,8,8S36.418,32,32,32z" fill="rgba(221, 205, 30, 0.38)">
</svg>
                        <div class="metric-title small text-muted mb-1">Locaciones revisadas</div>
                        <div class="metric-value fw-bold">{{ $totalLocationReports }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
    <div class="col-md-7">
        <div class="dashboard-card-b">
            <div class="p-3 grafica-contenedor">
                <h2 class="h6 text-muted mb-3 text-left pl-3">Tendencia Semanal de Reportes Inseguros</h2>
                <canvas id="weeklyUnsafeTrendChart"></canvas>
                <p class="text-muted text-center mt-2" style="font-size: 0.8em;">*La línea punteada representa la tendencia general.</p>
            </div>
        </div>
    </div>
        <div class="col-md-5">
            <div class="row">
                <div class="col-md-6 mt-3">
                    <div class="dashboard-card metric-card text-end p-3">
                    <svg class="fondo-svg" viewBox="0 0 100 100" preserveAspectRatio="none">
<g>
	<path fill="rgba(30, 221, 218, 0.38)" d="M28,5h-6V3c0-0.6-0.4-1-1-1s-1,0.4-1,1v2h-8V3c0-0.6-0.4-1-1-1s-1,0.4-1,1v2H4C3.4,5,3,5.4,3,6v4h26V6C29,5.4,28.6,5,28,5z
		"/>
	<path fill="rgba(30, 221, 218, 0.38)" d="M3,28c0,0.6,0.4,1,1,1h18v-6c0-0.5,0.4-0.9,0.9-1c1.4-0.1,3.5-1.6,4.3-2.6C28.9,17.2,29,16,29,16v-4H3V28z"/>
	<path fill="rgba(30, 221, 218, 0.38)" d="M24,23.8V29c0.2,0,0.3-0.1,0.5-0.2l0.9-0.9c2-2,3.2-4.6,3.5-7.4c0,0.1-0.1,0.1-0.1,0.2C27.8,21.9,25.8,23.3,24,23.8z"/>
</g>
</svg>
                        <div class="metric-title small text-muted mb-1">Días de producción</div>
                        <div class="metric-value fw-bold">{{ $productionDays }}</div>
                    </div>
                </div>
                <div class="col-md-6 mt-3">
                    <div class="dashboard-card metric-card text-end p-3">
                    <svg class="fondo-svg-shoot" viewBox="0 0 100 100" preserveAspectRatio="none">
                      <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                        <path d="M15,1 L14.012085,1 L14.012085,2 L11.9954016,2 L11.9954016,1 L4.10116386,1 L4.10116386,2 L1.90127892,2 L1.90127892,1 L1,1 L1,17 L1.90127892,17 L1.90127892,16 L3.95410156,16 L3.95410156,17 L11.9954016,17 L11.9954016,16 L14.012085,16 L14.012085,17 L15,17 L15,1 L15,1 Z M4,14 L2,14 L2,12 L4,12 L4,14 L4,14 Z M4,10 L2,10 L2,8 L4,8 L4,10 L4,10 Z M4,6 L2,6 L2,4 L4,4 L4,6 L4,6 Z M11,15 L5,15 L5,10 L11,10 L11,15 L11,15 Z M11,8 L5,8 L5,3 L11,3 L11,8 L11,8 Z M14,14 L12,14 L12,12 L14,12 L14,14 L14,14 Z M14,10 L12,10 L12,8 L14,8 L14,10 L14,10 Z M12,6 L12,4 L14,4 L14,6 L12,6 Z" fill="rgba(110, 30, 221, 0.38)">
                        </path>
                      </g>
                    </svg>
                        <div class="metric-title small text-muted mb-1">Días de rodaje</div>
                        <div class="metric-value fw-bold">{{ $shootingDays }}</div>
                    </div>
                </div>
                <div class="col-md-6 mt-3">
                    <div class="dashboard-card metric-card text-end p-3">
                    <svg class="fondo-svg" viewBox="0 0 100 100" preserveAspectRatio="none">
<path d="M30.052 8.772l-0.337-0.659c-0.372-0.728-1.263-1.018-1.992-0.646l-5.919 3.022c-0.316 0.161-0.549 0.42-0.681 0.721-0.078 0.102-0.143 0.215-0.194 0.34l-1.451 3.571-0.514-0.733-3.122-5.355v-6.029c0-0.818-0.663-1.481-1.48-1.481h-0.74c-0.817 0-1.48 0.663-1.48 1.481v6.649c0 0.338 0.113 0.649 0.303 0.898 0.009 0.017 0.018 0.034 0.028 0.050l2.671 4.581-4.581 3.215 0.381-3.049c0.065-0.516-0.145-1.002-0.513-1.315l-4.141-5.117c-0.514-0.636-1.446-0.734-2.082-0.219l-0.575 0.466c-0.635 0.514-0.733 1.447-0.219 2.083l3.736 4.618-0.703 5.623c-0.036 0.285 0.013 0.562 0.125 0.805 0.040 0.192 0.118 0.379 0.238 0.549l2.55 3.637c0.021 0.029 0.043 0.058 0.065 0.085 0.126 0.367 0.394 0.684 0.773 0.862l6.021 2.815c0.015 0.007 0.031 0.014 0.046 0.020 0.493 0.336 1.163 0.352 1.681-0.010l5.447-3.809c0.67-0.469 0.834-1.392 0.365-2.062l-0.424-0.607c-0.468-0.67-1.391-0.834-2.061-0.366l-4.38 3.063-2.967-1.387 7.189-5.044c0.174-0.122 0.313-0.274 0.416-0.445 0.135-0.135 0.246-0.299 0.322-0.487l2.3-5.658 5.253-2.682c0.728-0.372 1.017-1.264 0.645-1.992zM8.499 27.249c0 2.071-1.679 3.75-3.75 3.75s-3.75-1.679-3.75-3.75c0-2.071 1.679-3.75 3.75-3.75s3.75 1.679 3.75 3.75z" fill="rgba(221, 30, 30, 0.38)"></path>
</svg>
                        <div class="metric-title small text-muted mb-1">Total Accidentes</div>
                        <div class="metric-value fw-bold">{{ $totalAccidents }}</div>
                    </div>
                </div>
                <div class="col-md-6 mt-3">
                    <div class="dashboard-card metric-card text-end p-3">
                    <svg class="fondo-svg-medic" viewBox="0 0 100 100" preserveAspectRatio="none">
                      <path d="M42.924 13h-4.924v-5.226c0-3.736-2.948-6.774-6.694-6.774h-12.611c-3.748 0-6.695 3.038-6.695 6.774v5.226h-4.925c-3.356 0-6.075 2.591-6.075 5.937v23.007c0 3.345 2.719 6.056 6.075 6.056h35.849c3.355 0 6.076-2.711 6.076-6.057v-23.006c0-3.346-2.721-5.937-6.076-5.937zm-26.924-5.226c0-1.399 1.292-2.774 2.695-2.774h12.611c1.399 0 2.694 1.375 2.694 2.774v5.226h-18v-5.226zm20 27.226h-7v7h-8v-7h-7v-8h7v-7h8v7h7v8z" fill="rgba(30, 65, 221, 0.38)"></path>
                    </svg>
                        <div class="metric-title small text-muted mb-1">Total Consultas</div>
                        <div class="metric-value fw-bold">{{ $totalMedicalConsults }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .container.mt-4 {
        margin-top: 1.5rem !important;
    }

    .fondo-svg {
      position: absolute;
      top: 12;
      left: -70;
      width: 250%; /* Ajusta el ancho según necesites */
      height: 350%; /* Ajusta la altura según necesites */
      z-index: 0; /* Asegura que esté detrás del texto */
    }
    .fondo-svg-loc {
      position: absolute;
      top: 10;
      left: -75;
      width: 100%; /* Ajusta el ancho según necesites */
      height: 180%; /* Ajusta la altura según necesites */
      z-index: 0; /* Asegura que esté detrás del texto */
    }
    .fondo-svg-shoot {
      position: absolute;
      top: -7;
      left: -70;
      width: 400%; /* Ajusta el ancho según necesites */
      height: 650%; /* Ajusta la altura según necesites */
      z-index: 0; /* Asegura que esté detrás del texto */
    }
    .fondo-svg-medic {
      position: absolute;
      top: 10;
      left: -65;
      width: 150%; /* Ajusta el ancho según necesites */
      height: 300%; /* Ajusta la altura según necesites */
      z-index: 0; /* Asegura que esté detrás del texto */
    }
    .hide-icon{
      overflow: hidden;
    }

    .icono-esquina {
        /* Estilos del icono */
        position: absolute;
        top: 5px;
        right: 5px;
        font-size: 1.2em;
        color: rgba(40, 167, 69, 0.8);
        z-index: 1; /* Asegura que el icono esté encima del SVG */
    }

    .dashboard-card {
        background-color: #fff;
        height: 8em;
        border-radius: 8px;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        margin-bottom: 10px;
        overflow: hidden;
    }

    /*.dashboard-card-b {
        background-color: #fff;
        height: 19em;
        border-radius: 8px;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        margin-bottom: 40px;
        overflow: hidden;
    }*/

    .content-wr {
        font-size: 0.9rem;
        color: #343a40;
        margin-bottom: 0;
    }

    .metric-card {
        transition: none !important; /* Eliminamos la transición para el hover */
    }

    .metric-title {
        font-size: 0.65rem;
        color: #6c757d !important;
        font-family: 'Poppins', sans-serif;
        font-weight: 500;
        z-index: 1; /* Asegura que el texto esté encima del SVG */
      position: relative; /* Necesario para el z-index */
    }

    .metric-value {
        font-size: 1rem;
        font-family: 'Poppins', sans-serif;
        font-weight: 700 !important;
        color: #343a40;
    }

    .dashboard-card h2.h6 {
        font-size: 0.75rem;
        color: #6c757d !important;
        font-weight: 500;
        margin-bottom: 0.8rem !important;
        text-align: left !important;
        padding-left: 10px;
    }

    .dashboard-card-b h2.h6 {
        font-size: 0.75rem;
        color: #6c757d !important;
        font-weight: 500;
        margin-bottom: 0.8rem !important;
        text-align: left !important;
        padding-left: 10px;
    }
    .grafica-contenedor {
        width: 100%;
        height: 18em;
        position: relative;
        overflow: hidden;
        
    }

    .grafica-contenedor canvas {
        position: absolute;
        top: 0;
        left: 0;
        width: 100% !important;
        height: 100% !important;
        height: calc(100% - 20px) !important; /* Reducimos la altura para dejar espacio abajo */
    }

    .grafica-contenedor h2 {
        position: absolute;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        font-size: 0.8em;
        color: #6c757d;
        margin-bottom: 5px; /* Añadimos un pequeño margen inferior */
    }

    .grafica-contenedor p {
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        font-size: 0.8em;
        color: #6c757d;
        margin-bottom: 5px; /* Añadimos un pequeño margen inferior */
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-trendline"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
    const weeklyUnsafeTrendChartCanvas = document.getElementById('weeklyUnsafeTrendChart');
    if (weeklyUnsafeTrendChartCanvas) {
        const ctxWeeklyTrend = weeklyUnsafeTrendChartCanvas.getContext('2d');

        const weeklyData = @json($weeklyUnsafeReports);
        const labels = weeklyData.map(item => {
            const startDate = new Date(item['week_start']);
            const endDate = new Date(item['week_end']);
            const startDay = startDate.getDate();
            const endDay = endDate.getDate();
            const month = new Intl.DateTimeFormat('es-MX', { month: 'short' }).format(startDate);

            return `${startDay} ${month} - ${endDay} ${new Intl.DateTimeFormat('es-MX', { month: 'short' }).format(endDate)}`;
        });

        new Chart(ctxWeeklyTrend, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Actos Inseguros',
                        data: weeklyData.map(item => item['acts_count']),
                        borderColor: 'rgba(54, 162, 235, 1)', // Azul
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        fill: false,
                        tension: 0.4,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        trendline: {
                            style: 'rgba(54, 162, 235, 0.8)',
                            lineStyle: 'dotted',
                            width: 2
                        }
                    },
                    {
                        label: 'Condiciones Inseguras',
                        data: weeklyData.map(item => item['conds_count']),
                        borderColor: 'rgba(255, 206, 86, 1)', // Amarillo
                        backgroundColor: 'rgba(255, 206, 86, 0.2)',
                        fill: false,
                        tension: 0.4,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        trendline: {
                            style: 'rgba(255, 206, 86, 0.8)',
                            lineStyle: 'dotted',
                            width: 2
                        }
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuad'
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Número de Reportes'
                        },
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Semana del Mes'
                        },
                        ticks: {
                            autoSkip: true,
                            maxRotation: 45,
                            minRotation: 0,
                            maxTicksLimit: 10
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    },
                    title: {
                        display: true,
                        text: ' '
                    }
                }
            }
        });
    }
});
</script> 
@else
<div class="container">
	<div class="row">
		<div class="col-md-4">
        <div>
            <!-- FORMULARIO DEL PERFIL -->
            <div>
                <form action="{{ route('uploadCropImage')}}" enctype='multipart/form-data' method="POST">
                    {{ csrf_field() }}
                    {{ method_field('post') }}
                    <div>
                    <div class="home-img">
                         <!--<p>{{__('messages.updatep')}}</p>-->
                         <p>Cambia tu foto de perfil</p>
                    </div>
                        <input type="file" name="imgperfil" class="form-control image">
                    </div>
     					<br>

                </form>
			</div>
		</div>
<hr>
<div class="profile-card-2">
    <img src="{{ asset("imagesprf/usrs/" . auth()->user()->imgperfil) }}" class="img img-fluid">
    <div class="profile-logo-container"></div>
    <div class="profile-logo">
        <img src="{{ URL::asset('img/logo-cc-usrs.svg') }}" width="100" alt="">
    </div>
    <div class="profile-logo-client">
        <img src="{{ URL::asset('img/redrum.png') }}" width="80" alt="">
    </div>
    <div class="profile-text-container"></div>
    <div class="profile-name">{{ auth()->user()->name }} {{ auth()->user()->lname }}</div>
    <div class="profile-username">{{ auth()->user()->zone }}</div>
    <div class="profile-icons">
        <span class="data-basic">{{ auth()->user()->puestodepartamento }} |
        {{ \Carbon\Carbon::parse(auth()->user()->borndate)->age }}</span>
    </div>
</div>
</div>

   

    <div class="col-lg-8 col-md-8 col-12">
    @if(auth()->user()->encuestadiaria)
            <h1 class="display-5 content-wr">Gracias responder su cuestionario de salud.</h1>
            @else
            
            <h1 class="display-5 content-wr">Por favor responda su cuestionario.</h1>
            
            <a class="btn btn-outline-info" href="/dailyreport">Responder cuestionario de salud</a></div>
            @endif
    </div>
</div>
@endif

<div class="modal fade" id="modal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Corta tu foto</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">×</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="img-container">
            <div class="row">
                <div class="col-md-8 imgs">
                    <img class="imgs" id="image" src="https://avatars0.githubusercontent.com/u/3456749">
                </div>
                <div class="col-md-4">
                    <div class="preview"></div>
                </div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="crop">Actualizar</button>
      </div>
    </div>
  </div>
  </div>
<script>
var $modal = $('#modal');
var image = document.getElementById('image');
var cropper;
  
$("body").on("change", ".image", function(e){
    var files = e.target.files;
    var done = function (url) {
      image.src = url;
      $modal.modal('show');
    };
    var reader;
    var file;
    var url;
    if (files && files.length > 0) {
      file = files[0];
      if (URL) {
        done(URL.createObjectURL(file));
      } else if (FileReader) {
        reader = new FileReader();
        reader.onload = function (e) {
          done(reader.result);
        };
        reader.readAsDataURL(file);
      }
    }
});
$modal.on('shown.bs.modal', function () {
    cropper = new Cropper(image, {
      aspectRatio: 1,
      viewMode: 3,
      preview: '.preview'
    });
}).on('hidden.bs.modal', function () {
   cropper.destroy();
   cropper = null;
});
$("#crop").click(function(){
    canvas = cropper.getCroppedCanvas({
        width: 800,
        height: 800,
      });
    canvas.toBlob(function(blob) {
        url = URL.createObjectURL(blob);
        var reader = new FileReader();
         reader.readAsDataURL(blob); 
         reader.onloadend = function() {
            var base64data = reader.result; 
            $.ajax({
                type: "POST",
                dataType: "json",
                url: "{{ route('uploadCropImage')}}",
                data: {'_token': $('meta[name="_token"]').attr('content'), 'image': base64data},
                success: function(data){
                    console.log(data);
                    $modal.modal('hide');
                    alert("Crop image successfully uploaded");
                    window.location.href = "/home";
                }
              });
         }
    });
})
</script>


@endsection

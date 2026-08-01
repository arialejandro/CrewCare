@extends('layouts.app')
@section('content')

<style>
    @media print {
      .no-print { display: none; }
      table { page-break-inside: avoid; }
    }
    .section-title {
      background-color: #f0f0f0;
      padding: 10px;
      font-weight: bold;
      border: 1px solid #ccc;
      margin-top: 30px;
    }
    .table td, .table th { vertical-align: middle; }
    .form-check-inline { margin-right: 15px; }
    textarea { resize: vertical; }
  
    .hide_hosp {
      display: none;
    }
    
    @media (max-width: 768px) {
  /* 1. Reset de la tabla para móvil */
  .table-bordered {
    border: none;
  }
  
  .table-bordered thead {
    display: none; /* Ocultamos la cabecera en móvil */
  }

  /* 2. Cada fila como bloque independiente */
  .table-bordered tbody tr {
    display: block;
    border: 1px solid #ddd;
    margin-bottom: 15px;
    padding: 10px;
    position: relative;
  }

  /* 3. Convertimos celdas en bloques con pseudo-labels */
  .table-bordered tbody td {
    display: block;
    padding: 5px 0;
    text-align: left;
  }

  /* 4. Estilo específico para cada columna */
  .table-bordered tbody td:nth-child(1)::before {
    content: "#: ";
    font-weight: bold;
  }
  
  .table-bordered tbody td:nth-child(2)::before {
    content: "  ";
    font-weight: bold;
  }

  .table-bordered tbody td:nth-child(3)::before {
    content: "Si: ";
    font-weight: bold;
  }

  .table-bordered tbody td:nth-child(4)::before {
    content: "No: ";
    font-weight: bold;
  }

  /* 5. Detalles como última fila */
  .table-bordered tbody td:nth-child(5) {
    border-top: 1px dashed #aaa;
    margin-top: 8px;
    padding-top: 8px;
  }

  .table-bordered tbody td:nth-child(5)::before {
    content: "Detalles: ";
    font-weight: bold;
    display: inline;
  }

  /* 6. Ajuste de radios para móvil */
  .table-bordered .form-check-input {
    transform: scale(1.3);
    margin-right: 5px;
  }
  /* Eliminar el pseudo-label en celdas que no deben mostrarlo */
  .table-bordered tbody td[colspan="3"]::before,
  .table-bordered tbody td[colspan="2"]::before {
    content: "" !important;
    display: none !important;
  }

  /* Estilo específico para campos que abarcan múltiples columnas */
  .table-bordered tbody td[colspan="3"] {
    border-top: 1px dashed #aaa;
    padding-top: 10px;
    margin-top: 10px;
    width: 100%;
  }

  /* Ajuste para el input dentro de estas celdas */
  .table-bordered tbody td[colspan="3"] input {
    width: 100% !important;
  }
  /* Eliminar el sistema de columnas */
  .section-title + .row {
    display: block !important;
  }
  
  .section-title + .row > .col-6 {
    width: 100% !important;
    max-width: 100% !important;
    flex: 0 0 100% !important;
    padding: 0 !important;
  }

  /* Espaciado entre textareas */
  .section-title + .row > .col-6:first-child {
    margin-bottom: 20px;
  }

  /* Ajustar textareas */
  .section-title + .row textarea {
    width: 100% !important;
    min-width: 100% !important;
  }
  /* 1. Reset completo del grid checkbox*/
  .section-title + .row {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important; /* 2 columnas iguales */
    gap: 12px !important;
    margin: 0 !important;
    padding: 0 10px !important;
  }

  /* 2. Items de checkbox - Ocupan 1 celda del grid */
  .section-title + .row .form-check {
    width: 100% !important;
    max-width: 100% !important;
    margin: 1em !important;
    padding: 8px 5px !important;
  }

  /* 3. Caso especial: Hospital (ocupa 2 columnas) */
  .form-check:has(label[for="hospital"]),
  .form-check:has(input[name="emer_resp_hosp"]) {
    grid-column: span 2 !important; /* Ocupa ambas columnas */
  }

  /* 4. Estilo para el input del hospital */
  input[name="emer_resp_hosp"] {
    width: 100% !important;
    margin-left: 0 !important;
    margin-top: 5px !important;
  }

  /* 5. Mejora visual de checkboxes */
  .form-check-input {
    transform: scale(1.3);
    margin-right: 10px !important;
  }
  .hide_hosp{
    display: block !important;
  }
}

       
  
  
  </style>
  

<h1 class="mb-4">Identificación de Riesgo en Locaciones</h1>

<form action="{{ route('locationreport.store') }}" method="POST" enctype="multipart/form-data">
@csrf
    <!-- Datos Generales -->
<div class="card mb-3">
  <div class="card-header">
    Datos Generales
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-3 mb-3">
        <label class="form-label">Producción <span class="form-text">Nombre del proyecto Ej. EGN</span></label></label>
        <input type="text" name="production_name" id="production_name" class="form-control">
      </div>
      <div class="col-md-3 mb-3">
        <label class="form-label">Episodio / Escena <span class="form-text">Ej. 102</span></label></label>
        <input type="text" name="scene" id="scene" class="form-control">
      </div>
      <div class="col-md-3 mb-3">
        <label class="form-label">Encabezado Escena <span class="form-text">Ej. INT. Restaurant</span></label>
        <input type="text" name="name_scene" id="name_scene" class="form-control">
      </div>
      <div class="col-md-3 mb-3">
        <label class="form-label">Nombre Locación <span class="form-text">Ej. Restaurant Vida Mia</span></label></label>
        <input type="text" name="name_loc" id="name_loc" class="form-control">
      </div>
    </div>
  </div>
</div>

<!-- Fechas y Horarios -->
<div class="card mb-3">
  <div class="card-header">
    Fechas y Horarios
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-2 mb-3">
        <label class="form-label">Fecha Prep</label>
        <input type="date" name="date_prep" id="date_prep" class="form-control">
      </div>
      <div class="col-md-2 mb-3">
        <label class="form-label">Fecha Shoot</label>
        <input type="date" name="date_shoot" id="date_shoot" class="form-control">
      </div>
      <div class="col-md-2 mb-3">
        <label class="form-label">Fecha Wrap</label>
        <input type="date" name="date_wrap" id="date_wrap" class="form-control">
      </div>
      <div class="col-md-2 mb-3">
        <label class="form-label">Hora Prep</label> <span class="form-text">*Si se conoce</span></label></label>
        <input type="text" name="hour_prep" id="hour_prep" class="form-control">
      </div>
      <div class="col-md-2 mb-3">
        <label class="form-label">Hora Shoot</label> <span class="form-text">*Si se conoce</span></label></label>
        <input type="text" name="hour_shoot" id="hour_shoot" class="form-control">
      </div>
      <div class="col-md-2 mb-3">
        <label class="form-label">Hora Wrap</label> <span class="form-text">*Si se conoce</span></label></label>
        <input type="text" name="hour_wrap" id="hour_wrap" class="form-control">
      </div>
    </div>
  </div>
</div>

<!-- Locación: Tipo de llamado -->
<div class="card mb-3">
  <div class="card-header">
    Tipo de llamado
  </div>
  <div class="card-body">
  <div class="row">
    <div class="col-6">
      <label class="form-label me-3"><strong>Tipo de Locación:</strong></label>
      <div class="form-check" style="display: inline-block; margin-right: 20px;">
                <input class="form-check-input" type="radio" name="loc_type" id="loc-interior" value="0" required>
                <label class="form-check-label" for="loc-interior">Interior</label>
            </div>
            <div class="form-check" style="display: inline-block; margin-right: 20px;">
                <input class="form-check-input" type="radio" name="loc_type" id="loc-exterior" value="1">
                <label class="form-check-label" for="loc-exterior">Exterior</label>
            </div>
            <div class="form-check" style="display: inline-block; margin-right: 20px;">
                <input class="form-check-input" type="radio" name="loc_type" id="loc-ambos" value="2">
                <label class="form-check-label" for="loc-ambos">Ambos</label>
            </div>
    </div>

    <div class="col-6">
      <label class="form-label me-3"><strong>Llamado:</strong></label>
      <div class="form-check" style="display: inline-block; margin-right: 20px;">
                <input class="form-check-input" type="radio" name="shoot_time" id="llamado-diurno" value="0" required>
                <label class="form-check-label" for="llamado-diurno">Diurno</label>
            </div>
            <div class="form-check" style="display: inline-block; margin-right: 20px;">
                <input class="form-check-input" type="radio" name="shoot_time" id="llamado-nocturno" value="1">
                <label class="form-check-label" for="llamado-nocturno">Nocturno</label>
            </div>
            <div class="form-check" style="display: inline-block; margin-right: 20px;">
                <input class="form-check-input" type="radio" name="shoot_time" id="llamado-mixto" value="2">
                <label class="form-check-label" for="llamado-mixto">Mixto</label>
            </div>
    </div>
  </div>
</div>
</div>


    <!-- Sección 1 -->
    <div class="section-title">SECCIÓN 1 - PREGUNTAS PARA EL ADMINISTRADOR / PROPIETARIO DE LA LOCACIÓN</div>
    <table class="table table-bordered">
      <thead class="table-light">
        <tr>
          <th style="width:5%">#</th>
          <th style="width:45%">Aspectos a Considerar</th>
          <th style="width:10%">Sí</th>
          <th style="width:10%">No</th>
          <th style="width:30%">Detalles</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>1</td>
          <td>¿Informó a los administradores/propietarios de la locación sobre las actividades que realizará la compañía de producción?</td>
          <td><input class="form-check-input" type="radio" name="owner_1" id="owner_1_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_1" id="owner_1_no" value="0"></td>
          <!--<td><textarea class="form-control"></textarea></td>-->
          <td><input type="text" name="owner_1_details" class="form-control mt-2" placeholder="Se informo por parte de..."></td>
        </tr>
        <tr>
          <td>2</td>
          <td>¿Los administradores/propietarios están al tanto de algún riesgo existente asociado a la locación?</td>
          <td><input class="form-check-input" type="radio" name="owner_2" id="owner_2_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_2" id="owner_2_no" value="0"></td>
          <td><input type="text" name="owner_2_details" class="form-control mt-2" placeholder="Existen riesgos en..."></td>
        </tr>
        <tr>
          <td>3</td>
          <td>¿Se ha realizado una evaluación de riesgos en el pasado?</td>
          <td><input class="form-check-input" type="radio" name="owner_3" id="owner_3_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_3" id="owner_3_no" value="0"></td>
          <td><input type="text" name="owner_3_details" class="form-control mt-2" placeholder="Evaluación de riesgos realizada..."></td>
        </tr>
        <tr>
          <td>3.1</td>
          <td>Si es así ¿Cuando se completó esta evaluación?</td>
          
          <td colspan="3"><input type="text" name="owner_3_1" class="form-control mt-2" placeholder="(Ej. 08/02/2024)"></td>
        </tr>
        <tr>
          <td>3.2</td>
          <td>¿Está disponible para el uso de esta producción?</td>
          <td><input class="form-check-input" type="radio" name="owner_3_2" id="owner_3_2_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_3_2" id="owner_3_2_no" value="0"></td>
          <td><input type="text" name="owner_3_2_details" class="form-control mt-2" placeholder="Será entregada por..."></td>
        </tr>
        <tr>
          <td>4</td>
          <td>¿Hay informes de ingeniería y planos que describan puntos de anclaje, cargas de peso y problemas estructurales disponibles?</td>
          <td><input class="form-check-input" type="radio" name="owner_4" id="owner_4_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_4" id="owner_4_no" value="0"></td>
          <td><input type="text" name="owner_4_details" class="form-control mt-2" placeholder="Los planos de la locación..."></td>
        </tr>
        <tr>
          <td>4.1</td>
          <td>Si es así: ¿Están disponibles para el uso de esta producción?</td>
          <td><input class="form-check-input" type="radio" name="owner_4_1" id="owner_4_1_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_4_1" id="owner_4_1_no" value="0"></td>
          <td><input type="text" name="owner_4_1_details" class="form-control mt-2" placeholder="La documentación..."></td>
        </tr>
        <tr>
          <td>5</td>
          <td>Si la locación es una instalación operativa, ¿hay procedimientos específicos de emergencia o evacuación que la producción deba seguir?</td>
          <td><input class="form-check-input" type="radio" name="owner_5" id="owner_5_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_5" id="owner_5_no" value="0"></td>
          <td><input type="text" name="owner_5_details" class="form-control mt-2" placeholder="Existen procedimientos..."></td>
        </tr>
        <tr>
          <td>5.1</td>
          <td>Si es así ¿Cuando cuando estarán disponibles para la producción?</td>
          
          <td colspan="3"><input type="text" name="owner_5_1" class="form-control mt-2" placeholder="La locación se compromete..."></td>
        </tr>
        <tr>
          <td>6</td>
          <td>¿Hay algún material peligroso como pinturas con plomo, asbesto o moho?</td>
          <td><input class="form-check-input" type="radio" name="owner_6" id="owner_6_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_6" id="owner_6_no" value="0"></td>
          <td><input type="text" name="owner_6_details" class="form-control mt-2" placeholder="Los materiales identificados..."></td>
        </tr>
        <tr>
          <td>6.1</td>
          <td>Si es así: ¿Todos los materiales peligrosos existentes están almacenados y/o asegurados correctamente?</td>
          <td><input class="form-check-input" type="radio" name="owner_6_1" id="owner_6_1_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_6_1" id="owner_6_1_no" value="0"></td>
          <td><input type="text" name="owner_6_1_details" class="form-control mt-2" placeholder="El almacen de materiales..."></td>
        </tr>
        <tr>
          <td>7</td>
          <td>¿La locación contiene materiales PCB (ej. transformadores eléctricos)?</td>
          <td><input class="form-check-input" type="radio" name="owner_7" id="owner_7_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_7" id="owner_7_no" value="0"></td>
          <td><input type="text" name="owner_7_details" class="form-control mt-2" placeholder="Se ha indentificado..."></td>
        </tr>
        <tr>
          <td>8</td>
          <td>¿Se ha utilizado esta locación para un propósito que haya generado polvo o particulas en exceso?</td>
          <td><input class="form-check-input" type="radio" name="owner_8" id="owner_8_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_8" id="owner_8_no" value="0"></td>
          <td><input type="text" name="owner_8_details" class="form-control mt-2" placeholder="En las últimas semanas..."></td>
        </tr>
        <tr>
          <td>9</td>
          <td>Si la locación es una instalación operativa, ¿hay hojas de datos de seguridad en el archivo de la locación para todos los materiales peligrosos utilizados/almacenados en el sitio?</td>
          <td><input class="form-check-input" type="radio" name="owner_9" id="owner_9_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_9" id="owner_9_no" value="0"></td>
          <td><input type="text" name="owner_9_details" class="form-control mt-2" placeholder="El propietario nos ha indicado..."></td>
        </tr>
        <tr>
          <td>10</td>
          <td>¿La corriente alterna (A.C.) está conectada a tierra?</td>
          <td><input class="form-check-input" type="radio" name="owner_10" id="owner_10_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_10" id="owner_10_no" value="0"></td>
          <td><input type="text" name="owner_10_details" class="form-control mt-2" placeholder="La corriente..."></td>
        </tr>
        <tr>
          <td>11</td>
          <td>¿Hay algún riesgo eléctrico potencial (cableado expuesto, cajas eléctricas, etc.) en la locación?</td>
          <td><input class="form-check-input" type="radio" name="owner_11" id="owner_11_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_11" id="owner_11_no" value="0"></td>
          <td><input type="text" name="owner_11_details" class="form-control mt-2" placeholder="El cableado se observa..."></td>
        </tr>
        <tr>
          <td>12</td>
          <td>¿Hay suficiente suministro eléctrico para la demanda requerida? (*Aplica sólo si la producción se conectara a la locación)</td>
          <td><input class="form-check-input" type="radio" name="owner_12" id="owner_12_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_12" id="owner_12_no" value="0"></td>
          <td><input type="text" name="owner_12_details" class="form-control mt-2" placeholder="La carga eléctrica es..."></td>
        </tr>
        <tr>
          <td>13</td>
          <td>¿Hay agua potable sanitaria en el sitio y suficiente agua corriente para departamentos como construcción, pintura, etc.?</td>
          <td><input class="form-check-input" type="radio" name="owner_13" id="owner_13_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_13" id="owner_13_no" value="0"></td>
          <td><input type="text" name="owner_13_details" class="form-control mt-2" placeholder="El suministro de agua..."></td>
        </tr>
        <tr>
          <td>14</td>
          <td>¿Hay algún riesgo relacionado con el agua, como fugas en techos tuberias?</td>
          <td><input class="form-check-input" type="radio" name="owner_14" id="owner_14_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_14" id="owner_14_no" value="0"></td>
          <td><input type="text" name="owner_14_details" class="form-control mt-2" placeholder="Se observa..."></td>
        </tr>
        <tr>
          <td>15</td>
          <td>¿Hay seguridad en el sitio, especialmente para quienes trabajarán solos de noche?</td>
          <td><input class="form-check-input" type="radio" name="owner_15" id="owner_15_yes" value="1"></td>
          <td><input class="form-check-input" type="radio" name="owner_15" id="owner_15_no" value="0"></td>
          <td><input type="text" name="owner_15_details" class="form-control mt-2" placeholder="La locación cuenta..."></td>
        </tr>
      </tbody>
    </table>

    <!-- Sección 2 - Inspección Visual: Instalaciones -->
<div class="section-title">SECCIÓN 2: INSPECCIÓN VISUAL - INSTALACIONES</div>
<table class="table table-bordered">
  <thead class="table-light">
    <tr>
        <th style="width:5%">#</th>
        <th style="width:45%">Aspectos a Considerar</th>
        <th style="width:10%">Sí</th>
        <th style="width:10%">No</th>
        <th style="width:30%">Detalles</th>
    </tr>
  </thead>
  <tbody>
  <tr>
        <td>16</td>
        <td>¿La locación contiene una cantidad visible de polvo o particulas?</td>
        <td><input class="form-check-input" type="radio" name="inst_16" id="inst_16_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="inst_16" id="inst_16_no" value="0"></td>
        <td><input type="text" name="inst_16_details" class="form-control mt-2" placeholder="El nivel de polvo..."></td>
    </tr>
    <tr>
        <td>17</td>
        <td>¿Puede ver algo, como moho o humedad, que pueda llevar a niveles potencialmente peligrosos de exposición a contaminantes microbianos, como bacterias, levaduras, moho, hongos, virus, priones, protozoos o toxinas?</td>
        <td><input class="form-check-input" type="radio" name="inst_17" id="inst_17_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="inst_17" id="inst_17_no" value="0"></td>
        <td><input type="text" name="inst_17_details" class="form-control mt-2" placeholder="El nivel de polvo..."></td>
    </tr>
    <tr>
        <td>18</td>
        <td>Si es así: Asegúrese de realizar pruebas de calidad del aire y otras pruebas aplicables.</td>
        
        <td colspan="3"><input type="text" name="inst_18_details" class="form-control mt-2" placeholder="Se ha considerado..."></td>
    </tr>
    <tr>
        <td>19</td>
        <td>¿Existe riesgo de exposición a contaminantes biológicos, como sangre, orina, heces, restos de animales?</td>
        <td><input class="form-check-input" type="radio" name="inst_19" id="inst_19_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="inst_19" id="inst_19_no" value="0"></td>
        <td><input type="text" name="inst_19_details" class="form-control mt-2" placeholder="Los contaminantes..."></td>
    </tr>
    <tr>
        <td>20</td>
        <td>¿Las salidas, pasillos y escaleras están iluminados?</td>
        <td><input class="form-check-input" type="radio" name="inst_20" id="inst_20_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="inst_20" id="inst_20_no" value="0"></td>
        <td><input type="text" name="inst_20_details" class="form-control mt-2" placeholder="Algunos pasillos..."></td>
    </tr>
    <tr>
        <td>21</td>
        <td>¿Las salidas de emergencia están claramente marcadas y despejadas?</td>
        <td><input class="form-check-input" type="radio" name="inst_21" id="inst_21_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="inst_21" id="inst_21_no" value="0"></td>
        <td><input type="text" name="inst_21_details" class="form-control mt-2" placeholder="Las salidas marcadas..."></td>
    </tr>
    <tr>
        <td>22</td>
        <td>¿Las escaleras tienen antideslizantes y hay pasamanos?</td>
        <td><input class="form-check-input" type="radio" name="inst_22" id="inst_22_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="inst_22" id="inst_22_no" value="0"></td>
        <td><input type="text" name="inst_22_details" class="form-control mt-2" placeholder="Se identificaron escaleras..."></td>
    </tr>
    <tr>
        <td>23</td>
        <td>¿Hay medios adecuados de salida de emergencia y comunicaciones, como luces, salidas de emergencia, líneas telefónicas operativas y señales?</td>
        <td><input class="form-check-input" type="radio" name="inst_23" id="inst_23_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="inst_23" id="inst_23_no" value="0"></td>
        <td><input type="text" name="inst_23_details" class="form-control mt-2" placeholder="En locación se observa..."></td>
    </tr>
    <tr>
        <td>24</td>
        <td>¿Hay áreas adecuadas para almacenar equipos que no obstruyan las salidas de emergencia, etc.?</td>
        <td><input class="form-check-input" type="radio" name="inst_24" id="inst_24_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="inst_24" id="inst_24_no" value="0"></td>
        <td><input type="text" name="inst_24_details" class="form-control mt-2" placeholder="Las áreas asignadas..."></td>
    </tr>
  </tbody>
</table>

<!-- Sección 3 - Ventilación -->
<div class="section-title">INSPECCIÓN VISUAL - VENTILACIÓN</div>
<table class="table table-bordered">
  <thead class="table-light">
    <tr>
        <th style="width:5%">#</th>
        <th style="width:45%">Aspectos a Considerar</th>
        <th style="width:10%">Sí</th>
        <th style="width:10%">No</th>
        <th style="width:30%">Detalles</th>
    </tr>
  </thead>
  <tbody>
    <tr>
        <td>25</td>
        <td>¿La producción utilizará químicos, pinturas o humo y niebla que requieran controles de ventilación y/o cabinas de pulverización?</td>
        <td><input class="form-check-input" type="radio" name="vent_25" id="vent_25_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="vent_25" id="vent_25_no" value="0"></td>
        <td><input type="text" name="vent_25_details" class="form-control mt-2" placeholder="Será necesario implementar..."></td>
    </tr>
    <tr>
        <td>26</td>
        <td>¿El edificio tiene un sistema de ventilación general que esté en funcionamiento?</td>
        <td><input class="form-check-input" type="radio" name="vent_26" id="vent_26_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="vent_26" id="vent_26_no" value="0"></td>
        <td><input type="text" name="vent_26_details" class="form-control mt-2" placeholder="La ventilación..."></td>
    </tr>
    <tr>
        <td>27</td>
        <td>¿Hay áreas cerradas (ej. túneles) que puedan requerir ventilación suplementaria?</td>
        <td><input class="form-check-input" type="radio" name="vent_27" id="vent_27_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="vent_27" id="vent_27_no" value="0"></td>
        <td><input type="text" name="vent_27_details" class="form-control mt-2" placeholder="Los espacios cerrados..."></td>
    </tr>
    <tr>
        <td>28</td>
        <td>¿Hay calefacción/aire acondicionado adecuadamente instalado?</td>
        <td><input class="form-check-input" type="radio" name="vent_28" id="vent_28_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="vent_28" id="vent_28_no" value="0"></td>
        <td><input type="text" name="vent_28_details" class="form-control mt-2" placeholder="Se ha observado que..."></td>
    </tr>
    <tr>
        <td>29</td>
        <td>¿Se pueden traer calentadores y ventiladores sin comprometer la calidad del aire y la seguridad contra incendios?</td>
        <td><input class="form-check-input" type="radio" name="vent_29" id="vent_29_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="vent_29" id="vent_29_no" value="0"></td>
        <td><input type="text" name="vent_29_details" class="form-control mt-2" placeholder="La producción deberá..."></td>
    </tr>
  </tbody>
</table>

<!-- Sección 4 - Servicios -->
<div class="section-title">INSPECCIÓN VISUAL - AMENIDADES</div>
<table class="table table-bordered">
  <thead class="table-light">
    <tr>
        <th style="width:5%">#</th>
        <th style="width:45%">Aspectos a Considerar</th>
        <th style="width:10%">Sí</th>
        <th style="width:10%">No</th>
        <th style="width:30%">Detalles</th>
    </tr>
  </thead>
  <tbody>
    <tr>
        <td>30</td>
        <td>¿Hay baños higiénicos y funcionales para el número previsto de crew?</td>
        <td><input class="form-check-input" type="radio" name="batr_30" id="batr_30_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="batr_30" id="batr_30_no" value="0"></td>
        <td><input type="text" name="batr_30_details" class="form-control mt-2" placeholder="Los sanitarios..."></td>
    </tr>
    <tr>
        <td>31</td>
        <td>¿La iluminación exterior es adecuada?</td>
        <td><input class="form-check-input" type="radio" name="batr_31" id="batr_31_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="batr_31" id="batr_31_no" value="0"></td>
        <td><input type="text" name="batr_31_details" class="form-control mt-2" placeholder="En general la iluminación..."></td>
    </tr>
  </tbody>
</table>

<!-- Sección 4 - Control de tráfico -->
<div class="section-title">INSPECCIÓN VISUAL - CONTROL DE TRÁFICO</div>
<table class="table table-bordered">
  <thead class="table-light">
    <tr>
        <th style="width:5%">#</th>
        <th style="width:45%">Aspectos a Considerar</th>
        <th style="width:10%">Sí</th>
        <th style="width:10%">No</th>
        <th style="width:30%">Detalles</th>
    </tr>
  </thead>
  <tbody>
    <tr>
        <td>32</td>
        <td>¿Es necesario organizar control de tráfico?</td>
        <td><input class="form-check-input" type="radio" name="trafic_32" id="trafic_32_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="trafic_32" id="trafic_32_no" value="0"></td>
        <td><input type="text" name="trafic_32_details" class="form-control mt-2" placeholder="Será necesario que..."></td>
    </tr>
    <tr>
        <td>32.1</td>
        <td>Si es así, asegúrese de realizar evaluaciones adicionales nivel de tráfico, condiciones, etc. Si es necesario apoyarse de seguridad fílmica o seguridad pública.</td>
        
        <td colspan="3"><input type="text" name="trafic_32_1" class="form-control mt-2" placeholder="En general el tráfico..."></td>
    </tr>
    <tr>
        <td>32.2</td>
        <td>¿Se requiere un plan de gestión de tráfico?</td>
        <td><input class="form-check-input" type="radio" name="trafic_32_2" id="trafic_32_2_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="trafic_32_2" id="trafic_32_2_no" value="0"></td>
        <td><input type="text" name="trafic_32_2_details" class="form-control mt-2" placeholder="Se propone que..."></td>
    </tr>
    <tr>
        <td>33</td>
        <td>¿Es necesario desviar coches o peatones de manera segura alrededor del área de rodaje?</td>
        <td><input class="form-check-input" type="radio" name="trafic_33" id="trafic_33_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="trafic_33" id="trafic_33_no" value="0"></td>
        <td><input type="text" name="trafic_33_details" class="form-control mt-2" placeholder="Los autos y peatones..."></td>
    </tr>
    <tr>
        <td>34</td>
        <td>¿Hay circunstancias especiales (acrobacias o efectos especiales) que requerirán posiciones de bloqueo adicionales?</td>
        <td><input class="form-check-input" type="radio" name="trafic_34" id="trafic_34_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="trafic_34" id="trafic_34_no" value="0"></td>
        <td><input type="text" name="trafic_34_details" class="form-control mt-2" placeholder="Algunas acciones..."></td>
    </tr>
  </tbody>
</table>

<!-- Sección 7 - Trabajo en Alturas / Caídas -->
<div class="section-title">INSPECCIÓN VISUAL - TRABAJO EN ALTURAS / PROTECCIÓN CONTRA CAÍDAS</div>
<table class="table table-bordered">
  <thead class="table-light">
    <tr>
        <th style="width:5%">#</th>
        <th style="width:45%">Aspectos a Considerar</th>
        <th style="width:10%">Sí</th>
        <th style="width:10%">No</th>
        <th style="width:30%">Detalles</th>
    </tr>
  </thead>
  <tbody>
    <tr>
        <td>35</td>
        <td>¿Hay bordes sin protección (más altos de 3 metros sin barandillas ni tablas de pie) que puedan representar un riesgo de caída o de que se caigan objetos desde altura?</td>
        <td><input class="form-check-input" type="radio" name="height_35" id="height_35_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="height_35" id="height_35_no" value="0"></td>
        <td><input type="text" name="height_35_details" class="form-control mt-2" placeholder="Se ha observado que los bordes..."></td>
    </tr>
    <tr>
        <td>36</td>
        <td>¿Hay riesgos potenciales de resbalones, tropiezos o caídas en la locación, como limpieza, cables eléctricos, agujeros en el piso?</td>
        <td><input class="form-check-input" type="radio" name="height_36" id="height_36_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="height_36" id="height_36_no" value="0"></td>
        <td><input type="text" name="height_36_details" class="form-control mt-2" placeholder="Se observan riesgos de..."></td>
    </tr>
    <tr>
        <td>37</td>
        <td>¿Se requiere señalización para identificar áreas peligrosas de caída (riesgos potenciales de caída como bordes no marcados, áreas cubiertas o estructuralmente comprometidas) para otros en la producción? En caso de duda, consideren consultar a un ingeniero estructural.</td>
        <td><input class="form-check-input" type="radio" name="height_37" id="height_37_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="height_37" id="height_37_no" value="0"></td>
        <td><input type="text" name="height_37_details" class="form-control mt-2" placeholder="Se deberá señalizar..."></td>
    </tr>
  </tbody>
</table>

<!-- Sección 8 - Espacios Confinados -->
<div class="section-title">INSPECCIÓN VISUAL - ESPACIOS CONFINADOS</div>
<table class="table table-bordered">
  <thead class="table-light">
    <tr>
        <th style="width:5%">#</th>
        <th style="width:45%">Aspectos a Considerar</th>
        <th style="width:10%">Sí</th>
        <th style="width:10%">No</th>
        <th style="width:30%">Detalles</th>
    </tr>
  </thead>
  <tbody>
    <tr>
        <td>38</td>
        <td>¿Hay espacios confinados asociados con o en la locación? (Ej. alcantarillas, cámaras subterráneas, silos)</td>
        <td><input class="form-check-input" type="radio" name="confi_38" id="confi_38_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="confi_38" id="confi_38_no" value="0"></td>
        <td><input type="text" name="confi_38_details" class="form-control mt-2" placeholder="Se ha observado espacios..."></td>
    </tr>
  </tbody>
</table>

<!-- Sección 9 - Consideraciones Climáticas -->
<div class="section-title">CONSIDERACIONES CLIMÁTICAS</div>
<table class="table table-bordered">
  <thead class="table-light">
    <tr>
        <th style="width:5%">#</th>
        <th style="width:45%">Aspectos a Considerar</th>
        <th style="width:10%">Sí</th>
        <th style="width:10%">No</th>
        <th style="width:30%">Detalles</th>
    </tr>
  </thead>
  <tbody>
    <tr>
        <td>39</td>
        <td>¿Las condiciones climáticas, como lluvia o viento, podrían aumentar los riesgos?</td>
        <td><input class="form-check-input" type="radio" name="wheat_39" id="wheat_39_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="wheat_39" id="wheat_39_no" value="0"></td>
        <td><input type="text" name="wheat_39_details" class="form-control mt-2" placeholder="El clima en el rodaje..."></td>
    </tr>
    <tr>
        <td>39.1</td>
        <td>Si es SÍ, identifique qué riesgos son preocupantes debido a factores climáticos (ej. caída de ramas).</td>
        <td colspan="3"><input type="text" name="wheat_39_1" class="form-control mt-2" placeholder="Existe la posibilidad de..."></td>
    </tr>
  </tbody>
</table>

<!-- Sección 10 - Avisos de Seguridad y Respuesta de Emergencia -->
<div class="section-title">AVISOS DE SEGURIDAD</div>
<table class="table table-bordered">
  <thead class="table-light">
    <tr>
        <th style="width:5%">#</th>
        <th style="width:45%">Aspectos a Considerar</th>
        <th style="width:10%">Sí</th>
        <th style="width:10%">No</th>
        <th style="width:30%">Detalles</th>
    </tr>
  </thead>
  <tbody>
    <tr>
        <td>40</td>
        <td>¿Hay avisos de seguridad, boletines o prácticas de trabajo seguras específicas que la producción deba publicar o adjuntar a la hoja de llamado?</td>
        <td><input class="form-check-input" type="radio" name="adv_40" id="adv_40_yes" value="1"></td>
        <td><input class="form-check-input" type="radio" name="adv_40" id="adv_40_no" value="0"></td>
        <td><input type="text" name="adv_40_details" class="form-control mt-2" placeholder="Se deberá adjuntar..."></td>
    </tr>
  </tbody>
</table>

<!-- Sección 11 - Respuesta a Emergencias en Locación -->
<div class="section-title">RESPUESTA A EMERGENCIAS EN LOCACIÓN</div>
<div class="row" style="margin-left: 1em; margin-right: 1em; margin-top:1em;">
    <div class="form-check col-4">
        <input type="hidden" name="emer_resp_1" value="0"> <!-- Valor predeterminado si no se marca -->
        <input class="form-check-input" type="checkbox" name="emer_resp_1" id="hidrantes" value="1">
        <label class="form-check-label" for="hidrantes">Hidrantes</label>
    </div>
    <div class="form-check col-4">
        <input type="hidden" name="emer_resp_2" value="0">
        <input class="form-check-input" type="checkbox" name="emer_resp_2" id="ambulancia" value="1">
        <label class="form-check-label" for="ambulancia">Ambulancia</label>
    </div>
    <div class="form-check col-4">
        <input type="hidden" name="emer_resp_3" value="0">
        <input class="form-check-input" type="checkbox" name="emer_resp_3" id="sala" value="1">
        <label class="form-check-label" for="sala">Sala de primeros auxilios</label>
    </div>
    <div class="form-check col-4">
        <input type="hidden" name="emer_resp_4" value="0">
        <input class="form-check-input" type="checkbox" name="emer_resp_4" id="bomberos" value="1">
        <label class="form-check-label" for="bomberos">Bomberos</label>
    </div>
    <div class="form-check col-4">
        <input type="hidden" name="emer_resp_5" value="0">
        <input class="form-check-input" type="checkbox" name="emer_resp_5" id="extintores" value="1">
        <label class="form-check-label" for="extintores">Extintores</label>
    </div>
    <div class="form-check col-4">
        <input type="hidden" name="emer_resp_6" value="0">
        <input class="form-check-input" type="checkbox" name="emer_resp_6" id="hospital" value="1">
        <label class="form-check-label" for="hospital">Hospital cercano:</label>
    </div>
    <div class="form-check col-4">
        <input type="hidden" name="emer_resp_7" value="0">
        <input class="form-check-input" type="checkbox" name="emer_resp_7" id="aspersores" value="1">
        <label class="form-check-label" for="aspersores">Aspersores</label>
    </div>
    <div class="form-check col-4">
        <input type="hidden" name="emer_resp_8" value="0">
        <input class="form-check-input" type="checkbox" name="emer_resp_8" id="paramedicos" value="1">
        <label class="form-check-label" for="paramedicos">Paramédicos</label>
    </div>
    <div class="form-check col-4">
    <label class="form-check-label hide_hosp" for="emer_resp_hosp">Especifique hospital cercano:</label>
        <input type="text" name="emer_resp_hosp" class="form-control mt-2" placeholder="Centro Médico Florencia">
    </div>
</div>

<!-- Sección 11 - Riesgos adicionales -->
<div class="section-title">RIESGOS ADICIONALES IDENTIFICADOS</div>
<div class="row">
<div class="col-6">
  <label class="form-label">¿Qué riesgos adicionales, si los hay, ha identificado?</label>
  <p class="form-text">Enumere en forma de puntos.</p>
  <textarea class="form-control" name="aditional_risk" rows="4"></textarea>
</div>

<div class="col-6">
  <label class="form-label">Consideraciones:</label>
  <p class="form-text">Indique las Consideraciones en forma de puntos.</p>
  <textarea class="form-control" name="aditional_cons" rows="4"></textarea>
</div>
</div>

<!-- Sección 11-b - imagenes -->
<div class="section-title">IMÁGENES DE LA LOCACIÓN</div>
    <div class="mb-3">
        <label for="main_image" class="form-label"><strong>Imagen Principal de la Locación:</strong></label>
        <input type="file" class="form-control" id="main_image" name="main_image">
        <div class="form-text">Esta será la imagen principal del reporte.</div>
    </div>

    <div class="mb-3">
        <label class="form-label"><strong>Imágenes Adicionales (Evidencia):</strong></label>
        <div id="additional_images_container">
            </div>
        <button type="button" class="btn btn-outline-secondary mt-2" onclick="addImageField()">Agregar otra imagen</button>
        <div class="form-text">Puedes agregar varias imágenes como evidencia.</div>
    </div>
<!-- Sección 12 - Firma y distribución -->
<div class="section-title">CIERRE</div>
<div class="row">
  <div class="col-md-10">
    <label class="form-label">Completado por:</label>
    <input type="text" name="make_by" class="form-control mt-2" placeholder="Ari Rómulo">
  </div>
  <div class="col-md-2">
    <label class="form-label">Fecha realizado:</label>
    <input type="date" name="make_date" class="form-control mt-2">
  </div>
</div>
    <!-- Botones -->
    <div class="my-4">
      <button type="submit" class="btn btn-primary">Guardar</button>
      <button type="button" class="btn btn-secondary no-print" onclick="window.print()">Imprimir</button>
    </div>
  </form>

  <script>
    let imageCounter = 0;

    function addImageField() {
        imageCounter++;
        const container = document.getElementById('additional_images_container');
        const div = document.createElement('div');
        div.classList.add('image-upload-container');
        div.innerHTML = `
            <label for="additional_image_${imageCounter}" class="form-label">Imagen Adicional ${imageCounter}:</label>
            <input type="file" class="form-control" id="additional_image_${imageCounter}" name="additional_images[]">
            <button type="button" class="btn btn-sm btn-danger remove-image-button" onclick="removeImageField(this)">Eliminar</button>
        `;
        container.appendChild(div);
    }

    function removeImageField(button) {
        button.parentNode.remove();
    }
</script>
  @endsection
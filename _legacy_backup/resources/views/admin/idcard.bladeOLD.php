@extends('layouts.app')
@section('content')

<div class="col-md-12 d-flex justify-content-center">
            <div id="printpdf" class="p-3 py-5">
                <div id="print" class="gft">
                <img src="{{ URL::asset('img/ap-logo.png') }}" class="logoap-gft">
                <img src="{{ URL::asset('img/sss-logo.png') }}" class="logosss-gft">
                @switch ($users->zone)
                                @case ('1A')
                                <div class="upper-gft-1a position-static"></div>
                                @break
                                @case ('1B')
                                <div class="upper-gft-1b position-static"></div>
                                @break
                                @case ('2')
                                <div class="upper-gft-2"></div>
                                @break
                                @case ('3')
                                <div class="upper-gft-3"></div>
                                @break
                                    @default
                                    <div class="upper-gft-nozone"></div>
                            @endswitch  
                    <div class="project-gft">
                        <p>TGH 2</p>
                    </div>
                    <div class="user text-center">
                   <div class="profile"> <img src="{{ asset("imagesprf/usrs/$users->imgperfil")}}" class="rounded-circle circle-gft"> </div>
                   <!-- <div class="profile"> <img src="{{ asset("imagesprf/usrs/$users->imgperfil")}}" class="rounded-circle circle-gft"> </div>-->
                  
                    
                </div>
                <div class="text-center">
                    <h4 class="mb-0 name-gft">{{$users->name}}</h4> <h4 class="mb-0 name-gft">{{$users->lname}}</h4> <span class="text-muted d-block mb-2 position-gft">{{$users->puestodepartamento}}</span>
                    <div class="barcode-gft justify-content-center">
                            
                    <span class="text-muted d-block mb-2 consecutive-gft">Consecutive: {{$users->labn}}</span>
                            @php
                            $widthFactor = 2;
                            $height = 60;
                            $generator = new Picqer\Barcode\BarcodeGeneratorHTML();
                            echo $generator->getBarcode($users->labn, $generator::TYPE_CODE_128, $widthFactor, $height);
                            @endphp
                            
                            
                        </div>
                    <div class="justify-content-between align-items-center">
                            @switch ($users->zone)
                                @case ('1A')
                                    <div class="gft-1a align-middle"><p>Zone {{$users->zone}}</p></div>
                                @break
                                @case ('1B')
                                    <div class="gft-1b clip-path-gft-1b align-middle"><p>Zone {{$users->zone}}</p></div>
                                @break
                                @case ('2')
                                    <div class="gft-2 align-middle"><p>Zone {{$users->zone}}</p></div>
                                @break
                                @case ('3')
                                    <div class="gft-3"><p>Zone {{$users->zone}}</p></div>
                                @break
                                    @default
                                    <div class="gft-nozone align-middle"><p>No Zone</p></div>
                            @endswitch  
                   
                    </div>
                </div>
            </div>    
            
            </div>
            
            <small><a id="btnCapturar" href="#">Download as Image</a></small>
            <small><a id="btnPrint" href="#">Download as PDF</a></small>
        </div>

        <div id="contenedorCanvas" style="border: 1px solid red;">
  </div>

        </script>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
<script type="text/javascript" src="https://html2canvas.hertzen.com/dist/html2canvas.js"></script>
 <!-- Script to print the content of a div -->
 <script type="text/javascript">
$(function () {
    $("#btnPrint").click(function () {
        var contents = $("#printpdf").html();
        var frame1 = $('<iframe />');
        frame1[0].name = "frame1";
        frame1.css({ "position": "absolute", "margin-top": "-1000000px" });
        $("body").append(frame1);
        var frameDoc = frame1[0].contentWindow ? frame1[0].contentWindow : frame1[0].contentDocument.document ? frame1[0].contentDocument.document : frame1[0].contentDocument;
        frameDoc.document.open();
        //Create a new HTML document.
        frameDoc.document.write('<html>');
        frameDoc.document.write('<body>');
        //Append the external CSS file.
        frameDoc.document.write('<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">');
        frameDoc.document.write('<link rel="stylesheet" href="{{ asset("css/form-register.css") }}">');        
        frameDoc.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">');
        frameDoc.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">');
        //Append the DIV contents.
        frameDoc.document.write(contents);
        frameDoc.document.write('</body></html>');
        frameDoc.document.close();
        setTimeout(function () {
            window.frames["frame1"].focus();
            window.frames["frame1"].print();
            frame1.remove();
        }, 500);
    });
});
</script>
<script>   
//Definimos el botón para escuchar su click, y también el contenedor del canvas
const $boton = document.querySelector("#btnCapturar"), // El botón que desencadena
  $objetivo = document.querySelector("#print"), // A qué le tomamos la foto
  $contenedorCanvas = document.querySelector("#contenedorCanvas"); // En dónde ponemos el elemento canvas

// Agregar el listener al botón
$boton.addEventListener("click", () => {
  html2canvas(document.querySelector("#print"), {scale: 2}).then(canvas => {
      // Cuando se resuelva la promesa traerá el canvas
      $contenedorCanvas.appendChild(canvas); // Lo agregamos como hijo del div
    });
});
    </script>

@endsection
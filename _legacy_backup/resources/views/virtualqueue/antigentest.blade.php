@extends('layouts.app')

@section('content')

<div class="container">
    <div class="h-screen flex-grow-1 overflow-y-lg-auto">   
    <header class="bg-surface-primary border-bottom pt-6">
            <div class="container-fluid">
                <div class="mb-npx">
                    <div class="row">
                        
                            <!-- Title -->
                            <h1 class="display-3 text-center">Panel de pruebas</h1>
                            <small><a id="btnPrint" href="#">Descargar Reporte</a></small>
                           
                        
                    </div>
                </div>
            </div> 
    </header>
    </div>
    <main class="my-2 bg-surface-secondary">
        <div class="container-fluid">
               

    <div class="my-3"></div>
        <div class="row">
			<div id="printpdf" class="col-md-12 grid-margin">
                <div class="p-10 bg-surface-secondary">
                <div class="card">
            <div class="card-header">
                <h4 style="text-align:center;">Reporte de Pruebas | <img src="{{ URL::asset('img/logo-cc-usrs.svg') }}"  width="48"> | {{ Carbon\Carbon::now()->setTimezone('America/Mexico_City')->format('d-m-Y') }}</h4>
            </div>
            
        <div id="usertable" class="table-responsive">
            <table class="table table-hover table-nowrap">
                <thead class="table-light">
                    <tr>
                        <th scope="col">N/P</th>
                        <th scope="col">NC</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">Apellido</th>
                        <th scope="col">Apellido 2</th>
                        <th scope="col">F. Nac.</th>
                        <th scope="col">Edad</th>
                        <th scope="col">Result.</th>
                    </tr>
                </thead>
                <tbody>
                <div style="display:none;"> {{$nc = 0}} </div>
                
                @foreach ($usuarios as $user)
                <div style="display:none;"> {{$nc = $nc + 1}}  </div>
                        @if($user->enfermo == 0)
                            <tr>
                        @else
                            <tr class="table-warning">
                        @endif
                        <td><a title="No Queue" class="text-danger" style="text-decoration:none;" href="{{url('/notodays/'.$user->id)}}">
                            <i class="fa-solid fa-circle-xmark"></i>
                            </a></td>
                            <th>{{ $initial }}{{$nc}}</th>
                       
                        <td>{{$user->name}}</td>
                        <td>{{$user->lname}}</td>
                        <td>{{$user->lname2}}</td>
                        <td>{{$user->borndate}}</td>
                        <td>{{ \Carbon\Carbon::parse($user->borndate)->age }}</td>
                        <td>
                            <a title="Tested" class="dropdown-item" href="{{url('/usertested/'.$user->id)}}">
                            <i class="fa-solid fa-notes-medical"></i>&nbsp Negativo
                            </a>
                            
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div>
                {{ $usuarios->links() }}
            </div>
        </div>
	</div>
                </div>
            </div>
            
           
        </div>
        </div>
	</main>

</div>

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

@endsection
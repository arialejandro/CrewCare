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
</style>
@extends('layouts.app')
@section('content')

<div class="container">
	<div class="row">
		<div class="col-md-4">
    <h4 class="text-center"><strong>MI PERFIL</strong></h4>
    <hr>
    <div class="profile-card-2"><img src="{{ asset("imagesprf/usrs/$users->imgperfil")}}" class="img img-fluid">
        <div class="profile-logo-container"></div>
        <div class="profile-logo"><img src="{{ URL::asset('img/logo-cc-usrs.svg') }}" width="100" alt=""></div>
        <div class="profile-logo-client"><img src="{{ URL::asset('img/redrum.png') }}" width="80" alt=""></div>
		<div class="profile-text-container"></div>
        <div class="profile-name">{{$users->name}} {{$users->lname}}</div>
        <div class="profile-username">{{$users->zone}}</div>
        <div class="profile-icons">
			<span class="data-basic">{{$users->puestodepartamento}} |
			{{ \Carbon\Carbon::parse($users->borndate)->age }}</span>
		</div>
    </div>

    <div>
            <!-- FORMULARIO DEL PERFIL -->
            <div>
                <form action="{{ route('uploadCropImage')}}" enctype='multipart/form-data' method="POST">
                    {{ csrf_field() }}
                    {{ method_field('post') }}
                    <div>
                    <!--<div class="project-gft">
                         <p>{{__('messages.updatep')}}</p>
                         <p>Cambia tu foto de perfil</p>
                    </div>-->
                    <label>Cambia tu foto de perfil</label>
                        <input type="file" name="imgperfil" class="form-control image">
                    </div>
     					<br>

                </form>
			</div>
		</div>
	</div>
</div>


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

    <div class="col-8">
    @if(auth()->user()->encuestadiaria)
            <h1 class="display-4 content-wr">Gracias responder su cuestionario de salud.</h1>
            @else
            
            <h1 class="display-4 content-wr">Hola <strong>{{auth()->user()->name}} {{auth()->user()->lname}}</strong> por favor responda su cuestionario.</h1>
            </div>
            <a class="btn btn-outline-info" href="/dailyreport">Responder cuestionario de salud</a>
            @endif
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
                    window.location.href = "/profile";
                }
              });
         }
    });
})
</script>

@endsection
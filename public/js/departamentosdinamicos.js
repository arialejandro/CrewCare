
function onSelectdep(data){
    var catid =  $(this).val();
    console.log(catid);
}
$(document).on('change', $("id_departamentos"), function(el) {
    console.log($(el.target).val());
    fetch('http://127.0.0.1:8000/cargarusuarios/' + $(el.target).val(),{
        method: 'get'
    }).then(function(response){
        return response.text();
    }).then(function(htmlContent){
        $('#usuariosdepartamento').html(htmlContent);
    }).catch(function(err){
        console.log(err);
    });
});
function cargarusuarios(departamento){
   
}
/* =============================================================================
   inicio.js — Home view (CrewCare): profile-photo cropper + upload modal.

   Ported from Bootstrap 4 (jQuery .modal() API) to Bootstrap 5.
   - BS5 removed the jQuery `$(el).modal('show'|'hide')` plugin, so the show/hide
     calls now use the BS5 programmatic API:
       bootstrap.Modal.getOrCreateInstance(el).show() / .hide()
   - BS5 still dispatches the native `shown.bs.modal` / `hidden.bs.modal` events,
     so the existing jQuery `.on('shown.bs.modal', ...)` listeners keep working
     (jQuery is provided by the layout). Selectors unchanged.
   - Cropper.js initialization logic is intact (1:1 aspect ratio, 800x800 export).

   CSRF: reads the route token from the original `_token` meta tag (kept in the
   view) — see note in inicio.blade.php. Behavior identical to the original AJAX.
   ============================================================================= */
(function () {
  var $modal = $('#modal');
  var modalEl = document.getElementById('modal');
  var image = document.getElementById('image');
  var cropper;

  // BS5: get (or lazily create) the Bootstrap Modal instance for #modal.
  function getModal() {
    return bootstrap.Modal.getOrCreateInstance(modalEl);
  }

  // When the user picks a file, load it into the <img> and open the modal.
  $('body').on('change', '.image', function (e) {
    var files = e.target.files;
    var done = function (url) {
      image.src = url;
      getModal().show(); // BS5 replacement for $modal.modal('show')
    };
    var reader;
    var file;
    if (files && files.length > 0) {
      file = files[0];
      if (URL) {
        done(URL.createObjectURL(file));
      } else if (FileReader) {
        reader = new FileReader();
        reader.onload = function () {
          done(reader.result);
        };
        reader.readAsDataURL(file);
      }
    }
  });

  // BS5 still fires native shown/hidden.bs.modal events — jQuery listeners keep working.
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

  // Crop to 800x800, convert to base64, and POST it via AJAX.
  $('#crop').click(function () {
    var canvas = cropper.getCroppedCanvas({
      width: 800,
      height: 800
    });
    canvas.toBlob(function (blob) {
      var reader = new FileReader();
      reader.readAsDataURL(blob);
      reader.onloadend = function () {
        var base64data = reader.result;
        $.ajax({
          type: 'POST',
          dataType: 'json',
          url: window.uploadCropImageUrl,
          data: {
            // CSRF: original `_token` meta (kept in the view).
            '_token': $('meta[name="_token"]').attr('content'),
            'image': base64data
          },
          success: function (data) {
            console.log(data);
            getModal().hide(); // BS5 replacement for $modal.modal('hide')
            alert('Crop image successfully uploaded');
            window.location.href = '/home';
          }
        });
      };
    });
  });
})();

<div style="color:rgb(29,29,29);margin:0;padding:10px 0 0 0" bgcolor="#ffffff">
<table align="center" border="0" cellpadding="0" cellspacing="0" width="900" style="max-width:1200px;width:900px;border-collapse:collapse"> 
    <tbody>
        <tr>
            <td>
                <table border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;padding-top:10px;padding-bottom:10px;border-collapse:collapse;padding-top:10px;padding-bottom:10px"> 
                    <tbody> 
                        <tr> 
                            <td style="padding-bottom:15px;padding-bottom:15px"> 
                            </td> 
                        </tr> 
                    </tbody> 
                </table>
            </td>
        </tr>
        <tr>
            <table cellpadding="0" cellspacing="0" border="0" height="100%" width="100%" bgcolor="#e0e0e0" style="border-collapse:collapse;">
  <tr>
    <td><center style="width: 100%;">
        
        
        <div style="max-width: 600px;"> 
          <!--[if (gte mso 9)|(IE)]>
            <table cellspacing="0" cellpadding="0" border="0" width="600" align="center">
            <tr>
            <td>
            <![endif]--> 
          
          <!-- Email Header : BEGIN -->
          <table cellspacing="0" cellpadding="0" border="0" align="center" width="100%" style="max-width: 600px;">
            <tr>
              <td style="padding: 20px 0; text-align: center"><img src="https://ap2.crewcare.app/img/logo-mails.png"  width="200"></td>
            </tr>
          </table>
          <!-- Email Header : END --> 
          
          <!-- Email Body : BEGIN -->
          <table cellspacing="0" cellpadding="0" border="0" align="center" bgcolor="#ffffff" width="100%" style="max-width: 600px;">
            
            <!-- Hero Image, Flush : BEGIN -->
            <tr>
              <td class="full-width-image" align="center" ><img src="https://ap2.crewcare.app/img/head-notification.jpg" width="600" alt="alt_text" border="0" style="width: 100%; max-width: 600px; height: auto;"></td>
            </tr>
            <!-- Hero Image, Flush : END --> 
            
            <!-- 1 Column Text : BEGIN -->
            <tr>
              <td><table cellspacing="0" cellpadding="0" border="0" width="100%">
                  <tr>
                    <td style="padding: 40px; font-family: sans-serif; font-size: 15px; mso-height-rule: exactly; line-height: 20px; color: #555555;">
						<p> Hola {{$nombre}}</p>
                      <p>&nbsp;Esta es una notificación de un posible contagio.&nbsp; {{$usuario}} {{$usuariol}} reporto uno o más sintomas. <br>
                        <br>
                        @if($fiebre == 1)
                        <p>&nbsp;Fiebre</p>
                        @else
                        
                        @endif

                        @if($dificultad == 1)
                        <p>&nbsp;Dificultad para respirar</p>
                        @else
                        
                        @endif

                        @if($articulaciones == 1)
                        <p>&nbsp;Dolor de articulaciones</p>
                        @else
                        @endif

                        @if($gusto == 1)
                        <p>&nbsp;Perdida del gusto o del olfato</p>
                        @else
                        
                        @endif

                        @if($espalda == 1)
                        <p>&nbsp;Dolor de espalda baja</p>
                        @else
                        
                        @endif

                        @if($tos == 1)
                        <p>&nbsp;Tos</p>
                        @else
                        
                        @endif

                        @if($cabeza == 1)
                        <p>&nbsp;Dolor de cabeza</p>
                        @else
                        
                        @endif

                        @if($ojos == 1)
                        <p>&nbsp;Ojos rojos</p>
                        @else
                        
                        @endif

                        <p>&nbsp;{{$otros}}</p>
                        @if($convivencia == 1)
                        <p>&nbsp;Contacto con caso positivo</p>
                        @else
                        
                        @endif
                        <br>

                        <br>

                        Se le ha indicado permancer aislado en donde se encuentra.&nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
                        <!-- Button : Begin -->
                      </p>
<table cellspacing="0" cellpadding="0" border="0" align="center" style="margin: auto;">
  
</table>
                      
                      <!-- Button : END --> 
                      <br>
                       </td>
                  </tr>
                </table></td>
            </tr>
            <!-- 1 Column Text : BEGIN --> 
            
            <!-- Two Even Columns : BEGIN -->
            
            
          </table>
          <!-- Email Body : END --> 
          
          <!-- Email Footer : BEGIN -->
          
          <!-- Email Footer : END --> 
          
          <!--[if (gte mso 9)|(IE)]>
            </td>
            </tr>
            </table>
            <![endif]--> 
        </div>
      </center></td>
  </tr>
</table>
        </tr>
    </tbody>

</table>
</div>
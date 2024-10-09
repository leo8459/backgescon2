<!DOCTYPE html>
<html lang="en" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:v="urn:schemas-microsoft-com:vml">

<head>
   <title>Notificación de Paquete Entregado</title>
   <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
   <meta content="width=device-width, initial-scale=1.0" name="viewport" />
   <style>
      * {
         box-sizing: border-box;
      }
      body {
         margin: 0;
         padding: 0;
      }
      a[x-apple-data-detectors] {
         color: inherit !important;
         text-decoration: inherit !important;
      }
      #MessageViewBody a {
         color: inherit;
         text-decoration: none;
      }
      p {
         line-height: inherit
      }
      .desktop_hide,
      .desktop_hide table {
         mso-hide: all;
         display: none;
         max-height: 0px;
         overflow: hidden;
      }
      @media (max-width:700px) {
         .row-content {
            width: 100% !important;
         }
         .stack .column {
            width: 100%;
            display: block;
         }
         .desktop_hide {
            display: table !important;
         }
      }
   </style>
</head>

<body style="background-color: #FFFFFF; margin: 0; padding: 0;">
   <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #FFFFFF;" width="100%">
      <tr>
         <td>
            <table align="center" border="0" cellpadding="0" cellspacing="0" style="width: 680px; margin: 0 auto;" width="680">
               <tr>
                  <td style="padding: 20px; text-align: center;">
                     <!-- Imagen del logo -->
                     <img src="{{ asset('images/logo.png') }}" alt="Logo" style="max-width: 150px; margin-bottom: 20px;" />
                  </td>
               </tr>
               <tr>
                  <td style="padding: 20px;">
                     <h1 style="color: #17898d; font-family: Arial, sans-serif; font-size: 24px;">Notificación de Paquete Entregado</h1>
                     <h2 style="color: #171614; font-family: Arial, sans-serif; font-size: 20px;"><strong>{{ $solicitude->sucursale->nombre }}</strong></h2>
                     <p style="color: #7a7a7a; font-family: Arial, sans-serif; font-size: 16px;">
                        Le informamos que la correspondencia con la guía <strong>{{ $guia }}</strong> ha sido entregada exitosamente el {{ $fecha_d }}.
                     </p>
                     <p style="color: #7a7a7a; font-family: Arial, sans-serif; font-size: 16px;">
                        Precio: {{ $destinatario }} Bs
                     </p>
                     <p style="color: #7a7a7a; font-family: Arial, sans-serif; font-size: 16px;">
                        En caso de no estar conforme, contáctese con nosotros:
                     </p>
                     <div style="margin: 20px 0;">
                        <a href="https://wa.me/59178877682?text=Hola,%20quisiera%20consultar%20sobre%20la%20entrega%20de%20mi%20paquete%20con%20guía%20{{ $guia }}" 
                           style="background-color:#0c00af; color:#ffffff; padding: 10px 20px; text-decoration: none; border-radius: 4px;">
                           Contactar por WhatsApp
                        </a>
                     </div>
                     <div style="text-align: center;">
                        <img src="https://i.ibb.co/xLhwTH9/Beefree-logo.png" alt="Logo" style="max-width: 200px; margin-top: 20px;" />
                     </div>
                     <p style="text-align: center; font-size: 12px; color: #7a7a7a;">
                        Gracias por utilizar nuestros servicios.
                     </p>
                  </td>
               </tr>
            </table>
         </td>
      </tr>
   </table>
</body>

</html>

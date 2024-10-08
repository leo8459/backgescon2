<?php
namespace App\Mail;

use App\Models\Solicitude;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaqueteEntregadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public $solicitude;

    public function __construct(Solicitude $solicitude)
    {
        $this->solicitude = $solicitude;
    }

    public function build()
{
    return $this->view('emails.paquete_entregado')
                ->subject('Notificación de Paquete Entregado')
                ->with([
                    'guia' => $this->solicitude->guia,
                    'fecha_d' => $this->solicitude->fecha_d,
                    'destinatario' => $this->solicitude->nombre_d,
                ]);
}

}

<?php

namespace App\Http\Controllers;

use App\Models\Solicitude;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardCarteroController extends Controller
{
    public function solicitudesHoy(Request $request)
    {
        $carteroId = $request->query('cartero_entrega_id');
        $hoy = Carbon::today()->toDateString();

        $query = Solicitude::whereDate('fecha', $hoy);

        if ($carteroId) {
            $query->where('cartero_entrega_id', $carteroId);
        }

        $totalSolicitudesHoy = $query->count();

        return response()->json(['total' => $totalSolicitudesHoy]);
    }

    public function solicitudesEstado0(Request $request)
    {
        return $this->solicitudesPorEstado($request, 0);
    }

    public function solicitudesEstado1(Request $request)
    {
        $query = Solicitude::where('estado', 1);
        $totalSolicitudes = $query->count();
        return response()->json(['total' => $totalSolicitudes]);
    }

    public function solicitudesEstado2(Request $request)
    {
        return $this->solicitudesPorEstado($request, 2);
    }

    public function solicitudesEstado3(Request $request)
    {
        return $this->solicitudesPorEstados($request, [3, 4]); // Cambiado para reconocer estado 3 y 4
    }

    public function solicitudesPorCarteroRecogida(Request $request)
    {
        $carteroId = $request->query('cartero_recogida_id');

        $query = Solicitude::query();

        if ($carteroId) {
            $query->where('cartero_recogida_id', $carteroId);
        }

        $totalSolicitudes = $query->count();

        return response()->json(['total' => $totalSolicitudes]);
    }

    private function solicitudesPorEstado(Request $request, $estado)
    {
        $carteroId = $request->query('cartero_entrega_id');

        $query = Solicitude::where('estado', $estado);

        if ($carteroId) {
            $query->where('cartero_entrega_id', $carteroId);
        }

        $totalSolicitudes = $query->count();

        return response()->json(['total' => $totalSolicitudes]);
    }

    private function solicitudesPorEstados(Request $request, array $estados)
    {
        $carteroId = $request->query('cartero_entrega_id');

        $query = Solicitude::whereIn('estado', $estados);

        if ($carteroId) {
            $query->where('cartero_entrega_id', $carteroId);
        }

        $totalSolicitudes = $query->count();

        return response()->json(['total' => $totalSolicitudes]);
    }

    public function solicitudesEstado1Hoy(Request $request)
    {
        $hoy = Carbon::today()->toDateString();

        $totalSolicitudes = Solicitude::where('estado', 1)
                            ->whereDate('fecha', $hoy)
                            ->count();

        return response()->json(['total' => $totalSolicitudes]);
    }

    public function solicitudesEstado2Hoy(Request $request)
    {
        $carteroId = $request->query('cartero_entrega_id');
        $hoy = Carbon::today()->toDateString();

        $query = Solicitude::where('estado', 2)
                            ->whereDate('fecha', $hoy);

        if ($carteroId) {
            $query->where('cartero_entrega_id', $carteroId);
        }

        $totalSolicitudes = $query->count();

        return response()->json(['total' => $totalSolicitudes]);
    }

    public function solicitudesEstado3Hoy(Request $request)
    {
        $carteroId = $request->query('cartero_entrega_id');
        $hoy = Carbon::today()->toDateString();

        $query = Solicitude::whereIn('estado', [3, 4]) // Cambiado para reconocer estado 3 y 4
                            ->whereDate('fecha', $hoy);

        if ($carteroId) {
            $query->where('cartero_entrega_id', $carteroId);
        }

        $totalSolicitudes = $query->count();

        return response()->json(['total' => $totalSolicitudes]);
    }

    public function solicitudesEstado5Hoy(Request $request)
    {
        $carteroId = $request->query('cartero_recogida_id');
        $hoy = Carbon::today()->toDateString();

        $query = Solicitude::where('estado', 5)
                            ->whereDate('fecha', $hoy);

        if ($carteroId) {
            $query->where('cartero_recogida_id', $carteroId);
        }

        $totalSolicitudes = $query->count();

        return response()->json(['total' => $totalSolicitudes]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\LoginAttempt;
use Illuminate\Support\Facades\DB;

class SecurityDashboardController extends Controller
{
    public function index()
    {
        // Indicadores de las últimas 24 horas
        $since = now()->subHours(24);
        $stats = [];

        foreach (['success', 'failed', 'blocked'] as $status) {
            $stats[$status] = LoginAttempt::where('status', $status)
                ->where('created_at', '>=', $since)
                ->count();
        }

        // IPs sospechosas: 3 o más fallos en los últimos 5 minutos
        $suspiciousIps = LoginAttempt::select('ip_address', DB::raw('COUNT(*) as total'))
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->groupBy('ip_address')
            ->havingRaw('COUNT(*) >= 3')
            ->orderByDesc('total')
            ->get();

        // Últimos eventos (consulta "Últimos eventos" del paso 7)
        $recent = LoginAttempt::latest('created_at')->latest('id')->limit(15)->get();

        return view('security-dashboard', compact('stats', 'suspiciousIps', 'recent'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\LoginAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Login propio que registra cada intento y aplica las reglas de detección:
 *   - IP bloqueada        -> 429 y evento "blocked"
 *   - Login correcto      -> INFO y evento "success"
 *   - Login fallido       -> WARNING y evento "failed"
 *   - 3 o 4 fallos (5 min) -> ALERT por posible fuerza bruta
 *   - 5 fallos (5 min)     -> bloqueo de la IP por 10 minutos y CRITICAL
 */
class SecurityLoginController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $ip = $request->ip();
        $key = 'security:block:'.hash('sha256', $ip);

        // 1. Si la IP está bloqueada, se rechaza sin intentar autenticar
        if (Cache::has($key)) {
            $this->record($request, 'blocked');

            Log::channel('security')->warning('Intento desde IP bloqueada', ['ip' => $ip]);

            return response()->json(['message' => 'Acceso temporalmente restringido.'], 429);
        }

        // 2. Login correcto
        if (Auth::attempt($data)) {
            $request->session()->regenerate(); // evita fijación de sesión

            $this->record($request, 'success');

            Log::channel('security')->info('Inicio de sesión exitoso', ['user_id' => Auth::id(), 'ip' => $ip]);

            return response()->json(['message' => 'Autenticación correcta']);
        }

        // 3. Login fallido: se registra y se cuentan los fallos de los últimos 5 minutos
        $this->record($request, 'failed');

        $count = LoginAttempt::where('ip_address', $ip)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        Log::channel('security')->warning('Autenticación fallida', ['ip' => $ip, 'failed_attempts' => $count]);

        // 4. Respuesta según la cantidad de fallos
        if ($count >= 5) {
            Cache::put($key, true, now()->addMinutes(10));

            Log::channel('security')->critical('Bloqueo temporal por intentos fallidos', [
                'ip' => $ip,
                'failed_attempts' => $count,
                'duration_minutes' => 10,
            ]);
        } elseif ($count >= 3) {
            Log::channel('security')->alert('Posible ataque de fuerza bruta', ['ip' => $ip, 'failed_attempts' => $count]);
        }

        return response()->json(['message' => 'Credenciales inválidas'], 401);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/security-login');
    }

    private function record(Request $request, string $status): void
    {
        LoginAttempt::create([
            'identifier' => mb_strtolower($request->input('email', '')),
            'ip_address' => $request->ip(),
            'status' => $status,
            'user_agent' => substr($request->userAgent() ?? '', 0, 255),
            'created_at' => now(),
        ]);
    }
}

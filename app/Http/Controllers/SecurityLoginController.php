<?php

namespace App\Http\Controllers;

use App\Models\LoginAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Login propio que registra cada intento y aplica las reglas de detección.
 *
 * Por IP (enunciado, ventana de 5 minutos):
 *   - IP bloqueada         -> 429 y evento "blocked"
 *   - Login correcto       -> INFO y evento "success"
 *   - Login fallido        -> WARNING y evento "failed"
 *   - 3 o 4 fallos         -> ALERT por posible fuerza bruta
 *   - 5 fallos             -> bloqueo de la IP por 10 minutos y CRITICAL
 *
 * Por cuenta (mejora, ventana de 15 minutos, desde cualquier IP):
 *   - Cuenta bloqueada     -> 429 y evento "blocked"
 *   - 3 o 4 fallos         -> ALERT por posible ataque dirigido
 *   - 5 fallos             -> bloqueo de la cuenta por 10 minutos y CRITICAL
 *   Detecta ataques distribuidos: muchas IPs distintas contra la misma cuenta.
 */
class SecurityLoginController extends Controller
{
    private const ACCOUNT_WINDOW_MINUTES = 15;

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $ip = $request->ip();
        $email = mb_strtolower($data['email']);
        $key = 'security:block:'.hash('sha256', $ip);
        $accountKey = 'security:block:account:'.hash('sha256', $email);

        // 1. Si la IP está bloqueada, se rechaza sin intentar autenticar
        if (Cache::has($key)) {
            $this->record($request, 'blocked');

            Log::channel('security')->warning('Intento desde IP bloqueada', ['ip' => $ip]);

            return response()->json(['message' => 'Acceso temporalmente restringido.'], 429);
        }

        // 1b. Mejora: si la cuenta está bloqueada, se rechaza venga de la IP que venga
        if (Cache::has($accountKey)) {
            $this->record($request, 'blocked');

            Log::channel('security')->warning('Intento contra cuenta bloqueada', ['ip' => $ip, 'account' => $email]);

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

        // 4. Respuesta según la cantidad de fallos de la IP
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

        // 5. Mejora: fallos contra esta cuenta en 15 minutos, desde cualquier IP
        $this->checkAccount($email, $accountKey, $ip);

        return response()->json(['message' => 'Credenciales inválidas'], 401);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/security-login');
    }

    /**
     * Detección por cuenta: muchas IPs distintas contra la misma cuenta no llegan
     * al umbral por IP, pero sí al de la cuenta.
     */
    private function checkAccount(string $email, string $accountKey, string $ip): void
    {
        $attempts = LoginAttempt::where('identifier', $email)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subMinutes(self::ACCOUNT_WINDOW_MINUTES));

        $failures = (clone $attempts)->count();
        $distinctIps = (clone $attempts)->distinct()->count('ip_address');

        $context = ['ip' => $ip, 'account' => $email, 'failed_attempts' => $failures, 'distinct_ips' => $distinctIps];

        if ($failures >= 5) {
            Cache::put($accountKey, true, now()->addMinutes(10));

            Log::channel('security')->critical('Bloqueo temporal de cuenta', $context + ['duration_minutes' => 10]);
        } elseif ($failures >= 3) {
            Log::channel('security')->alert('Posible ataque dirigido a una cuenta', $context);
        }
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

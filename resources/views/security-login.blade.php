@extends('layouts.app')

@section('title', 'Login de seguridad')

@section('content')
    <div class="flex min-h-screen flex-col items-center justify-center px-5 py-10">
        <div class="flex items-center gap-3">
            {{-- Escudo: identidad del proyecto de seguridad --}}
            <svg class="size-10" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <path d="M24 5 9 11v11c0 9.5 6.4 18.3 15 21 8.6-2.7 15-11.5 15-21V11L24 5Z" stroke="currentColor" stroke-opacity=".3" stroke-width="3.5" stroke-linejoin="round"/>
                <path d="m17 24 5 5 9-10" stroke="var(--color-signal)" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div>
                <p class="font-mono text-[11px] uppercase tracking-[0.3em] text-muted">Logs de seguridad</p>
                <p class="font-display text-xl font-bold tracking-tight text-white">Prueba de autenticación</p>
            </div>
        </div>

        <div class="mt-8 w-full rounded-2xl border border-edge bg-panel/80 p-6 shadow-2xl shadow-black/40 backdrop-blur sm:max-w-md sm:p-8">
            <h1 class="font-display text-2xl font-bold tracking-tight text-white">Iniciar sesión</h1>
            <p class="mt-1 font-mono text-xs text-muted">Cada intento queda registrado en la base y en el log de seguridad.</p>

            {{-- Respuesta del servidor (JSON del controlador) --}}
            <div id="result" class="mt-5 hidden rounded-lg border px-3 py-2 font-mono text-xs" role="status" aria-live="polite"></div>

            <form id="login-form" method="POST" action="/security-login" class="mt-5 space-y-5">
                @csrf

                <div>
                    <label for="email" class="block font-mono text-xs uppercase tracking-widest text-muted">Email</label>
                    <input id="email" type="email" name="email" required autofocus autocomplete="username" placeholder="usuario@ejemplo.com"
                           class="mt-2 block w-full rounded-lg border border-edge bg-ink px-3 py-2.5 font-mono text-sm text-white placeholder:text-muted/70 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/25">
                </div>

                <div>
                    <label for="password" class="block font-mono text-xs uppercase tracking-widest text-muted">Contraseña</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••"
                           class="mt-2 block w-full rounded-lg border border-edge bg-ink px-3 py-2.5 font-mono text-sm text-white placeholder:text-muted/70 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/25">
                </div>

                <button type="submit" id="submit"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-signal px-4 py-2.5 font-mono text-xs font-bold uppercase tracking-widest text-ink transition hover:bg-signal/85 focus:outline-none focus:ring-2 focus:ring-signal/50 focus:ring-offset-2 focus:ring-offset-ink active:scale-[0.98] disabled:opacity-50">
                    Ingresar
                </button>
            </form>
        </div>

        <p class="mt-8 font-mono text-xs text-muted">UTN FRRe · Programación IV · TP Logging y detección de incidentes</p>
    </div>

    <script>
        // Envía el formulario con fetch y muestra el JSON del controlador según el código HTTP.
        // Sin JavaScript, el formulario se envía igual y el navegador muestra el JSON.
        const form = document.getElementById('login-form');
        const result = document.getElementById('result');
        const button = document.getElementById('submit');

        const estilos = {
            200: ['border-ok/30', 'bg-ok/10', 'text-ok'],
            401: ['border-warn/30', 'bg-warn/10', 'text-warn'],
            422: ['border-warn/30', 'bg-warn/10', 'text-warn'],
            429: ['border-danger/30', 'bg-danger/10', 'text-danger'],
        };

        function mostrar(status, mensaje) {
            result.className = 'mt-5 rounded-lg border px-3 py-2 font-mono text-xs';
            result.classList.add(...(estilos[status] ?? ['border-edge', 'text-muted']));
            result.textContent = `HTTP ${status} · ${mensaje}`;
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            button.disabled = true;

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: new FormData(form),
                });
                const data = await res.json().catch(() => ({}));

                mostrar(res.status, data.message ?? 'Respuesta inesperada');

                if (res.ok) {
                    setTimeout(() => window.location.href = '/', 800);
                    return;
                }
                form.password.value = '';
                form.password.focus();
            } catch {
                mostrar(0, 'No se pudo conectar con el servidor');
            } finally {
                button.disabled = false;
            }
        });
    </script>
@endsection

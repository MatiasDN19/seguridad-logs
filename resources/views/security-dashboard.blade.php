@extends('layouts.app')

@section('title', 'Panel de seguridad')

{{-- Se recarga sola cada 10 s para ver los intentos a medida que llegan --}}
@push('head')
    <meta http-equiv="refresh" content="10">
@endpush

@section('content')
    <main class="mx-auto max-w-6xl px-5 py-8 sm:px-8 sm:py-12">

        {{-- Encabezado --}}
        <header class="flex flex-col gap-6 border-b border-edge pb-8 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="font-mono text-xs uppercase tracking-[0.3em] text-muted">Panel de seguridad</p>
                <h1 class="mt-2 font-display text-4xl font-bold tracking-tight text-white sm:text-5xl">Accesos</h1>
                <p class="mt-2 font-mono text-sm text-muted">
                    Autenticaciones de las últimas 24 h <span class="text-edge">/</span> canal <span class="text-slate-300">security</span>
                </p>
            </div>

            <nav class="flex flex-wrap items-center gap-2 font-mono text-xs">
                <a href="{{ route('home') }}" class="rounded-full border border-edge px-3 py-1.5 text-muted transition hover:border-slate-500 hover:text-white">Inicio</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-full border border-edge px-3 py-1.5 text-muted transition hover:border-slate-500 hover:text-white">Cerrar sesión</button>
                </form>
            </nav>
        </header>

        {{-- Indicadores --}}
        @php
            $cards = [
                ['key' => 'success', 'label' => 'Exitosos',   'color' => 'text-ok',     'dot' => 'bg-ok',     'hint' => 'Logins correctos · INFO'],
                ['key' => 'failed',  'label' => 'Fallidos',   'color' => 'text-warn',   'dot' => 'bg-warn',   'hint' => 'Credenciales inválidas · WARNING'],
                ['key' => 'blocked', 'label' => 'Bloqueados', 'color' => 'text-danger', 'dot' => 'bg-danger', 'hint' => 'Rechazados con HTTP 429'],
            ];
        @endphp
        <section class="mt-8 grid gap-4 sm:grid-cols-3" aria-label="Indicadores de las últimas 24 horas">
            @foreach ($cards as $c)
                <article class="rounded-2xl border border-edge bg-panel/80 p-6">
                    <div class="flex items-center gap-2">
                        <span class="size-2 rounded-full {{ $c['dot'] }}"></span>
                        <h2 class="font-mono text-xs uppercase tracking-widest text-muted">{{ $c['label'] }}</h2>
                    </div>
                    <p class="mt-4 font-mono text-6xl font-bold tabular-nums {{ $c['color'] }}">{{ $stats[$c['key']] }}</p>
                    <p class="mt-2 font-mono text-xs text-muted">{{ $c['hint'] }}</p>
                </article>
            @endforeach
        </section>

        {{-- IPs sospechosas --}}
        <section class="mt-4 rounded-2xl border border-edge bg-panel/80 p-6" aria-label="IPs sospechosas">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-display text-lg font-medium text-white">IPs sospechosas</h2>
                <p class="font-mono text-xs text-muted">3 o más fallos en los últimos 5 minutos</p>
            </div>

            @if ($suspiciousIps->isEmpty())
                <p class="mt-6 flex items-center gap-2 font-mono text-sm text-ok">
                    <span class="size-2 rounded-full bg-ok"></span> Sin actividad sospechosa
                </p>
            @else
                <table class="mt-4 w-full font-mono text-sm">
                    <thead>
                        <tr class="border-b border-edge text-left text-xs uppercase tracking-widest text-muted">
                            <th class="py-3 font-medium">IP</th>
                            <th class="py-3 font-medium">Fallos</th>
                            <th class="py-3 text-right font-medium">Clasificación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($suspiciousIps as $item)
                            @php $critico = $item->total >= 5; @endphp
                            <tr class="border-b border-edge/60 last:border-0">
                                <td class="py-3 text-white">{{ $item->ip_address }}</td>
                                <td class="py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="w-6 tabular-nums">{{ $item->total }}</span>
                                        {{-- Barra: se llena al llegar a 5 fallos (umbral de bloqueo) --}}
                                        <span class="h-1.5 w-24 overflow-hidden rounded-full bg-edge">
                                            <span class="block h-full rounded-full {{ $critico ? 'bg-danger' : 'bg-warn' }}"
                                                  style="width: {{ min($item->total, 5) * 20 }}%"></span>
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 text-right">
                                    <span class="rounded-full border px-2.5 py-1 text-[11px] font-medium uppercase tracking-widest
                                        {{ $critico ? 'border-danger/30 bg-danger/10 text-danger' : 'border-warn/30 bg-warn/10 text-warn' }}">
                                        {{ $critico ? 'Crítico' : 'Alerta' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        {{-- Últimos eventos --}}
        <section class="mt-4 rounded-2xl border border-edge bg-panel/80 p-6" aria-label="Últimos eventos">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-display text-lg font-medium text-white">Últimos eventos</h2>
                <p class="font-mono text-xs text-muted">hora UTC · identificador = email usado en el intento</p>
            </div>

            @if ($recent->isEmpty())
                <p class="mt-6 font-mono text-sm text-muted">Todavía no hay intentos registrados.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full font-mono text-sm">
                        <thead>
                            <tr class="border-b border-edge text-left text-xs uppercase tracking-widest text-muted">
                                <th class="py-3 pr-4 font-medium">Hora</th>
                                <th class="py-3 pr-4 font-medium">Estado</th>
                                <th class="py-3 pr-4 font-medium">IP</th>
                                <th class="py-3 pr-4 font-medium">Identificador</th>
                                <th class="py-3 font-medium">Navegador</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recent as $e)
                                @php
                                    $badge = [
                                        'success' => 'border-ok/30 bg-ok/10 text-ok',
                                        'failed'  => 'border-warn/30 bg-warn/10 text-warn',
                                        'blocked' => 'border-danger/30 bg-danger/10 text-danger',
                                    ][$e->status] ?? 'border-edge text-muted';
                                @endphp
                                <tr class="border-b border-edge/60 last:border-0">
                                    <td class="py-3 pr-4 whitespace-nowrap tabular-nums text-slate-300">{{ $e->created_at->format('d/m H:i:s') }}</td>
                                    <td class="py-3 pr-4">
                                        <span class="rounded-full border px-2.5 py-1 text-[11px] font-medium uppercase tracking-widest {{ $badge }}">{{ $e->status }}</span>
                                    </td>
                                    <td class="py-3 pr-4 whitespace-nowrap text-white">{{ $e->ip_address }}</td>
                                    <td class="py-3 pr-4 text-muted">{{ $e->identifier ?: '—' }}</td>
                                    <td class="max-w-[220px] truncate py-3 text-muted" title="{{ $e->user_agent }}">{{ $e->user_agent ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <footer class="mt-10 font-mono text-xs text-muted">
            UTN FRRe · Programación IV · TP Logging y detección de incidentes (proyecto nuevo) · se actualiza cada 10 s
        </footer>
    </main>
@endsection

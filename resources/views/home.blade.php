@extends('layouts.app')

@section('title', 'Sesión iniciada')

@section('content')
    <div class="flex min-h-screen flex-col items-center justify-center px-5 py-10">
        <div class="w-full rounded-2xl border border-edge bg-panel/80 p-6 sm:max-w-md sm:p-8">
            <p class="flex items-center gap-2 font-mono text-xs uppercase tracking-widest text-ok">
                <span class="size-2 rounded-full bg-ok"></span> Sesión iniciada
            </p>
            <h1 class="mt-4 font-display text-2xl font-bold tracking-tight text-white">Hola, {{ auth()->user()->name }}</h1>
            <p class="mt-1 font-mono text-xs text-muted">{{ auth()->user()->email }}</p>

            <div class="mt-6 space-y-3">
                @can('viewSecurityDashboard')
                    <a href="{{ route('security.dashboard') }}"
                       class="flex w-full items-center justify-center rounded-lg bg-signal px-4 py-2.5 font-mono text-xs font-bold uppercase tracking-widest text-ink transition hover:bg-signal/85">
                        Abrir panel de seguridad
                    </a>
                @else
                    <p class="rounded-lg border border-dashed border-edge px-3 py-2 font-mono text-xs text-muted">
                        Tu cuenta no tiene permisos de administrador para ver el panel de seguridad.
                    </p>
                @endcan

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex w-full items-center justify-center rounded-lg border border-edge px-4 py-2.5 font-mono text-xs uppercase tracking-widest text-slate-300 transition hover:border-slate-500 hover:text-white">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection

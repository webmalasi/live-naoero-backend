@extends('layouts.admin')

@section('title', 'PostgreSQL & Redis System Operations')

@section('content')
<div class="space-y-6">

    <!-- HEADER & ACTION BUTTONS -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-navy">PostgreSQL & Redis Infrastructure</h1>
            <p class="text-xs text-gray-500 mt-0.5">Live telemetry for database connections, Redis key-value storage, session persistence, and application caches.</p>
        </div>

        <div class="flex items-center gap-2">
            <!-- Flush Cache -->
            <form method="POST" action="{{ route('admin.system.cache.flush') }}" onsubmit="return confirm('Flush application & Redis cache?')">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-xl bg-white border border-amber-300 text-amber-900 hover:bg-amber-50 font-bold text-xs shadow-sm transition-all flex items-center gap-1.5">
                    <span>⚡ Flush Redis Cache</span>
                </button>
            </form>

            <!-- Optimize App -->
            <form method="POST" action="{{ route('admin.system.optimize') }}">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-xl bg-navy hover:bg-[#062663] text-white font-bold text-xs shadow-md shadow-navy/20 hover:scale-105 transition-all flex items-center gap-1.5">
                    <span>🚀 Optimize System</span>
                </button>
            </form>
        </div>
    </div>

    <!-- 2 MAIN INFRASTRUCTURE CARDS -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- POSTGRESQL / DATABASE CARD -->
        <div class="naoero-card p-6 sm:p-8 space-y-6">
            <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xl shadow-sm">
                        🐘
                    </div>
                    <div>
                        <h2 class="text-lg font-extrabold text-navy">Database Engine</h2>
                        <p class="text-xs text-gray-500">PostgreSQL / Active Relational DB</p>
                    </div>
                </div>

                @if($dbConnected)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Connected
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span> Disconnected
                    </span>
                @endif
            </div>

            <div class="space-y-3 text-xs">
                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between">
                    <span class="text-gray-500 font-semibold">Active Driver:</span>
                    <span class="font-bold text-navy uppercase font-mono">{{ $dbDriver }}</span>
                </div>

                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between">
                    <span class="text-gray-500 font-semibold">Database Host:</span>
                    <span class="font-mono text-gray-800">{{ $dbHost }}</span>
                </div>

                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between">
                    <span class="text-gray-500 font-semibold">Database Name:</span>
                    <span class="font-mono text-gray-800">{{ $dbDatabase }}</span>
                </div>

                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between">
                    <span class="text-gray-500 font-semibold">Port:</span>
                    <span class="font-mono text-gray-800">{{ config("database.connections.{$dbDriver}.port", '5432') }}</span>
                </div>
            </div>

            @if($dbError)
                <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                    <strong class="block mb-1 font-bold">Database Error:</strong>
                    {{ $dbError }}
                </div>
            @endif
        </div>

        <!-- REDIS CACHE & SESSION CARD -->
        <div class="naoero-card p-6 sm:p-8 space-y-6">
            <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center font-bold text-xl shadow-sm">
                        ⚡
                    </div>
                    <div>
                        <h2 class="text-lg font-extrabold text-navy">Redis In-Memory Engine</h2>
                        <p class="text-xs text-gray-500">Predis / Cache & Session Management</p>
                    </div>
                </div>

                @if($redisConnected)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Active
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                        <span class="w-2 h-2 rounded-full bg-accent"></span> Ready / Configured
                    </span>
                @endif
            </div>

            <div class="space-y-3 text-xs">
                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between">
                    <span class="text-gray-500 font-semibold">Redis Client:</span>
                    <span class="font-bold text-navy font-mono">{{ $redisClient }}</span>
                </div>

                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between">
                    <span class="text-gray-500 font-semibold">Redis Host:</span>
                    <span class="font-mono text-gray-800">{{ $redisHost }}:{{ $redisPort }}</span>
                </div>

                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between">
                    <span class="text-gray-500 font-semibold">Cache Store:</span>
                    <span class="font-mono text-gray-800 uppercase font-bold">{{ $envInfo['cache_driver'] }}</span>
                </div>

                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between">
                    <span class="text-gray-500 font-semibold">Session Driver:</span>
                    <span class="font-mono text-gray-800 uppercase font-bold">{{ $envInfo['session_driver'] }}</span>
                </div>
            </div>
        </div>

    </div>

    <!-- RUNTIME & ENVIRONMENT SPECS -->
    <div class="naoero-card p-6 sm:p-8 space-y-4">
        <h3 class="text-base font-extrabold text-navy border-b border-gray-100 pb-3 flex items-center gap-2">
            <span>⚙️ Environment & Runtime Telemetry</span>
        </h3>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100">
                <span class="text-gray-400 font-bold uppercase tracking-wider block mb-1">PHP Version</span>
                <span class="text-base font-extrabold text-navy font-mono">{{ $envInfo['php_version'] }}</span>
            </div>

            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100">
                <span class="text-gray-400 font-bold uppercase tracking-wider block mb-1">Laravel Framework</span>
                <span class="text-base font-extrabold text-navy font-mono">v{{ $envInfo['laravel_version'] }}</span>
            </div>

            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100">
                <span class="text-gray-400 font-bold uppercase tracking-wider block mb-1">Environment Mode</span>
                <span class="text-base font-extrabold text-emerald-600 uppercase font-mono">{{ $envInfo['app_env'] }}</span>
            </div>

            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100">
                <span class="text-gray-400 font-bold uppercase tracking-wider block mb-1">Debug Mode</span>
                <span class="text-base font-extrabold text-navy">{{ $envInfo['app_debug'] }}</span>
            </div>
        </div>
    </div>

</div>
@endsection

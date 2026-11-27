@extends('layouts.admin')

@section('title', 'Global Locations Master (Countries, States & Cities)')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{
    activeTab: '{{ $tab }}',
    showCountryModal: false,
    showStateModal: false,
    showCityModal: false,
    countryModalMode: 'create',
    stateModalMode: 'create',
    cityModalMode: 'create',
    
    countryForm: {
        id: null,
        name: '',
        iso2: '',
        iso3: '',
        phone_code: '',
        currency: '',
        currency_symbol: '',
        currency_name: '',
        capital: '',
        region: 'Oceania',
        subregion: '',
        flag_emoji: '',
        latitude: '',
        longitude: '',
        sort_order: 0,
        is_active: true,
        actionUrl: '{{ route('admin.locations.countries.store') }}',
        method: 'POST'
    },
    stateForm: {
        id: null,
        country_id: '{{ $countryFilter ?: ($allCountries->first()->id ?? '') }}',
        name: '',
        state_code: '',
        type: 'State',
        latitude: '',
        longitude: '',
        sort_order: 0,
        is_active: true,
        actionUrl: '{{ route('admin.locations.states.store') }}',
        method: 'POST'
    },
    cityForm: {
        id: null,
        country_id: '{{ $countryFilter ?: ($allCountries->first()->id ?? '') }}',
        state_id: '{{ $stateFilter ?: '' }}',
        name: '',
        postal_code: '',
        iata_code: '',
        latitude: '',
        longitude: '',
        sort_order: 0,
        is_active: true,
        actionUrl: '{{ route('admin.locations.cities.store') }}',
        method: 'POST',
        stateList: []
    },

    openCreateCountry() {
        this.countryModalMode = 'create';
        this.countryForm = {
            id: null,
            name: '',
            iso2: '',
            iso3: '',
            phone_code: '',
            currency: '',
            currency_symbol: '',
            currency_name: '',
            capital: '',
            region: 'Oceania',
            subregion: '',
            flag_emoji: '',
            latitude: '',
            longitude: '',
            sort_order: 0,
            is_active: true,
            actionUrl: '{{ route('admin.locations.countries.store') }}',
            method: 'POST'
        };
        this.showCountryModal = true;
    },

    openEditCountry(item) {
        this.countryModalMode = 'edit';
        this.countryForm = {
            id: item.id,
            name: item.name || '',
            iso2: item.iso2 || '',
            iso3: item.iso3 || '',
            phone_code: item.phone_code || '',
            currency: item.currency || '',
            currency_symbol: item.currency_symbol || '',
            currency_name: item.currency_name || '',
            capital: item.capital || '',
            region: item.region || 'Oceania',
            subregion: item.subregion || '',
            flag_emoji: item.flag_emoji || '',
            latitude: item.latitude !== null ? item.latitude : '',
            longitude: item.longitude !== null ? item.longitude : '',
            sort_order: item.sort_order || 0,
            is_active: !!item.is_active,
            actionUrl: `/admin/locations/countries/${item.id}`,
            method: 'PUT'
        };
        this.showCountryModal = true;
    },

    openCreateState() {
        this.stateModalMode = 'create';
        this.stateForm = {
            id: null,
            country_id: '{{ $countryFilter ?: ($allCountries->first()->id ?? '') }}',
            name: '',
            state_code: '',
            type: 'State',
            latitude: '',
            longitude: '',
            sort_order: 0,
            is_active: true,
            actionUrl: '{{ route('admin.locations.states.store') }}',
            method: 'POST'
        };
        this.showStateModal = true;
    },

    openEditState(item) {
        this.stateModalMode = 'edit';
        this.stateForm = {
            id: item.id,
            country_id: item.country_id,
            name: item.name || '',
            state_code: item.state_code || '',
            type: item.type || 'State',
            latitude: item.latitude !== null ? item.latitude : '',
            longitude: item.longitude !== null ? item.longitude : '',
            sort_order: item.sort_order || 0,
            is_active: !!item.is_active,
            actionUrl: `/admin/locations/states/${item.id}`,
            method: 'PUT'
        };
        this.showStateModal = true;
    },

    async openCreateCity() {
        this.cityModalMode = 'create';
        let defaultCountryId = '{{ $countryFilter ?: ($allCountries->first()->id ?? '') }}';
        this.cityForm = {
            id: null,
            country_id: defaultCountryId,
            state_id: '',
            name: '',
            postal_code: '',
            iata_code: '',
            latitude: '',
            longitude: '',
            sort_order: 0,
            is_active: true,
            actionUrl: '{{ route('admin.locations.cities.store') }}',
            method: 'POST',
            stateList: []
        };
        await this.loadCityStates(defaultCountryId);
        this.showCityModal = true;
    },

    async openEditCity(item) {
        this.cityModalMode = 'edit';
        this.cityForm = {
            id: item.id,
            country_id: item.country_id,
            state_id: item.state_id,
            name: item.name || '',
            postal_code: item.postal_code || '',
            iata_code: item.iata_code || '',
            latitude: item.latitude !== null ? item.latitude : '',
            longitude: item.longitude !== null ? item.longitude : '',
            sort_order: item.sort_order || 0,
            is_active: !!item.is_active,
            actionUrl: `/admin/locations/cities/${item.id}`,
            method: 'PUT',
            stateList: []
        };
        await this.loadCityStates(item.country_id);
        this.cityForm.state_id = item.state_id;
        this.showCityModal = true;
    },

    async loadCityStates(countryId) {
        if (!countryId) {
            this.cityForm.stateList = [];
            return;
        }
        try {
            let res = await fetch(`/admin/locations/countries/${countryId}/states-json`);
            let data = await res.json();
            this.cityForm.stateList = data;
            if (data.length > 0 && !this.cityForm.state_id) {
                this.cityForm.state_id = data[0].id;
            }
        } catch (e) {
            console.error('Failed to load states', e);
        }
    }
}">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-0.5">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-navy transition-colors">Admin</a>
                <span>/</span>
                <span class="text-navy font-bold">Locations Master</span>
            </div>
            <h1 class="text-2xl font-extrabold text-navy tracking-tight">Global Locations Master</h1>
            <p class="text-xs text-ink-muted">High-performance PostgreSQL hierarchy for Countries, States/Provinces, and Cities with IATA airport hubs and cascading API endpoints.</p>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto">
            <template x-if="activeTab === 'countries'">
                <button type="button" @click="openCreateCountry()" 
                        class="px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                    <span class="text-base font-bold">+</span>
                    <span>Add New Country</span>
                </button>
            </template>
            <template x-if="activeTab === 'states'">
                <button type="button" @click="openCreateState()" 
                        class="px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                    <span class="text-base font-bold">+</span>
                    <span>Add New State / Province</span>
                </button>
            </template>
            <template x-if="activeTab === 'cities'">
                <button type="button" @click="openCreateCity()" 
                        class="px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                    <span class="text-base font-bold">+</span>
                    <span>Add New City / Hub</span>
                </button>
            </template>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <span>✅</span>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 font-black text-sm">&times;</button>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold shadow-sm">
            <p class="font-bold mb-1">Please correct the following errors:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <a href="{{ route('admin.locations.index', ['tab' => 'countries']) }}" 
           class="naoero-card p-4.5 bg-white border {{ $tab === 'countries' ? 'border-accent shadow-md shadow-accent/10 ring-2 ring-accent/20' : 'border-slate-200' }} rounded-2xl flex items-center justify-between transition-all hover:scale-[1.01]">
            <div>
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Countries Master</span>
                <div class="flex items-baseline gap-2 mt-1">
                    <span class="text-2xl font-black text-navy">{{ $stats['total_countries'] }}</span>
                    <span class="text-[11px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">{{ $stats['active_countries'] }} Active</span>
                </div>
                <span class="text-[10px] text-slate-400 block mt-1">Nauru, Pacific, Australia, Global</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-[#012169]/10 text-[#012169] flex items-center justify-center text-xl font-bold">
                🌏
            </div>
        </a>

        <a href="{{ route('admin.locations.index', ['tab' => 'states']) }}" 
           class="naoero-card p-4.5 bg-white border {{ $tab === 'states' ? 'border-accent shadow-md shadow-accent/10 ring-2 ring-accent/20' : 'border-slate-200' }} rounded-2xl flex items-center justify-between transition-all hover:scale-[1.01]">
            <div>
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">States & Provinces</span>
                <div class="flex items-baseline gap-2 mt-1">
                    <span class="text-2xl font-black text-navy">{{ $stats['total_states'] }}</span>
                    <span class="text-[11px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">{{ $stats['active_states'] }} Active</span>
                </div>
                <span class="text-[10px] text-slate-400 block mt-1">Districts, Divisions, Territories</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center text-xl font-bold">
                🗺️
            </div>
        </a>

        <a href="{{ route('admin.locations.index', ['tab' => 'cities']) }}" 
           class="naoero-card p-4.5 bg-white border {{ $tab === 'cities' ? 'border-accent shadow-md shadow-accent/10 ring-2 ring-accent/20' : 'border-slate-200' }} rounded-2xl flex items-center justify-between transition-all hover:scale-[1.01]">
            <div>
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Cities & Airport Hubs</span>
                <div class="flex items-baseline gap-2 mt-1">
                    <span class="text-2xl font-black text-navy">{{ $stats['total_cities'] }}</span>
                    <span class="text-[11px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">{{ $stats['active_cities'] }} Active</span>
                </div>
                <span class="text-[10px] text-slate-400 block mt-1">Airports (INU, BNE, NAN, TRW, MAJ)</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center text-xl font-bold">
                ✈️
            </div>
        </a>
    </div>

    <!-- Main Content Container with Tabs -->
    <div class="naoero-card bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
        
        <!-- Tab Navigation Bar -->
        <div class="flex flex-wrap items-center justify-between border-b border-slate-200 bg-slate-50/70 px-6 py-3 gap-4">
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.locations.index', ['tab' => 'countries']) }}" 
                   class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 {{ $tab === 'countries' ? 'bg-[#012169] text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-navy' }}">
                    <span>🌐</span>
                    <span>1. Countries ({{ $stats['total_countries'] }})</span>
                </a>
                <a href="{{ route('admin.locations.index', ['tab' => 'states']) }}" 
                   class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 {{ $tab === 'states' ? 'bg-[#012169] text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-navy' }}">
                    <span>🗺️</span>
                    <span>2. States & Districts ({{ $stats['total_states'] }})</span>
                </a>
                <a href="{{ route('admin.locations.index', ['tab' => 'cities']) }}" 
                   class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 {{ $tab === 'cities' ? 'bg-[#012169] text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-navy' }}">
                    <span>🏙️</span>
                    <span>3. Cities & Hubs ({{ $stats['total_cities'] }})</span>
                </a>
            </div>

            <!-- API Fast Reference Pill -->
            <div class="flex items-center gap-2 text-[11px] bg-white border border-slate-200 px-3 py-1.5 rounded-xl font-mono text-slate-600 shadow-2xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="font-bold text-navy">REST API:</span>
                <span>/api/v1/locations/countries</span>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="p-5 border-b border-slate-100 bg-white">
            <form action="{{ route('admin.locations.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                <input type="hidden" name="tab" value="{{ $tab }}">

                <!-- Search Input -->
                <div class="sm:col-span-5 relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </span>
                    <input type="text" 
                           name="search" 
                           value="{{ $search }}" 
                           placeholder="{{ $tab === 'countries' ? 'Search country by name, ISO, currency, capital...' : ($tab === 'states' ? 'Search state by name, code...' : 'Search city by name, IATA, zip...') }}" 
                           class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-navy focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#012169] transition-all">
                </div>

                <!-- Tab Dependent Filters -->
                @if($tab === 'countries')
                    <div class="sm:col-span-4">
                        <select name="region" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-navy focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#012169]">
                            <option value="">All Regions</option>
                            @foreach($regions as $reg)
                                <option value="{{ $reg }}" {{ $regionFilter === $reg ? 'selected' : '' }}>{{ $reg }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif($tab === 'states')
                    <div class="sm:col-span-4">
                        <select name="country_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-navy focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#012169]">
                            <option value="">All Countries</option>
                            @foreach($allCountries as $c)
                                <option value="{{ $c->id }}" {{ $countryFilter == $c->id ? 'selected' : '' }}>{{ $c->display_name }} ({{ $c->iso2 }})</option>
                            @endforeach
                        </select>
                    </div>
                @elseif($tab === 'cities')
                    <div class="sm:col-span-3">
                        <select name="country_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-navy focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#012169]">
                            <option value="">All Countries</option>
                            @foreach($allCountries as $c)
                                <option value="{{ $c->id }}" {{ $countryFilter == $c->id ? 'selected' : '' }}>{{ $c->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-3">
                        <select name="state_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-navy focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#012169]">
                            <option value="">All States / Districts</option>
                            @foreach($allStates as $s)
                                <option value="{{ $s->id }}" {{ $stateFilter == $s->id ? 'selected' : '' }}>{{ $s->country?->name }}: {{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <!-- Submit / Clear Buttons -->
                <div class="sm:col-span-3 flex items-center gap-2">
                    <button type="submit" class="px-4 py-2 bg-[#012169] text-white rounded-xl text-xs font-bold hover:bg-[#0A1F44] transition-colors flex-1 text-center">
                        Filter
                    </button>
                    @if($search || $countryFilter || $stateFilter || $regionFilter)
                        <a href="{{ route('admin.locations.index', ['tab' => $tab]) }}" class="px-3 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-200 transition-colors">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- TAB 1: COUNTRIES TABLE -->
        @if($tab === 'countries')
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                            <th class="py-3 px-4">#</th>
                            <th class="py-3 px-4">Country & Code</th>
                            <th class="py-3 px-4">Capital</th>
                            <th class="py-3 px-4">Phone / Currency</th>
                            <th class="py-3 px-4">Region</th>
                            <th class="py-3 px-4 text-center">States</th>
                            <th class="py-3 px-4 text-center">Cities</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        @forelse($countries as $country)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3 px-4 font-mono text-slate-400 text-[11px]">{{ $country->id }}</td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-xl">{{ $country->flag_emoji ?: '🌐' }}</span>
                                        <div>
                                            <span class="font-extrabold text-navy block text-sm">{{ $country->name }}</span>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <span class="px-1.5 py-0.2 bg-blue-50 text-blue-700 font-mono font-bold rounded text-[10px]">{{ $country->iso2 }}</span>
                                                @if($country->iso3)
                                                    <span class="px-1.5 py-0.2 bg-slate-100 text-slate-600 font-mono rounded text-[10px]">{{ $country->iso3 }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-slate-700 font-semibold">{{ $country->capital ?: '—' }}</td>
                                <td class="py-3 px-4">
                                    <span class="text-slate-600 font-mono text-[11px] block">{{ $country->phone_code ?: '—' }}</span>
                                    <span class="text-[11px] font-bold text-emerald-700 block">{{ $country->currency }} ({{ $country->currency_symbol ?: '$' }})</span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 text-slate-700">{{ $country->region ?: 'Global' }}</span>
                                    @if($country->subregion)
                                        <span class="text-[10px] text-slate-400 block mt-0.5">{{ $country->subregion }}</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="{{ route('admin.locations.index', ['tab' => 'states', 'country_id' => $country->id]) }}" 
                                       class="px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 font-extrabold hover:bg-blue-100 transition-colors">
                                        {{ $country->states_count }}
                                    </a>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="{{ route('admin.locations.index', ['tab' => 'cities', 'country_id' => $country->id]) }}" 
                                       class="px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 font-extrabold hover:bg-indigo-100 transition-colors">
                                        {{ $country->cities_count }}
                                    </a>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <form action="{{ route('admin.locations.countries.toggle', $country) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" 
                                                class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider transition-all {{ $country->is_active ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
                                            {{ $country->is_active ? 'Active' : 'Disabled' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="py-3 px-4 text-right space-x-1.5">
                                    <button type="button" 
                                            @click="openEditCountry({{ json_encode($country) }})" 
                                            class="p-1.5 rounded-lg text-slate-500 hover:text-navy hover:bg-slate-100 transition-colors" 
                                            title="Edit Country">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <form action="{{ route('admin.locations.countries.destroy', $country) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete {{ $country->name }}? All related states and cities will be removed.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition-colors" title="Delete Country">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-12 text-center text-slate-400">
                                    <p class="text-base font-semibold mb-1">No countries found.</p>
                                    <p class="text-xs">Adjust your search filter or click "+ Add New Country".</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($countries->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $countries->links() }}
                </div>
            @endif
        @endif

        <!-- TAB 2: STATES / PROVINCES TABLE -->
        @if($tab === 'states')
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                            <th class="py-3 px-4">#</th>
                            <th class="py-3 px-4">State / District / Province</th>
                            <th class="py-3 px-4">Code / Type</th>
                            <th class="py-3 px-4">Country</th>
                            <th class="py-3 px-4 text-center">Cities Count</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        @forelse($states as $state)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3 px-4 font-mono text-slate-400 text-[11px]">{{ $state->id }}</td>
                                <td class="py-3 px-4 font-extrabold text-navy text-sm">
                                    {{ $state->name }}
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 bg-slate-100 text-slate-700 font-mono font-bold rounded text-[11px]">{{ $state->state_code ?: '—' }}</span>
                                    <span class="text-[10px] text-slate-400 block mt-0.5">{{ $state->type }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-1.5 font-bold text-slate-800">
                                        <span>{{ $state->country?->flag_emoji }}</span>
                                        <span>{{ $state->country?->name }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="{{ route('admin.locations.index', ['tab' => 'cities', 'country_id' => $state->country_id, 'state_id' => $state->id]) }}" 
                                       class="px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 font-extrabold hover:bg-indigo-100 transition-colors">
                                        {{ $state->cities_count }}
                                    </a>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <form action="{{ route('admin.locations.states.toggle', $state) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" 
                                                class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider transition-all {{ $state->is_active ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
                                            {{ $state->is_active ? 'Active' : 'Disabled' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="py-3 px-4 text-right space-x-1.5">
                                    <button type="button" 
                                            @click="openEditState({{ json_encode($state) }})" 
                                            class="p-1.5 rounded-lg text-slate-500 hover:text-navy hover:bg-slate-100 transition-colors" 
                                            title="Edit State">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <form action="{{ route('admin.locations.states.destroy', $state) }}" method="POST" class="inline-block" onsubmit="return confirm('Delete state {{ $state->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition-colors" title="Delete State">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <p class="text-base font-semibold mb-1">No states or provinces found.</p>
                                    <p class="text-xs">Adjust your search or filter criteria.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($states->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $states->links() }}
                </div>
            @endif
        @endif

        <!-- TAB 3: CITIES & HUBS TABLE -->
        @if($tab === 'cities')
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                            <th class="py-3 px-4">#</th>
                            <th class="py-3 px-4">City / Airport Station</th>
                            <th class="py-3 px-4">IATA / Zip</th>
                            <th class="py-3 px-4">State & Country</th>
                            <th class="py-3 px-4">Coordinates</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        @forelse($cities as $city)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3 px-4 font-mono text-slate-400 text-[11px]">{{ $city->id }}</td>
                                <td class="py-3 px-4">
                                    <span class="font-extrabold text-navy block text-sm">{{ $city->name }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    @if($city->iata_code)
                                        <span class="px-2 py-0.5 bg-accent/20 text-[#012169] border border-accent/40 font-mono font-black rounded text-[11px]">{{ $city->iata_code }}</span>
                                    @endif
                                    @if($city->postal_code)
                                        <span class="text-[11px] text-slate-500 font-mono {{ $city->iata_code ? 'ml-1' : '' }}">{{ $city->postal_code }}</span>
                                    @endif
                                    @if(!$city->iata_code && !$city->postal_code)
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <span class="font-bold text-slate-800 block">{{ $city->state?->name ?: '—' }}</span>
                                    <span class="text-[11px] text-slate-500 flex items-center gap-1 mt-0.5">
                                        <span>{{ $city->country?->flag_emoji }}</span>
                                        <span>{{ $city->country?->name }}</span>
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-mono text-[11px] text-slate-500">
                                    @if($city->latitude && $city->longitude)
                                        <span>{{ number_format($city->latitude, 3) }}, {{ number_format($city->longitude, 3) }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <form action="{{ route('admin.locations.cities.toggle', $city) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" 
                                                class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider transition-all {{ $city->is_active ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
                                            {{ $city->is_active ? 'Active' : 'Disabled' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="py-3 px-4 text-right space-x-1.5">
                                    <button type="button" 
                                            @click="openEditCity({{ json_encode($city) }})" 
                                            class="p-1.5 rounded-lg text-slate-500 hover:text-navy hover:bg-slate-100 transition-colors" 
                                            title="Edit City">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <form action="{{ route('admin.locations.cities.destroy', $city) }}" method="POST" class="inline-block" onsubmit="return confirm('Delete city {{ $city->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition-colors" title="Delete City">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <p class="text-base font-semibold mb-1">No cities or airport stations found.</p>
                                    <p class="text-xs">Adjust your search or filter criteria.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($cities->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $cities->links() }}
                </div>
            @endif
        @endif

    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 1: CREATE / EDIT COUNTRY -->
    <!-- ========================================================================= -->
    <div x-show="showCountryModal" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-navy/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div @click.away="showCountryModal = false" class="naoero-card bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl space-y-5 border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-lg font-black text-navy" x-text="countryModalMode === 'create' ? 'Add New Country' : 'Edit Country Details'"></h3>
                <button type="button" @click="showCountryModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-xl">&times;</button>
            </div>

            <form :action="countryForm.actionUrl" method="POST" class="space-y-4">
                @csrf
                <template x-if="countryForm.method === 'PUT'">
                    @method('PUT')
                </template>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-navy mb-1">Country Name *</label>
                        <input type="text" name="name" x-model="countryForm.name" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-navy focus:outline-none" placeholder="e.g. Nauru, Australia, Fiji">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">ISO 2 Code (Alpha-2) *</label>
                        <input type="text" name="iso2" x-model="countryForm.iso2" required maxlength="2" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs uppercase font-mono font-bold focus:ring-2 focus:ring-navy focus:outline-none" placeholder="NR, AU, FJ">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">ISO 3 Code (Alpha-3)</label>
                        <input type="text" name="iso3" x-model="countryForm.iso3" maxlength="3" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs uppercase font-mono focus:ring-2 focus:ring-navy focus:outline-none" placeholder="NRU, AUS, FJI">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">Phone Calling Code</label>
                        <input type="text" name="phone_code" x-model="countryForm.phone_code" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-navy focus:outline-none" placeholder="+674, +61, +679">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">Currency Code & Symbol</label>
                        <div class="flex gap-2">
                            <input type="text" name="currency" x-model="countryForm.currency" maxlength="10" class="w-2/3 px-3.5 py-2 rounded-xl border border-slate-200 text-xs uppercase font-mono font-bold focus:ring-2 focus:ring-navy focus:outline-none" placeholder="AUD, USD, FJD">
                            <input type="text" name="currency_symbol" x-model="countryForm.currency_symbol" maxlength="10" class="w-1/3 px-3.5 py-2 rounded-xl border border-slate-200 text-xs text-center font-bold focus:ring-2 focus:ring-navy focus:outline-none" placeholder="$, ₹, ¥">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">Capital City</label>
                        <input type="text" name="capital" x-model="countryForm.capital" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-navy focus:outline-none" placeholder="Yaren, Canberra, Suva">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">Flag Emoji</label>
                        <input type="text" name="flag_emoji" x-model="countryForm.flag_emoji" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs text-xl text-center focus:ring-2 focus:ring-navy focus:outline-none" placeholder="🇳🇷">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">Region</label>
                        <select name="region" x-model="countryForm.region" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-navy focus:outline-none">
                            <option value="Oceania">Oceania</option>
                            <option value="Asia">Asia</option>
                            <option value="Americas">Americas</option>
                            <option value="Europe">Europe</option>
                            <option value="Africa">Africa</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">Subregion</label>
                        <input type="text" name="subregion" x-model="countryForm.subregion" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-navy focus:outline-none" placeholder="Micronesia, Melanesia, Polynesia">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">Sort Order</label>
                        <input type="number" name="sort_order" x-model="countryForm.sort_order" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-navy focus:outline-none">
                    </div>

                    <div class="flex items-center pt-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" x-model="countryForm.is_active" class="w-4 h-4 text-navy rounded border-slate-300 focus:ring-navy">
                            <span class="text-xs font-bold text-navy">Country is Active</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="showCountryModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#012169] text-white text-xs font-extrabold hover:bg-[#0A1F44] transition-colors">
                        <span x-text="countryModalMode === 'create' ? 'Create Country' : 'Save Changes'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: CREATE / EDIT STATE -->
    <!-- ========================================================================= -->
    <div x-show="showStateModal" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-navy/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div @click.away="showStateModal = false" class="naoero-card bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-5 border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-lg font-black text-navy" x-text="stateModalMode === 'create' ? 'Add State / District / Province' : 'Edit State Details'"></h3>
                <button type="button" @click="showStateModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-xl">&times;</button>
            </div>

            <form :action="stateForm.actionUrl" method="POST" class="space-y-4">
                @csrf
                <template x-if="stateForm.method === 'PUT'">
                    @method('PUT')
                </template>

                <div>
                    <label class="block text-xs font-bold text-navy mb-1">Country *</label>
                    <select name="country_id" x-model="stateForm.country_id" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-navy focus:outline-none">
                        @foreach($allCountries as $c)
                            <option value="{{ $c->id }}">{{ $c->display_name }} ({{ $c->iso2 }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-navy mb-1">State / District / Province Name *</label>
                        <input type="text" name="name" x-model="stateForm.name" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-navy focus:outline-none" placeholder="e.g. Queensland, Yaren District, Western Division">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">State Code / Abbreviation</label>
                        <input type="text" name="state_code" x-model="stateForm.state_code" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs uppercase font-mono focus:ring-2 focus:ring-navy focus:outline-none" placeholder="QLD, NSW, YAR, WST">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">Classification Type</label>
                        <select name="type" x-model="stateForm.type" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-navy focus:outline-none">
                            <option value="State">State</option>
                            <option value="District">District (Nauru)</option>
                            <option value="Province">Province</option>
                            <option value="Division">Division (Fiji)</option>
                            <option value="Territory">Territory</option>
                            <option value="Island Group">Island Group</option>
                            <option value="Atoll">Atoll</option>
                            <option value="Region">Region</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">Sort Order</label>
                        <input type="number" name="sort_order" x-model="stateForm.sort_order" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-navy focus:outline-none">
                    </div>

                    <div class="flex items-center pt-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" x-model="stateForm.is_active" class="w-4 h-4 text-navy rounded border-slate-300 focus:ring-navy">
                            <span class="text-xs font-bold text-navy">State is Active</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="showStateModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#012169] text-white text-xs font-extrabold hover:bg-[#0A1F44] transition-colors">
                        <span x-text="stateModalMode === 'create' ? 'Create State' : 'Save Changes'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 3: CREATE / EDIT CITY -->
    <!-- ========================================================================= -->
    <div x-show="showCityModal" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-navy/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div @click.away="showCityModal = false" class="naoero-card bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-5 border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-lg font-black text-navy" x-text="cityModalMode === 'create' ? 'Add City / Airport Hub' : 'Edit City Details'"></h3>
                <button type="button" @click="showCityModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-xl">&times;</button>
            </div>

            <form :action="cityForm.actionUrl" method="POST" class="space-y-4">
                @csrf
                <template x-if="cityForm.method === 'PUT'">
                    @method('PUT')
                </template>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">Country *</label>
                        <select name="country_id" x-model="cityForm.country_id" @change="loadCityStates(cityForm.country_id)" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-navy focus:outline-none">
                            @foreach($allCountries as $c)
                                <option value="{{ $c->id }}">{{ $c->display_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">State / District / Province *</label>
                        <select name="state_id" x-model="cityForm.state_id" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-navy focus:outline-none">
                            <template x-for="st in cityForm.stateList" :key="st.id">
                                <option :value="st.id" x-text="st.name + (st.state_code ? ' (' + st.state_code + ')' : '')"></option>
                            </template>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-navy mb-1">City / Hub Name *</label>
                        <input type="text" name="name" x-model="cityForm.name" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-navy focus:outline-none" placeholder="e.g. Brisbane, Yaren, Nadi, Tarawa">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">IATA Airport Code</label>
                        <input type="text" name="iata_code" x-model="cityForm.iata_code" maxlength="10" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs uppercase font-mono font-bold focus:ring-2 focus:ring-navy focus:outline-none" placeholder="INU, BNE, NAN, TRW, MAJ">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">Postal / Zip Code</label>
                        <input type="text" name="postal_code" x-model="cityForm.postal_code" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-navy focus:outline-none" placeholder="4000, 96960, NR-YAR">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">Latitude</label>
                        <input type="text" name="latitude" x-model="cityForm.latitude" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-navy focus:outline-none" placeholder="-27.4698">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">Longitude</label>
                        <input type="text" name="longitude" x-model="cityForm.longitude" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-navy focus:outline-none" placeholder="153.0251">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-1">Sort Order</label>
                        <input type="number" name="sort_order" x-model="cityForm.sort_order" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-navy focus:outline-none">
                    </div>

                    <div class="flex items-center pt-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" x-model="cityForm.is_active" class="w-4 h-4 text-navy rounded border-slate-300 focus:ring-navy">
                            <span class="text-xs font-bold text-navy">City is Active</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="showCityModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#012169] text-white text-xs font-extrabold hover:bg-[#0A1F44] transition-colors">
                        <span x-text="cityModalMode === 'create' ? 'Create City' : 'Save Changes'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

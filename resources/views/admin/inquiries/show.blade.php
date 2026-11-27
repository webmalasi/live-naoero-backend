@extends('layouts.admin')

@section('title', 'Inquiry Details #' . $inquiry->id)

@section('content')
@php
    $details = (array) ($inquiry->details ?? $inquiry->payload ?? []);
    
    // Extracted Aviation / Charter / Booking specifics
    $company = $details['companyName'] ?? $details['company'] ?? null;
    $position = $details['position'] ?? null;
    $country = $details['country'] ?? null;
    $aircraftType = $details['aircraftType'] ?? $details['charterType'] ?? null;
    $departureLoc = $details['departureLocation'] ?? $details['origin'] ?? null;
    $departureDate = $details['departureDate'] ?? $details['dates'] ?? null;
    $arrivalLoc = $details['arrivalLocation'] ?? $details['destination'] ?? null;
    $arrivalDate = $details['arrivalDate'] ?? $details['returnDate'] ?? null;
    $requestedTime = $details['requestedTime'] ?? null;

    // Passenger specifics
    $male = $details['male'] ?? null;
    $female = $details['female'] ?? null;
    $children = $details['children'] ?? null;
    $infants = $details['infants'] ?? null;
    $passengerCount = $details['passengerCount'] ?? $details['passengers'] ?? $details['groupSize'] ?? null;
    $passengerNotes = $details['passengerSupportingDetails'] ?? null;
    $hasPassengerData = ($male !== null || $female !== null || $children !== null || $infants !== null || $passengerCount !== null || $passengerNotes !== null);

    // Freight specifics
    $freightDesc = $details['freightDescription'] ?? $details['cargoType'] ?? null;
    $freightPkg = $details['freightPackaging'] ?? null;
    $freightPieces = $details['freightPieces'] ?? null;
    $freightWeight = $details['freightWeight'] ?? $details['weight'] ?? $details['estimatedWeight'] ?? null;
    $tempSensitive = $details['tempSensitive'] ?? null;
    $tempSensitiveDetails = $details['tempSensitiveDetails'] ?? null;
    $dangerousGoods = $details['dangerousGoods'] ?? null;
    $dangerousGoodsDetails = $details['dangerousGoodsDetails'] ?? null;
    $freightNotes = $details['freightSupportingDetails'] ?? null;
    $hasFreightData = ($freightDesc || $freightPkg || $freightPieces || $freightWeight || $tempSensitive || $dangerousGoods || $freightNotes);

    // Flight Info specifics (for feedback/other forms)
    $flightNumber = $details['flightNumber'] ?? null;
    $classOfTravel = $details['classOfTravel'] ?? null;
    $feedbackCategory = $details['feedbackCategory'] ?? null;
@endphp

<div class="max-w-6xl mx-auto space-y-6">

    <!-- Top Header & Breadcrumb -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('admin.inquiries.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-navy mb-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Inquiries Inbox
            </a>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-extrabold text-navy">Inquiry #{{ $inquiry->id }}</h1>
                <span class="px-3 py-1 rounded-xl text-xs font-extrabold uppercase tracking-wider
                    @if($inquiry->type === 'charter') bg-purple-100 text-purple-800 border border-purple-200
                    @elseif($inquiry->type === 'group_booking') bg-blue-100 text-blue-800 border border-blue-200
                    @elseif($inquiry->type === 'feedback') bg-teal-100 text-teal-800 border border-teal-200
                    @elseif($inquiry->type === 'acmi') bg-indigo-100 text-indigo-800 border border-indigo-200
                    @elseif($inquiry->type === 'freight') bg-amber-100 text-amber-800 border border-amber-200
                    @else bg-slate-100 text-slate-700 border border-slate-200 @endif">
                    {{ str_replace('_', ' ', $inquiry->type) }}
                </span>
                
                <span class="px-3 py-1 rounded-xl text-xs font-bold
                    @if($inquiry->status === 'new') bg-rose-50 text-rose-700 border border-rose-200 animate-pulse
                    @elseif($inquiry->status === 'in_progress') bg-amber-50 text-amber-700 border border-amber-200
                    @elseif($inquiry->status === 'resolved') bg-emerald-50 text-emerald-700 border border-emerald-200
                    @else bg-slate-50 text-slate-600 border border-slate-200 @endif">
                    ● {{ ucfirst(str_replace('_', ' ', $inquiry->status)) }}
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Received on <strong class="text-slate-600 font-mono">{{ $inquiry->created_at->format('d M Y \a\t H:i:s') }}</strong>
                @if($inquiry->ip_address)
                    &bull; IP: <span class="font-mono text-slate-500">{{ $inquiry->ip_address }}</span>
                @endif
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="mailto:{{ $inquiry->email }}?subject=Re: {{ rawurlencode($inquiry->subject ?: 'Naoero Airlines Inquiry') }}" 
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-xs shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span>Reply by Email</span>
            </a>

            <form method="POST" action="{{ route('admin.inquiries.destroy', $inquiry) }}" 
                  onsubmit="return confirm('Are you sure you want to delete this inquiry record?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-xl border border-rose-200 transition-colors">
                    Delete
                </button>
            </form>
        </div>
    </div>

    <!-- Client & Organization Summary Card -->
    <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-slate-100">
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 rounded-2xl bg-[#012169] text-white flex items-center justify-center font-extrabold text-xl shadow-md shadow-navy/20 shrink-0">
                    {{ strtoupper(substr($inquiry->name ?: 'NA', 0, 2)) }}
                </div>
                <div>
                    <h2 class="text-xl font-extrabold text-navy">{{ $inquiry->name }}</h2>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 mt-1">
                        <span class="flex items-center gap-1.5 font-semibold text-navy">
                            <svg class="w-3.5 h-3.5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <a href="mailto:{{ $inquiry->email }}" class="text-navy hover:text-accent-hover hover:underline">{{ $inquiry->email }}</a>
                        </span>

                        @if($inquiry->phone)
                            <span class="flex items-center gap-1.5 font-mono font-bold text-slate-700">
                                <svg class="w-3.5 h-3.5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                                {{ $inquiry->phone }}
                            </span>
                        @endif

                        @if($country)
                            <span class="flex items-center gap-1 text-slate-600 font-semibold">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                {{ $country }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Status Update Widget -->
            <form method="POST" action="{{ route('admin.inquiries.status', $inquiry) }}" class="flex items-center gap-2 bg-slate-50 p-2 rounded-2xl border border-slate-200">
                @csrf
                @method('PATCH')
                <select name="status" class="px-3 py-1.5 text-xs rounded-xl border border-slate-200 font-bold bg-white focus:outline-none focus:ring-2 focus:ring-accent/40 text-navy">
                    <option value="new" {{ $inquiry->status === 'new' ? 'selected' : '' }}>New</option>
                    <option value="in_progress" {{ $inquiry->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="resolved" {{ $inquiry->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                    <option value="archived" {{ $inquiry->status === 'archived' ? 'selected' : '' }}>Archived</option>
                </select>
                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-[#012169] text-white text-xs font-bold hover:bg-navy-700 transition-colors shrink-0">
                    Update
                </button>
            </form>
        </div>

        <!-- Meta Highlights (Company, Position, Flight/Aircraft) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-6">
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Company / Org</span>
                <span class="text-sm font-extrabold text-navy block mt-0.5">{{ $company ?: 'Individual' }}</span>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Position / Role</span>
                <span class="text-sm font-extrabold text-navy block mt-0.5">{{ $position ?: '—' }}</span>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Aircraft / Service</span>
                <span class="text-sm font-extrabold text-navy block mt-0.5">{{ $aircraftType ?: ($inquiry->type === 'charter' ? 'Private Charter' : ucfirst($inquiry->type)) }}</span>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Country</span>
                <span class="text-sm font-extrabold text-navy block mt-0.5">{{ $country ?: '—' }}</span>
            </div>
        </div>
    </div>

    <!-- Flight Itinerary & Routing Card (If Departure/Arrival details present) -->
    @if($departureLoc || $arrivalLoc || $departureDate || $arrivalDate)
        <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-navy/10 text-navy flex items-center justify-center font-bold">
                        ✈️
                    </div>
                    <h3 class="text-base font-extrabold text-navy">Flight Routing & Schedule Request</h3>
                </div>
                @if($aircraftType)
                    <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200">
                        Aircraft: {{ $aircraftType }}
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Departure -->
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Departure Origin</span>
                    <p class="text-base font-black text-navy mt-1">{{ $departureLoc ?: 'Not specified' }}</p>
                    <div class="mt-2 text-xs text-slate-500 font-semibold space-y-0.5">
                        <p>Date: <strong class="text-slate-800 font-mono">{{ $departureDate ?: '—' }}</strong></p>
                        @if($requestedTime)
                            <p>Preferred Time: <strong class="text-slate-800 font-mono">{{ $requestedTime }}</strong></p>
                        @endif
                    </div>
                </div>

                <!-- Arrival -->
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Destination Arrival</span>
                    <p class="text-base font-black text-navy mt-1">{{ $arrivalLoc ?: 'Not specified' }}</p>
                    <div class="mt-2 text-xs text-slate-500 font-semibold space-y-0.5">
                        <p>Arrival Date: <strong class="text-slate-800 font-mono">{{ $arrivalDate ?: '—' }}</strong></p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Passenger Manifest Breakdown Card (If passenger details present) -->
    @if($hasPassengerData)
        <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center font-bold">
                        👥
                    </div>
                    <h3 class="text-base font-extrabold text-navy">Passenger Manifest Breakdown</h3>
                </div>
                @php
                    $totalCalc = (int)($male ?? 0) + (int)($female ?? 0) + (int)($children ?? 0) + (int)($infants ?? 0);
                @endphp
                @if($totalCalc > 0)
                    <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">
                        Total: {{ $totalCalc }} Passengers
                    </span>
                @elseif($passengerCount)
                    <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">
                        Group Size: {{ $passengerCount }}
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100 text-center">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Adult Males</span>
                    <span class="text-xl font-black text-navy mt-1 block">{{ $male ?? '—' }}</span>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100 text-center">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Adult Females</span>
                    <span class="text-xl font-black text-navy mt-1 block">{{ $female ?? '—' }}</span>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100 text-center">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Children (2-11)</span>
                    <span class="text-xl font-black text-navy mt-1 block">{{ $children ?? '—' }}</span>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100 text-center">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Infants (<2)</span>
                    <span class="text-xl font-black text-navy mt-1 block">{{ $infants ?? '—' }}</span>
                </div>
            </div>

            @if($passengerNotes)
                <div class="p-3.5 rounded-xl bg-blue-50/50 border border-blue-100 text-xs text-slate-700">
                    <strong class="font-bold text-navy block mb-1">Passenger Supporting Details:</strong>
                    {{ $passengerNotes }}
                </div>
            @endif
        </div>
    @endif

    <!-- Freight / Cargo Specifications Card (If freight details present) -->
    @if($hasFreightData)
        <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center font-bold">
                        📦
                    </div>
                    <h3 class="text-base font-extrabold text-navy">Freight & Cargo Specifications</h3>
                </div>
                @if($freightWeight)
                    <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                        Weight: {{ $freightWeight }}
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Cargo Description</span>
                    <span class="text-sm font-bold text-navy mt-1 block">{{ $freightDesc ?: '—' }}</span>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Packaging Type</span>
                    <span class="text-sm font-bold text-navy mt-1 block">{{ $freightPkg ?: '—' }}</span>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Number of Pieces / Weight</span>
                    <span class="text-sm font-bold text-navy mt-1 block">{{ $freightPieces ?: '—' }} / {{ $freightWeight ?: '—' }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Temperature Sensitive -->
                <div class="p-3.5 rounded-xl border {{ strtolower($tempSensitive) === 'yes' ? 'bg-amber-50 border-amber-200' : 'bg-slate-50 border-slate-100' }}">
                    <span class="text-[11px] font-bold uppercase tracking-wider block {{ strtolower($tempSensitive) === 'yes' ? 'text-amber-800' : 'text-slate-400' }}">
                        Temperature Sensitive: <strong>{{ ucfirst($tempSensitive ?: 'No') }}</strong>
                    </span>
                    @if($tempSensitiveDetails)
                        <p class="text-xs text-slate-700 mt-1 font-medium">{{ $tempSensitiveDetails }}</p>
                    @endif
                </div>

                <!-- Dangerous Goods -->
                <div class="p-3.5 rounded-xl border {{ strtolower($dangerousGoods) === 'yes' ? 'bg-rose-50 border-rose-200' : 'bg-slate-50 border-slate-100' }}">
                    <span class="text-[11px] font-bold uppercase tracking-wider block {{ strtolower($dangerousGoods) === 'yes' ? 'text-rose-800' : 'text-slate-400' }}">
                        Dangerous Goods: <strong>{{ ucfirst($dangerousGoods ?: 'No') }}</strong>
                    </span>
                    @if($dangerousGoodsDetails)
                        <p class="text-xs text-slate-700 mt-1 font-medium">{{ $dangerousGoodsDetails }}</p>
                    @endif
                </div>
            </div>

            @if($freightNotes)
                <div class="p-3.5 rounded-xl bg-amber-50/50 border border-amber-100 text-xs text-slate-700">
                    <strong class="font-bold text-navy block mb-1">Cargo Handling / Supporting Details:</strong>
                    {{ $freightNotes }}
                </div>
            @endif
        </div>
    @endif

    <!-- Message & Notes Card -->
    <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm space-y-4">
        <div class="pb-3 border-b border-slate-100">
            <h3 class="text-base font-extrabold text-navy">Client Request & Notes</h3>
            @if($inquiry->subject)
                <p class="text-xs text-slate-500 mt-0.5">Subject: <strong class="text-navy">{{ $inquiry->subject }}</strong></p>
            @endif
        </div>

        <div class="p-5 rounded-xl bg-slate-50 border border-slate-100 text-sm text-slate-800 whitespace-pre-line leading-relaxed font-normal">
            {{ $inquiry->message ?: 'No additional message text provided.' }}
        </div>
    </div>

    <!-- Complete Structured Data Grid (All Submission Fields) -->
    @if(count($details) > 0)
        <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-extrabold text-navy">Complete Form Submission Data</h3>
                    <p class="text-xs text-slate-400">All submitted fields and parameters</p>
                </div>
                <span class="text-xs font-bold text-slate-400">{{ count($details) }} Fields</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($details as $key => $val)
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                        <span class="text-slate-400 font-semibold block capitalize">{{ preg_replace('/(?<!\ )[A-Z]/', ' $0', str_replace('_', ' ', $key)) }}</span>
                        <span class="font-bold text-navy break-all mt-0.5 block">
                            @if(is_array($val))
                                <pre class="text-[11px] font-mono bg-white p-1 rounded border border-slate-200">{{ json_encode($val, JSON_PRETTY_PRINT) }}</pre>
                            @elseif(is_bool($val))
                                {{ $val ? 'Yes' : 'No' }}
                            @elseif($val === '' || $val === null)
                                <span class="text-slate-300 font-normal italic">Empty</span>
                            @else
                                {{ $val }}
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Internal Admin Notes Form -->
    <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm space-y-4">
        <h3 class="text-base font-extrabold text-navy">Internal Admin Notes</h3>
        
        <form method="POST" action="{{ route('admin.inquiries.status', $inquiry) }}" class="space-y-3">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="{{ $inquiry->status }}">
            
            <textarea name="admin_notes" 
                      rows="3" 
                      data-no-editor="true"
                      placeholder="Add private operational notes regarding this charter / passenger inquiry (visible only to admin team)..." 
                      class="w-full p-3.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 bg-slate-50 focus:bg-white text-navy transition-all">{{ old('admin_notes', $inquiry->admin_notes) }}</textarea>

            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#012169] text-white text-xs font-bold hover:bg-navy-700 transition-colors shadow-sm">
                    Save Internal Notes
                </button>
            </div>
        </form>
    </div>

</div>
@endsection

@extends('layouts.admin')

@section('title', 'Inquiries & Feedback Inbox')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Customer Inquiries Inbox</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $inquiries->total() }} Messages
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage passenger contact queries, customer feedback, group flight bookings, and charter flights.</p>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="naoero-card p-4 bg-white border border-slate-200">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">New / Unread</span>
            <p class="text-2xl font-extrabold text-rose-600 mt-1">{{ $stats['new'] }}</p>
        </div>
        <div class="naoero-card p-4 bg-white border border-slate-200">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">General Inquiries</span>
            <p class="text-2xl font-extrabold text-navy mt-1">{{ $stats['contacts'] }}</p>
        </div>
        <div class="naoero-card p-4 bg-white border border-slate-200">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Group Bookings</span>
            <p class="text-2xl font-extrabold text-navy mt-1">{{ $stats['group_bookings'] }}</p>
        </div>
        <div class="naoero-card p-4 bg-white border border-slate-200">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Aircraft Charters</span>
            <p class="text-2xl font-extrabold text-navy mt-1">{{ $stats['charters'] }}</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="naoero-card p-4 bg-white border border-slate-200">
        <form method="GET" action="{{ route('admin.inquiries.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="relative sm:col-span-2">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search sender name, email, subject, message..." 
                       class="w-full pl-10 pr-4 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <div>
                <select name="type" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                    <option value="">All Inquiry Types</option>
                    <option value="contact" {{ request('type') === 'contact' ? 'selected' : '' }}>General Contact</option>
                    <option value="feedback" {{ request('type') === 'feedback' ? 'selected' : '' }}>Customer Feedback</option>
                    <option value="group_booking" {{ request('type') === 'group_booking' ? 'selected' : '' }}>Group Booking</option>
                    <option value="charter" {{ request('type') === 'charter' ? 'selected' : '' }}>Aircraft Charter</option>
                    <option value="acmi" {{ request('type') === 'acmi' ? 'selected' : '' }}>ACMI Lease</option>
                    <option value="freight" {{ request('type') === 'freight' ? 'selected' : '' }}>Cargo / Freight</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 px-4 py-2 rounded-xl bg-navy text-white text-sm font-bold hover:bg-navy-700 transition-colors">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'type', 'status']))
                    <a href="{{ route('admin.inquiries.index') }}" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-sm font-semibold hover:bg-slate-200 transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Inquiries List -->
    <div class="naoero-card bg-white border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase font-extrabold text-slate-500 border-b border-slate-200 tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">Type</th>
                        <th class="px-5 py-3.5">Sender</th>
                        <th class="px-5 py-3.5">Subject / Message</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-center">Received Date</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($inquiries as $msg)
                        <tr class="hover:bg-slate-50/70 transition-colors {{ $msg->status === 'new' ? 'bg-amber-50/40 font-semibold' : '' }}">
                            <!-- Type Badge -->
                            <td class="px-5 py-3.5">
                                <span class="px-2.5 py-1 rounded-lg text-[11px] font-extrabold uppercase tracking-wider
                                    @if($msg->type === 'charter') bg-purple-100 text-purple-800
                                    @elseif($msg->type === 'group_booking') bg-blue-100 text-blue-800
                                    @elseif($msg->type === 'feedback') bg-teal-100 text-teal-800
                                    @elseif($msg->type === 'acmi') bg-indigo-100 text-indigo-800
                                    @elseif($msg->type === 'freight') bg-amber-100 text-amber-800
                                    @else bg-slate-100 text-slate-700 @endif">
                                    {{ str_replace('_', ' ', $msg->type) }}
                                </span>
                            </td>

                            <!-- Sender -->
                            <td class="px-5 py-3.5">
                                <div>
                                    <span class="text-navy font-bold block">{{ $msg->name }}</span>
                                    <span class="text-xs text-slate-500 font-mono">{{ $msg->email }}</span>
                                    @if($msg->phone)
                                        <span class="text-[11px] text-slate-400 block">{{ $msg->phone }}</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Subject & Snippet -->
                            <td class="px-5 py-3.5 max-w-md">
                                <div>
                                    <a href="{{ route('admin.inquiries.show', $msg) }}" class="text-navy font-bold hover:text-accent-hover transition-colors block truncate">
                                        {{ $msg->subject ?: 'Inquiry from ' . $msg->name }}
                                    </a>
                                    <p class="text-xs text-slate-500 truncate mt-0.5">{{ $msg->message }}</p>
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="px-5 py-3.5 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold
                                    @if($msg->status === 'new') bg-rose-100 text-rose-800 animate-pulse
                                    @elseif($msg->status === 'in_progress') bg-amber-100 text-amber-800
                                    @elseif($msg->status === 'resolved') bg-emerald-100 text-emerald-800
                                    @else bg-slate-100 text-slate-600 @endif">
                                    {{ ucfirst(str_replace('_', ' ', $msg->status)) }}
                                </span>
                            </td>

                            <!-- Date -->
                            <td class="px-5 py-3.5 text-center text-xs text-slate-500 font-mono">
                                {{ $msg->created_at->format('d M Y, H:i') }}
                            </td>

                            <!-- Actions -->
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.inquiries.show', $msg) }}" 
                                       class="p-1.5 text-slate-400 hover:text-navy hover:bg-slate-100 rounded-lg transition-colors"
                                       title="View Full Message">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>

                                    <form method="POST" action="{{ route('admin.inquiries.destroy', $msg) }}" 
                                          onsubmit="return confirm('Are you sure you want to delete this inquiry?');"
                                          class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                                title="Delete Inquiry">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <p class="text-base font-bold text-slate-600">No customer messages</p>
                                <p class="text-xs text-slate-400 mt-1">Inquiries submitted on the website contact or booking forms will arrive here.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($inquiries->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">
                {{ $inquiries->links() }}
            </div>
        @endif
    </div>

</div>
@endsection

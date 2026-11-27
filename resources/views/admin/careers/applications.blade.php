@extends('layouts.admin')

@section('title', 'Job Applications Inbox')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Job Applications</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $applications->total() }} Applications
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Review candidate submissions, resume attachments, and application status.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.careers.index') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-navy font-bold text-sm shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                <span>Manage Job Postings</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="naoero-card p-4 bg-white border border-slate-200">
        <form method="GET" action="{{ route('admin.job-applications.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <select name="status" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                    <option value="">All Review Statuses</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending Review</option>
                    <option value="reviewed" {{ request('status') === 'reviewed' ? 'selected' : '' }}>Reviewed</option>
                    <option value="shortlisted" {{ request('status') === 'shortlisted' ? 'selected' : '' }}>Shortlisted</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 px-4 py-2 rounded-xl bg-navy text-white text-sm font-bold hover:bg-navy-700 transition-colors">
                    Filter
                </button>
                @if(request()->hasAny(['status', 'career_id']))
                    <a href="{{ route('admin.job-applications.index') }}" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-sm font-semibold hover:bg-slate-200 transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Applications List -->
    <div class="space-y-4">
        @forelse($applications as $app)
            <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-extrabold text-navy">{{ $app->full_name }}</h3>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold 
                                @if($app->status === 'shortlisted') bg-emerald-100 text-emerald-800
                                @elseif($app->status === 'reviewed') bg-blue-100 text-blue-800
                                @elseif($app->status === 'rejected') bg-rose-100 text-rose-800
                                @else bg-amber-100 text-amber-800 @endif">
                                {{ ucfirst($app->status) }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Applied for <strong class="text-navy">{{ $app->career ? $app->career->title : 'General Application' }}</strong> 
                            &bull; {{ $app->created_at->diffForHumans() }} ({{ $app->created_at->format('d M Y, H:i') }})
                        </p>
                    </div>

                    <!-- Status Update Form -->
                    <form method="POST" action="{{ route('admin.job-applications.status', $app) }}" class="flex items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="px-3 py-1.5 text-xs rounded-xl border border-slate-200 font-bold bg-white focus:outline-none focus:ring-2 focus:ring-accent/40">
                            <option value="pending" {{ $app->status === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="reviewed" {{ $app->status === 'reviewed' ? 'selected' : '' }}>Reviewed</option>
                            <option value="shortlisted" {{ $app->status === 'shortlisted' ? 'selected' : '' }}>Shortlisted</option>
                            <option value="rejected" {{ $app->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-navy text-white text-xs font-bold hover:bg-navy-700 transition-colors">
                            Update
                        </button>
                    </form>
                </div>

                <!-- Contact & Resume -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-slate-400 block font-semibold">Email</span>
                        <a href="mailto:{{ $app->email }}" class="font-bold text-navy hover:underline">{{ $app->email }}</a>
                    </div>

                    <div>
                        <span class="text-slate-400 block font-semibold">Phone</span>
                        <span class="font-bold text-navy">{{ $app->phone ?: 'Not provided' }}</span>
                    </div>

                    <div>
                        <span class="text-slate-400 block font-semibold">Resume / CV</span>
                        @if($app->resume_url_display)
                            <a href="{{ $app->resume_url_display }}" target="_blank" class="inline-flex items-center gap-1 font-bold text-accent-hover hover:underline">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                View Resume File
                            </a>
                        @else
                            <span class="text-slate-400">No file uploaded</span>
                        @endif
                    </div>
                </div>

                @if($app->cover_letter)
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100 text-xs text-slate-700 space-y-1">
                        <span class="font-bold text-navy block">Cover Letter / Message:</span>
                        <p class="whitespace-pre-line">{{ $app->cover_letter }}</p>
                    </div>
                @endif

                <div class="flex items-center justify-between pt-2">
                    <form method="POST" action="{{ route('admin.job-applications.destroy', $app) }}" 
                          onsubmit="return confirm('Are you sure you want to delete this application?');" 
                          class="ml-auto">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-semibold text-rose-500 hover:text-rose-700 hover:underline">
                            Delete Application
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="naoero-card p-12 text-center text-slate-400 bg-white border border-slate-200">
                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-base font-bold text-slate-600">No job applications submitted</p>
                <p class="text-xs text-slate-400 mt-1">Applications received from the website career form will appear here.</p>
            </div>
        @endforelse
    </div>

    @if($applications->hasPages())
        <div class="naoero-card p-4 bg-white border border-slate-200">
            {{ $applications->links() }}
        </div>
    @endif

</div>
@endsection

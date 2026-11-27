@extends('layouts.admin')

@section('title', 'FAQs Knowledgebase')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Frequently Asked Questions</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $faqs->total() }} Questions
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage baggage policies, check-in rules, flight schedule questions, and help topics.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.faqs.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-sm shadow-md shadow-accent/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add FAQ</span>
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="naoero-card p-4 bg-white border border-slate-200">
        <form method="GET" action="{{ route('admin.faqs.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="relative">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search question, answer..." 
                       class="w-full pl-10 pr-4 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <div>
                <select name="category" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 px-4 py-2 rounded-xl bg-navy text-white text-sm font-bold hover:bg-navy-700 transition-colors">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'category']))
                    <a href="{{ route('admin.faqs.index') }}" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-sm font-semibold hover:bg-slate-200 transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- FAQs Accordion / List -->
    <div class="space-y-3">
        @forelse($faqs as $faq)
            <div class="naoero-card p-5 bg-white border border-slate-200 space-y-3" x-data="{ expanded: false }">
                <div class="flex items-start justify-between gap-4 cursor-pointer" @click="expanded = !expanded">
                    <div class="space-y-1.5 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-lg text-xs font-extrabold uppercase tracking-wider bg-navy/10 text-navy">
                                {{ $faq->category }}
                            </span>
                            <span class="text-xs font-mono text-slate-400">Order #{{ $faq->sort_order }}</span>
                            <span class="px-2 py-0.5 text-xs rounded-full font-bold {{ $faq->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                {{ $faq->is_active ? 'Active' : 'Draft' }}
                            </span>
                        </div>
                        <h3 class="text-base font-bold text-navy hover:text-accent-hover transition-colors flex items-center gap-2">
                            <span>{{ $faq->question }}</span>
                            <svg :class="expanded ? 'rotate-180' : ''" class="w-4 h-4 text-slate-400 transition-transform shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </h3>
                    </div>

                    <div class="flex items-center gap-1 shrink-0" @click.stop>
                        <a href="{{ route('admin.faqs.edit', $faq) }}" 
                           class="p-1.5 text-slate-400 hover:text-navy hover:bg-slate-100 rounded-lg transition-colors"
                           title="Edit FAQ">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </a>
                        <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" 
                              onsubmit="return confirm('Are you sure you want to delete this FAQ?');"
                              class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                    title="Delete FAQ">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>

                <div x-show="expanded" x-collapse class="pt-3 border-t border-slate-100 text-sm text-slate-700 whitespace-pre-line leading-relaxed">
                    {{ $faq->answer }}
                </div>
            </div>
        @empty
            <div class="naoero-card p-12 text-center text-slate-400 bg-white border border-slate-200">
                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-base font-bold text-slate-600">No questions found</p>
                <p class="text-xs text-slate-400 mt-1">Add answers to frequently asked passenger questions.</p>
            </div>
        @endforelse
    </div>

    @if($faqs->hasPages())
        <div class="naoero-card p-4 bg-white border border-slate-200">
            {{ $faqs->links() }}
        </div>
    @endif

</div>
@endsection

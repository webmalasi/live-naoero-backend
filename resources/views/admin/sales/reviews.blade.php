@extends('layouts.admin')

@section('title', 'Product Reviews')

@section('content')
@php
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y') : '—';
    $canManage = auth()->user()->can('shop.sales.manage');
@endphp
<div class="max-w-6xl mx-auto space-y-6">
    @include('admin.sales._nav', [
        'crumb' => 'Reviews',
        'title' => 'Product Reviews',
        'subtitle' => 'From customers who bought the product. '.$total.' review(s)'.($average ? ', '.number_format((float) $average, 1).' ★ on average' : '').($hidden ? ", {$hidden} hidden" : '').'. Ratings in the shop count published reviews only.',
    ])

    <form method="GET" class="naoero-card p-4 flex flex-wrap gap-2">
        <input name="search" value="{{ request('search') }}" placeholder="Product, review text or customer email" class="flex-1 min-w-[14rem] rounded-xl border border-slate-200 px-3 py-2 text-sm">
        <select name="status" class="rounded-xl border border-slate-200 px-3 py-2 text-sm"><option value="">Published and hidden</option><option value="published" @selected(request('status') === 'published')>Published</option><option value="hidden" @selected(request('status') === 'hidden')>Hidden</option></select>
        <select name="rating" class="rounded-xl border border-slate-200 px-3 py-2 text-sm"><option value="">Any rating</option>@foreach ([5, 4, 3, 2, 1] as $n)<option value="{{ $n }}" @selected((string) request('rating') === (string) $n)>{{ $n }} ★</option>@endforeach</select>
        <label class="flex items-center gap-2 px-2 text-xs font-semibold text-slate-600"><input type="checkbox" name="unanswered" value="1" @checked(request('unanswered'))> Not answered</label>
        <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Filter</button>
    </form>

    <div class="space-y-3">
        @forelse ($reviews as $rv)
            <div class="naoero-card p-5 {{ $rv->status === 'hidden' ? 'opacity-70' : '' }}" x-data="{ reply: false }">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-amber-500 font-bold">{{ str_repeat('★', $rv->rating) }}<span class="text-slate-300">{{ str_repeat('★', 5 - $rv->rating) }}</span></span>
                    <span class="font-bold text-navy">{{ $rv->product_title }}</span>@if ($rv->variant_title)<span class="text-xs text-slate-500">· {{ $rv->variant_title }}</span>@endif
                    @if ($rv->status === 'hidden')<span class="px-2 py-0.5 rounded-full bg-slate-200 text-[10px] font-extrabold uppercase text-slate-600">Hidden</span>@endif
                    <span class="ml-auto text-xs text-slate-500">{{ $rv->reviewer_name }} (@include('admin.customers._identity', ['email' => $rv->customer_main_email ?? null, 'login' => $rv->zenith_login])) · {{ $when($rv->created_at) }}@if ($rv->order_number) · <a href="{{ route('admin.sales.index', ['search' => $rv->order_number]) }}" class="text-navy hover:underline">{{ $rv->order_number }}</a>@endif</span>
                </div>
                @if ($rv->title)<div class="font-bold text-slate-800 mt-2">{{ $rv->title }}</div>@endif
                @if ($rv->body)<div class="text-sm text-slate-700 whitespace-pre-line mt-1">{{ $rv->body }}</div>@endif
                @if ($rv->hidden_reason)<div class="text-xs text-rose-600 mt-1">Hidden: {{ $rv->hidden_reason }}</div>@endif
                @if ($rv->staff_reply)<div class="mt-3 rounded-xl bg-slate-50 px-4 py-2 text-sm"><span class="text-[11px] font-bold text-navy">Our reply:</span> {{ $rv->staff_reply }}</div>@endif
                @if ($canManage)
                    <div class="flex flex-wrap gap-2 mt-3">
                        <button type="button" @click="reply = !reply" class="px-3 py-1.5 rounded-lg border border-slate-200 text-[11px] font-bold text-navy">{{ $rv->staff_reply ? 'Edit reply' : 'Reply' }}</button>
                        <form method="POST" action="{{ route('admin.sales.reviews.moderate', $rv->id) }}" class="flex gap-2">@csrf
                            @if ($rv->status === 'published')
                                <input type="hidden" name="status" value="hidden">
                                <input name="reason" placeholder="Why hide it (internal)" class="rounded-lg border border-slate-200 px-2 py-1 text-[11px]">
                                <button class="px-3 py-1.5 rounded-lg border border-rose-200 text-[11px] font-bold text-rose-700">Hide</button>
                            @else
                                <input type="hidden" name="status" value="published">
                                <button class="px-3 py-1.5 rounded-lg border border-emerald-200 text-[11px] font-bold text-emerald-700">Publish</button>
                            @endif
                        </form>
                    </div>
                    <form x-show="reply" x-cloak method="POST" action="{{ route('admin.sales.reviews.reply', $rv->id) }}" class="mt-3 flex gap-2">@csrf
                        <textarea name="reply" rows="2" maxlength="2000" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Public reply (empty to remove it)">{{ $rv->staff_reply }}</textarea>
                        <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold self-start">Save reply</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="naoero-card p-14 text-center text-sm text-slate-400">No reviews yet.</div>
        @endforelse
    </div>
    <div>{{ $reviews->links() }}</div>
</div>
@endsection

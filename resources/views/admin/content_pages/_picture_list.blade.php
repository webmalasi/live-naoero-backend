{{-- Pictures of a gallery section, or of a tip that has its own pictures. $list is the Alpine expression of the list. --}}
<div class="space-y-2">
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
        <template x-for="(pic, pi) in {{ $list }}" :key="pic._id">
            <div class="bg-white border border-slate-200 rounded-xl p-2 space-y-1.5">
                <div class="relative h-24 rounded-lg bg-slate-100 overflow-hidden flex items-center justify-center">
                    <template x-if="pic.image"><img :src="preview(pic.image)" alt="" class="w-full h-full object-cover"></template>
                    <template x-if="!pic.image"><span class="text-[11px] text-slate-400 px-2 text-center">No picture yet</span></template>
                </div>
                <input type="text" x-model="pic.title" placeholder="Caption" class="w-full px-2 py-1 text-xs rounded-lg border border-slate-200">
                <div class="flex items-center gap-1">
                    <label class="flex-1 text-center px-2 py-1 text-[11px] font-bold text-navy bg-navy/10 rounded-lg cursor-pointer">
                        <span x-text="uploading === 'p' + pic._id ? 'Uploading…' : (pic.image ? 'Replace' : 'Upload')"></span>
                        <input type="file" accept="image/*" class="hidden" @change="upload($event, url => pic.image = url, 'p' + pic._id)">
                    </label>
                    <button type="button" title="Move earlier" @click="move({{ $list }}, pi, -1)" :disabled="pi === 0" class="w-6 h-6 rounded-md bg-slate-100 text-slate-600 disabled:opacity-30">&larr;</button>
                    <button type="button" title="Move later" @click="move({{ $list }}, pi, 1)" :disabled="pi === {{ $list }}.length - 1" class="w-6 h-6 rounded-md bg-slate-100 text-slate-600 disabled:opacity-30">&rarr;</button>
                    <button type="button" title="Remove" @click="{{ $list }}.splice(pi, 1)" class="w-6 h-6 rounded-md bg-rose-50 text-rose-600">&times;</button>
                </div>
            </div>
        </template>
    </div>
    <button type="button" @click="addPicture({{ $list }})" class="px-3 py-1 text-xs font-bold text-navy bg-accent rounded-lg">+ Add picture</button>
</div>

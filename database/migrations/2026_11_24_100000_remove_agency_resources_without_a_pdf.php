<?php

use App\Models\AgencyResource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Agency Resources: the cards that hold no PDF are taken off the website and
 * the app. Twelve came from a seeder and point at files that never existed.
 * Each removed row is kept in storage/app/backups first, so it can be put back.
 */
return new class extends Migration
{
    public function up(): void
    {
        $without = AgencyResource::query()->orderBy('id')->get()->reject(fn (AgencyResource $a) => $a->has_pdf);
        if ($without->isEmpty()) {
            return;
        }

        $file = 'backups/agency_resources_without_pdf_'.now()->format('YmdHis').'.json';
        Storage::disk('local')->put($file, $without->map(fn (AgencyResource $a) => $a->getAttributes())->values()->toJson(JSON_PRETTY_PRINT));

        AgencyResource::query()->whereKey($without->modelKeys())->delete();

        Log::info('Agency Resources without a PDF removed', ['ids' => $without->modelKeys(), 'backup' => $file]);
    }

    public function down(): void
    {
        // The removed rows are in storage/app/backups; nothing to undo here.
    }
};

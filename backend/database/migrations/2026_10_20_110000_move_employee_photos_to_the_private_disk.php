<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Profile pictures stop being public files (CORE-ACC-008). Anything already stored under the public disk
     * is moved to the private one, keeping its path, so the records stay valid.
     */
    public function up(): void
    {
        foreach (Storage::disk('public')->allFiles('employee-photos') as $path) {
            Storage::disk('local')->put($path, Storage::disk('public')->get($path));
            Storage::disk('public')->delete($path);
        }
    }

    public function down(): void
    {
        foreach (Storage::disk('local')->allFiles('employee-photos') as $path) {
            Storage::disk('public')->put($path, Storage::disk('local')->get($path));
            Storage::disk('local')->delete($path);
        }
    }
};

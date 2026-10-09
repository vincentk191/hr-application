<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The kinds of request (jenis pengajuan), kept as data so an administrator can add and remove
     * them instead of a developer editing code.
     *
     * A request stores its type's `code`, which never changes once made; the label is free to be
     * renamed. The six types the app started with are created here, with any maximum days that were
     * set under the old "Batas cuti" screen carried over.
     */
    public function up(): void
    {
        Schema::create('request_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->unsignedSmallInteger('max_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $defaults = [
            'sick' => 'Cuti sakit',
            'annual' => 'Cuti tahunan',
            'maternity' => 'Cuti melahirkan',
            'marriage' => 'Pernikahan / lamaran',
            'bereavement' => 'Cuti duka',
            'unpaid' => 'Cuti tanpa gaji',
        ];

        $order = 0;

        foreach ($defaults as $code => $label) {
            $limit = DB::table('app_settings')->where('key', "leave.max_days.{$code}")->value('value');
            $limit = $limit === null ? null : json_decode((string) $limit, true);

            DB::table('request_types')->insert([
                'code' => $code,
                'label' => $label,
                'max_days' => is_int($limit) && $limit > 0 ? $limit : null,
                'is_active' => true,
                'sort_order' => ++$order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('app_settings')->where('key', 'like', 'leave.max_days.%')->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('request_types');
    }
};

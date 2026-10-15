<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What each person has already been told once ("this document is about to lapse", "attendance
     * issues of that day"), kept apart from the notifications themselves.
     *
     * Read notifications are deleted after a day, and the once-only check used to look for the
     * notification to know it had been sent: with the notification gone, the next morning's job
     * would send it again. The keys already in the notifications table are copied across.
     */
    public function up(): void
    {
        Schema::create('notification_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 191);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'key']);
        });

        DB::table('notifications')
            ->where('notifiable_type', (new App\Models\User)->getMorphClass())
            ->where('data', 'like', '%"key":%')
            ->orderBy('created_at')
            ->each(function (object $row): void {
                $key = json_decode((string) $row->data, true)['key'] ?? null;

                if (is_string($key) && $key !== '') {
                    DB::table('notification_keys')->insertOrIgnore([
                        'user_id' => $row->notifiable_id,
                        'key' => $key,
                        'created_at' => $row->created_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_keys');
    }
};

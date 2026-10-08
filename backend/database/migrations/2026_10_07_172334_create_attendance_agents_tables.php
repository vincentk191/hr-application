<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The on-site sync agent's identity and its request log (ATT-CAL-003, ATT-DEV-001/003/004).
     *
     * An agent is not a user: it has no login, no roles and no place in the people lists. It is a
     * named holder of API tokens, so a token can be revoked or rotated without touching an account.
     * Every request it makes is logged, errors included, which is what the admin screen shows and
     * what the staleness alarm reads.
     */
    public function up(): void
    {
        Schema::create('attendance_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('sync_requested_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_ingest_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20);
            $table->unsignedInteger('rows')->default(0);
            $table->unsignedInteger('accepted')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('unmapped')->default(0);
            $table->string('message')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_ingest_requests');
        Schema::dropIfExists('attendance_agents');
    }
};

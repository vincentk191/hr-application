<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every email the app sends on a company's behalf (email automation): the log and the outbox in one. A row is made when
     * the email is queued, with the text as it will be sent; `send_at` lets one wait (a rejection goes out the next
     * morning) and be cancelled; the status and the error say what came of it.
     */
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->string('subject', 300);
            $table->text('body');
            // Files to attach: [{name, mime, path (private disk) or data}].
            $table->json('attachments')->nullable();
            $table->string('status', 12)->default('pending');
            $table->timestamp('send_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            // What the email is about (an offer, a candidate), so a screen can show its emails.
            $table->nullableMorphs('related');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'send_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};

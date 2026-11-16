<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The mailbox a company's emails are sent from (email automation): its SMTP login and the From and Reply-To addresses.
     * One per company, set by an admin. The password is encrypted by the model; nothing is sent until it is enabled.
     */
    public function up(): void
    {
        Schema::create('company_mail_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(465);
            // 'ssl' (implicit TLS, usually port 465) or 'tls' (STARTTLS, usually port 587).
            $table->string('encryption', 3)->default('ssl');
            $table->string('username');
            $table->text('password');
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->string('reply_to')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_mail_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone_number')->nullable();
            $table->string('avatar')->nullable();
            $table->enum('role', ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CUSTOMER'])->default('CUSTOMER');
            $table->json('custom_permissions')->nullable();
            $table->boolean('is_email_verified')->default(false);
            $table->string('email_verification_token')->nullable();
            $table->timestamp('email_verification_expiry')->nullable();
            $table->boolean('is_phone_verified')->default(false);
            $table->string('phone_verification_token')->nullable();
            $table->timestamp('phone_verification_expiry')->nullable();
            $table->string('password_reset_token')->nullable();
            $table->timestamp('password_reset_expiry')->nullable();
            $table->string('otp_code')->nullable();
            $table->timestamp('otp_expiry')->nullable();
            $table->integer('otp_attempts')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->uuid('vip_tier_id')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('email');
            $table->index('role');
            $table->index('is_active');
            $table->index(['role', 'is_active']);
            $table->index('last_login_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};

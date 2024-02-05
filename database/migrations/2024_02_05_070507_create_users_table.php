<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firstName', 25);
            $table->string('lastName', 25);
            $table->string('email', 50)->unique();
            $table->string('mobile', 20);
            $table->string('password', 1000);
            $table->string('otp', 8);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }
    public function down(): void {
        Schema::dropIfExists('users');
    }
};

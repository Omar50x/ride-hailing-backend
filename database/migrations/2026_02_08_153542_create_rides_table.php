<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->id();

            // Connect user
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // bind driver (nullable because initially it may not be assigned)
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('pickup_location');
            $table->string('dropoff_location');

            // status (enum)
            $table->enum('status', [
                'matching',
                'driver_assigned',
                'arrived',
                'ongoing',
                'completed',
                'cancelled'
            ])->default('matching');

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};

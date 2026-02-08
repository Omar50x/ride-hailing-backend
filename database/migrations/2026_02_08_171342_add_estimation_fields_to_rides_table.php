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
        Schema::table('rides', function (Blueprint $table) {
            $table->decimal('distance_km', 8, 2)->nullable()->after('dropoff_location');
            $table->integer('eta_minutes')->nullable()->after('distance_km');
            $table->decimal('price', 10, 2)->nullable()->after('eta_minutes');
            $table->decimal('pickup_latitude', 10, 8)->nullable()->after('pickup_location');
            $table->decimal('pickup_longitude', 11, 8)->nullable()->after('pickup_latitude');
            $table->decimal('dropoff_latitude', 10, 8)->nullable()->after('dropoff_location');
            $table->decimal('dropoff_longitude', 11, 8)->nullable()->after('dropoff_latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn([
                'distance_km',
                'eta_minutes',
                'price',
                'pickup_latitude',
                'pickup_longitude',
                'dropoff_latitude',
                'dropoff_longitude',
            ]);
        });
    }
};

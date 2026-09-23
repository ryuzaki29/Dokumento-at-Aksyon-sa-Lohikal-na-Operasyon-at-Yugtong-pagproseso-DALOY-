<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->foreignId('office_id')->constrained('offices');
            $table->unsignedSmallInteger('sequence');
            $table->json('roles')->nullable();
            $table->timestamps();

            $table->unique(['route_id', 'sequence']);
            $table->unique(['route_id', 'office_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_steps');
    }
};

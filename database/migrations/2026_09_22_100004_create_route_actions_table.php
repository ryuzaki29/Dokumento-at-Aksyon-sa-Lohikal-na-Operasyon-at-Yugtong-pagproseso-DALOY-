<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents');
            $table->foreignId('from_office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->foreignId('to_office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->string('action', 30)->index();
            $table->text('remarks')->nullable();
            $table->foreignId('acted_by')->constrained('users');
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['document_id', 'acted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_actions');
    }
};

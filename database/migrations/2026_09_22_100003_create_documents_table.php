<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 50)->unique();
            $table->foreignId('document_type_id')->constrained('document_types');
            $table->string('subject', 255);
            $table->foreignId('originating_office_id')->constrained('offices');
            $table->foreignId('current_office_id')->nullable()->constrained('offices')->index();
            $table->string('status', 30)->default('registered')->index();
            $table->string('file_path', 255)->nullable();
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

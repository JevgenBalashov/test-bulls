<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sheet_source_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'running', 'success', 'partial', 'failed'])
                ->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('rows_read')->default(0);
            $table->unsignedInteger('rows_written')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['sheet_source_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};

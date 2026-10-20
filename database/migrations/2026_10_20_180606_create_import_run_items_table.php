<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sheet_mapping_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tab_name');
            $table->string('target_table');
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->unsignedInteger('rows_read')->default(0);
            $table->unsignedInteger('rows_written')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->json('added_columns')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_run_items');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheet_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sheet_tab_id')->constrained()->cascadeOnDelete();
            $table->string('target_table');
            $table->enum('write_mode', ['insert', 'upsert'])->default('upsert');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['sheet_tab_id', 'target_table']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheet_mappings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheet_mapping_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sheet_mapping_id')->constrained()->cascadeOnDelete();
            $table->string('source_column');
            $table->string('target_column');
            $table->enum('data_type', [
                'string',
                'text',
                'integer',
                'decimal',
                'boolean',
                'date',
                'datetime',
            ])->default('string');
            $table->boolean('is_nullable')->default(true);
            $table->boolean('is_unique_key')->default(false);
            $table->timestamps();

            $table->unique(['sheet_mapping_id', 'target_column']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheet_mapping_columns');
    }
};

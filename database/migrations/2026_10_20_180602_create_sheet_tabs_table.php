<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheet_tabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sheet_source_id')->constrained()->cascadeOnDelete();
            $table->string('tab_name');
            $table->unsignedSmallInteger('header_row')->default(1);
            $table->unsignedSmallInteger('data_start_row')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['sheet_source_id', 'tab_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheet_tabs');
    }
};

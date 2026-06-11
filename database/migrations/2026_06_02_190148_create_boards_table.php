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
        Schema::disableForeignKeyConstraints();

        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained();
            $table->string('president_name');
            $table->string('vice_president_name')->nullable();
            $table->string('first_secretary_name')->nullable();
            $table->string('second_secretary_name')->nullable();
            $table->string('executive_secretary_name')->nullable();
            $table->string('treasurer_name')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boards');
    }
};

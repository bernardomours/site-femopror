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
    Schema::create('downloads', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('description')->nullable(); 
        $table->string('file_path'); 
        $table->string('icon')->default('heroicon-o-document-text'); 
        $table->boolean('is_active')->default(true); 
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('downloads');
    }
};

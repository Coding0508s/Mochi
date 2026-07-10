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
        Schema::create('store_return_registrations', function (Blueprint $table) {
            $table->id();
            $table->date('returned_at');
            $table->string('institution_sk_code', 50)->nullable()->index();
            $table->string('institution_name');
            $table->string('item_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 50);
            $table->string('freight', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('cs_team', 100)->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_return_registrations');
    }
};

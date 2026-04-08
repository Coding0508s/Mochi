<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('sk_code', 100)->index();
            $table->string('account_name', 255);
            $table->string('changed_account_name', 255)->nullable();
            $table->string('business_number', 100)->nullable();
            $table->date('document_date');
            $table->string('document_time', 8)->nullable();
            $table->string('consultant', 150)->nullable();
            $table->string('original_filename', 255);
            $table->string('stored_disk', 32)->default('local');
            $table->string('stored_path', 512);
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('uploaded_by', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_documents');
    }
};

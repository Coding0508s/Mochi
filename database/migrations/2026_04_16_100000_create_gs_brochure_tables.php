<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = (string) config('gs_brochure.table_prefix', 'gsb_');
        $brochures = $prefix.'brochures';
        $contacts = $prefix.'contacts';
        $institutions = $prefix.'institutions';
        $requests = $prefix.'requests';
        $requestItems = $prefix.'request_items';
        $invoices = $prefix.'invoices';
        $stockHistories = $prefix.'stock_histories';
        $adminUsers = $prefix.'admin_users';

        Schema::create($brochures, function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('image_url')->nullable();
            $table->integer('stock')->default(0);
            $table->integer('stock_warehouse')->default(0);
            $table->integer('last_stock_quantity')->default(0);
            $table->string('last_stock_date')->nullable();
            $table->integer('last_warehouse_stock_quantity')->default(0);
            $table->string('last_warehouse_stock_date')->nullable();
            $table->timestamps();
        });

        Schema::create($contacts, function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create($institutions, function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->text('description')->nullable();
            $table->string('address', 512)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create($requests, function (Blueprint $table) use ($contacts) {
            $table->id();
            $table->string('date');
            $table->string('schoolname');
            $table->string('address');
            $table->string('phone');
            $table->foreignId('contact_id')->nullable()->constrained($contacts)->nullOnDelete();
            $table->string('contact_name')->nullable();
            $table->timestamps();

            $table->index('date');
            $table->index('schoolname');
        });

        Schema::create($requestItems, function (Blueprint $table) use ($brochures, $requests) {
            $table->id();
            $table->foreignId('request_id')->constrained($requests)->cascadeOnDelete();
            $table->foreignId('brochure_id')->constrained($brochures);
            $table->string('brochure_name');
            $table->integer('quantity');
            $table->timestamps();

            $table->index('request_id');
        });

        Schema::create($invoices, function (Blueprint $table) use ($requests) {
            $table->id();
            $table->foreignId('request_id')->constrained($requests)->cascadeOnDelete();
            $table->string('invoice_number');
            $table->timestamps();

            $table->index('request_id');
        });

        Schema::create($stockHistories, function (Blueprint $table) use ($brochures) {
            $table->id();
            $table->string('type');
            $table->string('location')->nullable();
            $table->string('date');
            $table->foreignId('brochure_id')->constrained($brochures);
            $table->string('brochure_name');
            $table->integer('quantity');
            $table->string('contact_name')->nullable();
            $table->string('schoolname')->nullable();
            $table->integer('before_stock');
            $table->integer('after_stock');
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index('date');
            $table->index('brochure_id');
        });

        Schema::create($adminUsers, function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('password_hash');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = (string) config('gs_brochure.table_prefix', 'gsb_');
        Schema::dropIfExists($prefix.'admin_users');
        Schema::dropIfExists($prefix.'stock_histories');
        Schema::dropIfExists($prefix.'invoices');
        Schema::dropIfExists($prefix.'request_items');
        Schema::dropIfExists($prefix.'requests');
        Schema::dropIfExists($prefix.'institutions');
        Schema::dropIfExists($prefix.'contacts');
        Schema::dropIfExists($prefix.'brochures');
    }
};

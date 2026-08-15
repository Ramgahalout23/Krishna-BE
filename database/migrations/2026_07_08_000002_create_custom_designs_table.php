<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_designs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Link to the order that contains this custom design
            $table->uuid('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            
            // Index of this item within the order's items array
            $table->unsignedInteger('item_index')->default(0);

            // Customer info (denormalized for fast access)
            $table->uuid('user_id')->nullable();
            $table->string('customer_name', 100)->nullable();
            $table->string('customer_email', 100)->nullable();

            // Uploaded design file
            $table->string('design_file_path', 500)->nullable();
            $table->string('design_file_url', 500)->nullable();
            $table->string('design_filename', 255)->nullable();
            $table->string('design_mime', 50)->nullable();
            $table->unsignedInteger('design_file_size')->nullable();

            // Design specifications
            $table->string('color', 50)->nullable();
            $table->string('size', 10)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('placement', 50)->default('Front'); // Front / Back / Both
            $table->decimal('price', 12, 2)->default(0.00);

            // Customer notes
            $table->text('design_notes')->nullable();

            // Status tracking
            $table->string('status', 30)->default('PENDING_REVIEW');
            // PENDING_REVIEW → APPROVED → IN_PRODUCTION → SHIPPED → COMPLETED
            // Any except COMPLETED can go to REJECTED

            // Admin fields
            $table->text('admin_notes')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('order_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_designs');
    }
};

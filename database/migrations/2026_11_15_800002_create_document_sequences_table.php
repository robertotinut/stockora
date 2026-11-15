<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('document_sequences')) {
            Schema::create('document_sequences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('doc_type', 40);
                $table->string('period_key', 16);
                $table->unsignedBigInteger('last_seq')->default(0);
                $table->timestamps();

                $table->unique(['company_id', 'doc_type', 'period_key'], 'uq_doc_seq_company_type_period');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dte_correlatives', function (Blueprint $table) {
            $table->id();
            $table->string('establishment_code', 4);
            $table->string('pos_code', 4);
            $table->string('tipo_dte', 2);
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['establishment_code', 'pos_code', 'tipo_dte', 'fiscal_year'], 'dte_correlatives_unique_scope');
        });

        Schema::create('dte_contingency_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('codigo_generacion')->unique();
            $table->unsignedTinyInteger('motivo_code');
            $table->string('motivo_desc', 180);
            $table->string('f_inicio', 10);
            $table->string('f_fin', 10);
            $table->string('status', 24)->default('open');
            $table->string('sello_recibido', 64)->nullable();
            $table->json('event_payload_json')->nullable();
            $table->timestamps();
        });

        Schema::create('dte_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('codigo_generacion')->unique();
            $table->string('numero_control', 31)->unique();
            $table->string('tipo_dte', 2);
            $table->unsignedTinyInteger('schema_version');
            $table->string('ambiente', 2)->default('00');
            $table->unsignedTinyInteger('tipo_transmision')->default(1);
            $table->string('status', 32)->default('draft');
            $table->decimal('total_pagar', 12, 2);
            $table->string('sello_recibido', 64)->nullable();
            $table->foreignId('contingency_batch_id')->nullable()->constrained('dte_contingency_batches')->nullOnDelete();
            $table->json('payload_json');
            $table->text('signed_jws')->nullable();
            $table->json('mh_response_json')->nullable();
            $table->timestamps();
        });

        Schema::create('dte_invalidation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dte_document_id')->constrained('dte_documents')->cascadeOnDelete();
            $table->uuid('codigo_generacion')->unique();
            $table->unsignedTinyInteger('tipo_anulacion');
            $table->string('motivo', 250);
            $table->string('sello_recibido', 64);
            $table->json('event_payload_json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dte_invalidation_events');
        Schema::dropIfExists('dte_documents');
        Schema::dropIfExists('dte_contingency_batches');
        Schema::dropIfExists('dte_correlatives');
    }
};

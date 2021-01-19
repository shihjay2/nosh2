<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFhirJsonTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fhir_json', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('table', 255)->nullable();
            $table->bigInteger('index')->nullable();
            $table->longtext('json')->nullable();
            $table->string('transaction', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fhir_json');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePracticeinfoPlusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('practiceinfo_plus', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('practice_id')->nullable();
            $table->json('private_jwk')->nullable();
            $table->json('public_jwk')->nullable();
            $table->string('gnap_uri', 255)->nullable();
            $table->string('gnap_client_id', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('practiceinfo_plus');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGnapUriToDemographicsPlusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('demographics_plus', function (Blueprint $table) {
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
        Schema::table('demographics_plus', function (Blueprint $table) {
            $table->dropColumn('gnap_uri');
            $table->dropColumn('gnap_client_id');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRsLocationsToDemographicsPlusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('demographics_plus', function (Blueprint $table) {
            $table->json('rs_locations')->nullable();
            $table->string('resource_handle', 255)->nullable();
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
            $table->dropColumn('rs_locations');
            $table->dropColumn('resource_handle');
        });
    }
}

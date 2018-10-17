<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInsuranceInsuCountryToInsuranceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('insurance', function (Blueprint $table) {
            $table->string('insurance_insu_country', 255)->default('United States');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('insurance', function (Blueprint $table) {
            $table->dropColumn('insurance_insu_country');
        });
    }
}

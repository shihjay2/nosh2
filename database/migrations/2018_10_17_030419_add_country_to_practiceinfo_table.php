<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCountryToPracticeinfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('practiceinfo', function (Blueprint $table) {
            $table->string('country', 255)->default('United States');
            $table->string('billing_country', 255)->default('United States');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('practiceinfo', function (Blueprint $table) {
            $table->dropColumn('country');
            $table->dropColumn('billing_country');
        });
    }
}

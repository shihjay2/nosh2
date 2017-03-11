<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddWtPercentileInVitalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vitals', function (Blueprint $table) {
            $table->string('wt_percentile', 255)->nullable();
            $table->string('ht_percentile', 255)->nullable();
            $table->string('hc_percentile', 255)->nullable();
            $table->string('wt_ht_percentile', 255)->nullable();
            $table->string('bmi_percentile', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vitals', function (Blueprint $table) {
            $table->dropColumn('wt_percentile');
            $table->dropColumn('ht_percentile');
            $table->dropColumn('hc_percentile');
            $table->dropColumn('wt_ht_percentile');
            $table->dropColumn('bmi_percentile');
        });
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOhDietInOtherHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('other_history', function (Blueprint $table) {
            $table->longtext('oh_diet')->nullable();
            $table->longtext('oh_physical_activity')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('other_history', function (Blueprint $table) {
            $table->dropColumn('oh_diet');
            $table->dropColumn('oh_physical_activity');
        });
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddReminderIntervalInPracticeinfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('practiceinfo', function (Blueprint $table) {
            $table->string('reminder_interval', 255)->nullable();
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
            $table->dropColumn('reminder_interval');
        });
    }
}

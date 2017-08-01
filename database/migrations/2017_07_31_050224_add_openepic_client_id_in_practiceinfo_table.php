<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOpenepicClientIdInPracticeinfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('practiceinfo', function (Blueprint $table) {
            $table->string('openepic_client_id', 255)->nullable();
            $table->string('openepic_sandbox_client_id', 255)->nullable();
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
            $table->dropColumn('openepic_client_id');
            $table->dropColumn('openepic_sandbox_client_id');
        });
    }
}

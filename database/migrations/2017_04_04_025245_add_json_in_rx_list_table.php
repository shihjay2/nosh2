<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddJsonInRxListTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rx_list', function (Blueprint $table) {
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
        Schema::table('rx_list', function (Blueprint $table) {
            $table->dropColumn('json');
            $table->dropColumn('transaction');
        });
    }
}

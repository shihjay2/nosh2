<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeOrdersLabsIcdInOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->longText('orders_labs_icd')->change();
            $table->longText('orders_radiology_icd')->change();
            $table->longText('orders_cp_icd')->change();
            $table->longText('orders_referrals_icd')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('orders_labs_icd', 255)->change();
            $table->string('orders_radiology_icd', 255)->change();
            $table->string('orders_cp_icd', 255)->change();
            $table->string('orders_referrals_icd', 255)->change();
        });
    }
}

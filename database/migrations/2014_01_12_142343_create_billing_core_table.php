<?php

use Illuminate\Database\Migrations\Migration;

class CreateBillingcoreTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('billing_core')) {
			Schema::create('billing_core', function($table) {
				$table->bigInteger('billing_core_id')->primary();
				$table->bigInteger('eid')->nullable();
				$table->bigInteger('pid')->nullable();
				$table->bigInteger('other_billing_id')->nullable();
				$table->string('cpt', 5)->nullable();
				$table->string('cpt_charge', 6)->nullable();
				$table->string('icd_pointer', 4)->nullable();
				$table->string('unit', 1)->nullable();
				$table->string('modifier', 2)->nullable();
				$table->string('dos_f', 10)->nullable();
				$table->string('dos_t', 10)->nullable();
				$table->string('billing_group', 1)->nullable();
				$table->string('payment', 6)->nullable();
				$table->string('reason', 255)->nullable();
				$table->string('payment_type', 255)->nullable();
				$table->integer('practice_id')->nullable();
			});
		}
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('billing_core');
    }

}

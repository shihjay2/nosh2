<?php

use Illuminate\Database\Migrations\Migration;

class CreateOrdersTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('orders')) {
			Schema::create('orders', function($table) {
				$table->bigIncrements('orders_id');
				$table->bigInteger('address_id')->nullable();
				$table->bigInteger('eid')->nullable();
				$table->bigInteger('t_messages_id')->nullable();
				$table->bigInteger('pid')->nullable();
				$table->string('encounter_provider', 255)->nullable();
				$table->timestamp('orders_date')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
				$table->string('orders_insurance', 255)->nullable();
				$table->longtext('orders_referrals')->nullable();
				$table->longtext('orders_labs')->nullable();
				$table->longtext('orders_radiology')->nullable();
				$table->longtext('orders_cp')->nullable();
				$table->string('orders_referrals_icd', 255)->nullable();
				$table->string('orders_labs_icd', 255)->nullable();
				$table->string('orders_radiology_icd', 255)->nullable();
				$table->string('orders_cp_icd', 255)->nullable();
				$table->string('orders_labs_obtained', 255)->nullable();
				$table->boolean('orders_completed')->nullable();
				$table->bigInteger('id')->nullable();
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
        Schema::drop('orders');
    }

}

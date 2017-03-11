<?php

use Illuminate\Database\Migrations\Migration;

class CreateOrderslist1Table extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('orderslist1')) {
			Schema::create('orderslist1', function($table) {
				$table->increments('orderslist1_id');
				$table->bigInteger('orders_code')->nullable();
				$table->string('orders_category', 255)->nullable();
				$table->string('orders_vendor', 255)->nullable();
				$table->string('cpt', 255)->nullable();
				$table->longtext('orders_description')->nullable();
				$table->bigInteger('result_code')->nullable();
				$table->string('result_name', 255)->nullable();
				$table->string('units', 255)->nullable();
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
        Schema::drop('orderslist1');
    }

}

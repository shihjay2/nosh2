<?php

use Illuminate\Database\Migrations\Migration;

class CreateOrderslistTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('orderslist')) {
			Schema::create('orderslist', function($table) {
				$table->increments('orderslist_id');
				$table->bigInteger('user_id')->nullable();
				$table->string('orders_category', 255)->nullable();
				$table->string('cpt', 255)->nullable();
				$table->longtext('orders_description')->nullable();
				$table->string('snomed', 255)->nullable();
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
        Schema::drop('orderslist');
    }

}

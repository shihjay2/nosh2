<?php

use Illuminate\Database\Migrations\Migration;

class CreateAlertsTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('alerts')) {
			Schema::create('alerts', function($table) {
				$table->increments('alert_id');
				$table->bigInteger('pid')->nullable();
				$table->bigInteger('orders_id')->nullable();
				$table->string('alert', 255)->nullable();
				$table->longtext('alert_description')->nullable();
				$table->dateTime('alert_date_active')->nullable();
				$table->dateTime('alert_date_complete')->nullable();
				$table->string('alert_reason_not_complete', 255)->nullable();
				$table->bigInteger('alert_provider')->nullable();
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
        Schema::drop('alerts');
    }

}

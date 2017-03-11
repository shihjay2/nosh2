<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHippaRequestTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('hippa_request', function(Blueprint $table)
		{
			$table->increments('hippa_request_id');
			$table->bigInteger('pid')->nullable();
			$table->dateTime('hippa_date_request')->nullable();
			$table->string('request_reason', 255)->nullable();
			$table->string('request_type', 255)->nullable();
			$table->longtext('request_to')->nullable();
			$table->bigInteger('address_id')->nullable();
			$table->string('history_physical', 255)->nullable();
			$table->string('lab_type', 255)->nullable();
			$table->string('lab_date', 100)->nullable();
			$table->string('op', 255)->nullable();
			$table->string('accident_f', 100)->nullable();
			$table->string('accident_t', 100)->nullable();
			$table->longtext('other')->nullable();
			$table->integer('practice_id')->nullable();
			$table->string('received', 10)->nullable()->default('No');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('hippa_request');
	}

}

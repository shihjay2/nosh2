<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateApiQueueTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('api_queue', function(Blueprint $table)
		{
			$table->increments('id');
			$table->string('table', 100)->nullable();
			$table->string('primary', 100)->nullable();
			$table->bigInteger('local_id')->nullable();
			$table->bigInteger('remote_id')->nullable();
			$table->string('action', 100)->nullable();
			$table->longtext('json')->nullable();
			$table->longtext('login')->nullable();
			$table->string('url', 100)->nullable();
			$table->string('api_key', 100)->nullable();
			$table->longtext('response')->nullable();
			$table->string('success', 4)->nullable()->default('n');
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('api_queue');
	}

}

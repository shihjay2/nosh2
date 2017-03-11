<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRefreshTokensTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('refresh_tokens', function(Blueprint $table)
		{
			$table->increments('id');
			$table->string('refresh_token', 255)->nullable();
			$table->integer('pid')->nullable();
			$table->integer('practice_id')->nullable();
			$table->bigInteger('user_id')->nullable();
			$table->longtext('endpoint_uri')->nullable();
			$table->string('client_id', 255)->nullable();
			$table->string('client_secret', 255)->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('refresh_tokens');
	}

}

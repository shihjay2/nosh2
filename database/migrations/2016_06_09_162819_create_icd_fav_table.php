<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIcdFavTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('icd_fav', function(Blueprint $table)
		{
			$table->increments('icd_fav_id');
			$table->bigInteger('id')->nullable();
			$table->string('icd', 255)->nullable();
			$table->string('icd_description', 255)->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('icd_fav');
	}

}

<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIcd10Table extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('icd10', function($table) {
			$table->increments('icd10_id');
			$table->string('icd10', 255)->nullable();
			$table->string('icd10_description', 255)->nullable();
			$table->boolean('icd10_common')->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('icd10');
	}

}

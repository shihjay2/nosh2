<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class EditImageTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('image', function(Blueprint $table)
		{
			$table->dropColumn('image_id');
		});
		Schema::table('image', function(Blueprint $table)
		{
			$table->bigIncrements('image_id');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('image', function(Blueprint $table)
		{
			$table->dropColumn('image_id');
		});
		Schema::table('image', function(Blueprint $table)
		{
			$table->bigInteger('image_id')->primary();
		});
	}

}

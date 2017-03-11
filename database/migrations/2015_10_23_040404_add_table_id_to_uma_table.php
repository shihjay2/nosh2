<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTableIdToUmaTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('uma', function(Blueprint $table)
		{
			$table->integer('table_id')->nullable();
			$table->string('table', 255)->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('uma', function(Blueprint $table)
		{
			$table->dropColumn('table_id');
			$table->dropColumn('table');
		});
	}

}

<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSupplementsToPracticeinfoTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('practiceinfo', function(Blueprint $table)
		{
			$table->string('supplements_menu_item', 100)->nullable()->default('y');
			$table->string('immunizations_menu_item', 100)->nullable()->default('y');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('practiceinfo', function(Blueprint $table)
		{
			$table->dropColumn('supplements_menu_item');
			$table->dropColumn('immunizations_menu_item');
		});
	}

}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddBillComplexToEncountersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('encounters', function(Blueprint $table) {
			$table->string('bill_complex', 255)->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('encounters', function(Blueprint $table) {
			$table->dropColumn('bill_complex');
		});
	}

}

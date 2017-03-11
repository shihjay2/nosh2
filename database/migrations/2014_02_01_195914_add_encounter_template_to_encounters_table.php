<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddEncounterTemplateToEncountersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('encounters', function(Blueprint $table) {
			$table->string('encounter_template', 255)->nullable();
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
			$table->dropColumn('encounter_template');
		});
	}

}

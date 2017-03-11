<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEncounterTemplateToPracticeinfoTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('practiceinfo', function(Blueprint $table)
		{
			$table->string('encounter_template', 100)->nullable()->default('standardmedical');
			$table->string('fax_email_smtp', 100)->nullable()->default('smtp.gmail.com');
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
			$table->dropColumn('encounter_template');
			$table->dropColumn('fax_email_smtp');
		});
	}

}

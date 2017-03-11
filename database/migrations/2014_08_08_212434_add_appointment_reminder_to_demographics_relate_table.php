<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAppointmentReminderToDemographicsRelateTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('demographics_relate', function(Blueprint $table)
		{
			$table->string('appointment_reminder', 100)->nullable()->default('n');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('demographics_relate', function(Blueprint $table)
		{
			$table->dropColumn('appointment_reminder');
		});
	}

}

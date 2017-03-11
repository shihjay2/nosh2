<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAppointmentExtensionToPracticeinfoTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('practiceinfo', function(Blueprint $table)
		{
			$table->string('appointment_extension', 4)->nullable()->default('n');
			$table->string('appointment_sent_date', 100)->nullable();
			$table->longtext('appointment_message')->nullable()->default('');
			$table->string('appointment_interval', 100)->nullable()->default('31556926');
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
			$table->dropColumn('appointment_extension');
			$table->dropColumn('appointment_sent_date');
			$table->dropColumn('appointment_message');
			$table->dropColumn('appointment_interval');
		});
	}

}

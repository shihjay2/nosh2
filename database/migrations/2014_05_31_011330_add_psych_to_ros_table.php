<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPsychToRosTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('ros', function(Blueprint $table)
		{
			$table->longtext('ros_psych1')->nullable();
			$table->longtext('ros_psych2')->nullable();
			$table->longtext('ros_psych3')->nullable();
			$table->longtext('ros_psych4')->nullable();
			$table->longtext('ros_psych5')->nullable();
			$table->longtext('ros_psych6')->nullable();
			$table->longtext('ros_psych7')->nullable();
			$table->longtext('ros_psych8')->nullable();
			$table->longtext('ros_psych9')->nullable();
			$table->longtext('ros_psych10')->nullable();
			$table->longtext('ros_psych11')->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('ros', function(Blueprint $table)
		{
			$table->dropColumn('psych1');
			$table->dropColumn('psych2');
			$table->dropColumn('psych3');
			$table->dropColumn('psych4');
			$table->dropColumn('psych5');
			$table->dropColumn('psych6');
			$table->dropColumn('psych7');
			$table->dropColumn('psych8');
			$table->dropColumn('psych9');
			$table->dropColumn('psych10');
			$table->dropColumn('psych11');
		});
	}

}

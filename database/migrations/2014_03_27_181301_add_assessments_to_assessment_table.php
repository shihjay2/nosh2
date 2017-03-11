<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAssessmentsToAssessmentTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('assessment', function(Blueprint $table)
		{
			$table->string('assessment_icd9', 20)->nullable();
			$table->string('assessment_icd10', 20)->nullable();
			$table->string('assessment_icd11', 20)->nullable();
			$table->string('assessment_icd12', 20)->nullable();
			$table->longtext('assessment_9')->nullable();
			$table->longtext('assessment_10')->nullable();
			$table->longtext('assessment_11')->nullable();
			$table->longtext('assessment_12')->nullable();
		});
		Schema::table('billing', function(Blueprint $table)
		{
			$table->string('bill_Box21_5', 8)->nullable();
			$table->string('bill_Box21_6', 8)->nullable();
			$table->string('bill_Box21_7', 8)->nullable();
			$table->string('bill_Box21_8', 8)->nullable();
			$table->string('bill_Box21_9', 8)->nullable();
			$table->string('bill_Box21_10', 8)->nullable();
			$table->string('bill_Box21_11', 8)->nullable();
			$table->string('bill_Box21_12', 8)->nullable();
			$table->string('bill_Box21A', 8)->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('assessment', function(Blueprint $table)
		{
			$table->dropColumn('assessment_icd9');
			$table->dropColumn('assessment_icd10');
			$table->dropColumn('assessment_icd11');
			$table->dropColumn('assessment_icd12');
			$table->dropColumn('assessment_9');
			$table->dropColumn('assessment_10');
			$table->dropColumn('assessment_11');
			$table->dropColumn('assessment_12');
		});
		Schema::table('billing', function(Blueprint $table)
		{
			$table->dropColumn('bill_Box21_5');
			$table->dropColumn('bill_Box21_6');
			$table->dropColumn('bill_Box21_7');
			$table->dropColumn('bill_Box21_8');
			$table->dropColumn('bill_Box21_9');
			$table->dropColumn('bill_Box21_10');
			$table->dropColumn('bill_Box21_11');
			$table->dropColumn('bill_Box21_12');
			$table->dropColumn('bill_Box21A');
		});
	}

}

<?php

use Illuminate\Database\Migrations\Migration;

class CreateAssessmentTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('assessment')) {
			Schema::create('assessment', function($table) {
				$table->bigInteger('eid')->primary();
				$table->bigInteger('pid')->nullable();
				$table->string('encounter_provider', 255)->nullable();
				$table->timestamp('assessment_date')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
				$table->string('assessment_icd1', 20)->nullable();
				$table->string('assessment_icd2', 20)->nullable();
				$table->string('assessment_icd3', 20)->nullable();
				$table->string('assessment_icd4', 20)->nullable();
				$table->string('assessment_icd5', 20)->nullable();
				$table->string('assessment_icd6', 20)->nullable();
				$table->string('assessment_icd7', 20)->nullable();
				$table->string('assessment_icd8', 20)->nullable();
				$table->longtext('assessment_1')->nullable();
				$table->longtext('assessment_2')->nullable();
				$table->longtext('assessment_3')->nullable();
				$table->longtext('assessment_4')->nullable();
				$table->longtext('assessment_5')->nullable();
				$table->longtext('assessment_6')->nullable();
				$table->longtext('assessment_7')->nullable();
				$table->longtext('assessment_8')->nullable();
				$table->longtext('assessment_other')->nullable();
				$table->longtext('assessment_ddx')->nullable();
				$table->longtext('assessment_notes')->nullable();
			});
		}
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('assessment');
    }

}

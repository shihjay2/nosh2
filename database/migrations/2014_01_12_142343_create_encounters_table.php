<?php

use Illuminate\Database\Migrations\Migration;

class CreateEncountersTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('encounters')) {
			Schema::create('encounters', function($table) {
				$table->bigIncrements('eid');
				$table->bigInteger('pid')->nullable();
				$table->bigInteger('appt_id')->nullable();
				$table->string('encounter_provider', 255)->nullable();
				$table->timestamp('encounter_date')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
				$table->string('encounter_signed', 4)->nullable();
				$table->timestamp('date_signed')->default("0000-00-00 00:00:00");
				$table->dateTime('encounter_DOS')->nullable();
				$table->string('encounter_age', 100)->nullable();
				$table->string('encounter_type', 100)->nullable();
				$table->string('encounter_location', 100)->nullable();
				$table->string('encounter_activity', 100)->nullable();
				$table->longtext('encounter_cc')->nullable();
				$table->string('encounter_condition', 255)->nullable();
				$table->string('encounter_condition_work', 4)->nullable();
				$table->string('encounter_condition_auto', 4)->nullable();
				$table->string('encounter_condition_auto_state', 2)->nullable();
				$table->string('encounter_condition_other', 4)->nullable();
				$table->string('bill_submitted', 4)->nullable();
				$table->string('addendum', 4)->nullable();
				$table->bigInteger('addendum_eid')->nullable();
				$table->integer('user_id')->nullable();
				$table->string('encounter_role', 255)->nullable();
				$table->string('referring_provider', 255)->nullable();
				$table->integer('practice_id')->nullable();
				$table->string('referring_provider_npi', 255)->nullable();
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
        Schema::drop('encounters');
    }

}

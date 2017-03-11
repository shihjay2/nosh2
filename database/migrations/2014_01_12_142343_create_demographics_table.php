<?php

use Illuminate\Database\Migrations\Migration;

class CreateDemographicsTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('demographics')) {
			Schema::create('demographics', function($table) {
				$table->bigIncrements('pid');
				$table->bigInteger('id')->nullable();
				$table->string('lastname', 100)->nullable();
				$table->string('firstname', 100)->nullable();
				$table->string('middle', 100)->nullable();
				$table->string('nickname', 100)->nullable();
				$table->string('title', 100)->nullable();
				$table->string('sex', 100)->nullable();
				$table->dateTime('DOB')->nullable();
				$table->string('ss', 100)->nullable();
				$table->string('race', 100)->nullable();
				$table->string('ethnicity', 100)->nullable();
				$table->string('language', 100)->nullable();
				$table->string('address', 255)->nullable();
				$table->string('city', 100)->nullable();
				$table->string('state', 100)->nullable();
				$table->string('zip', 100)->nullable();
				$table->string('phone_home', 100)->nullable();
				$table->string('phone_work', 100)->nullable();
				$table->string('phone_cell', 100)->nullable();
				$table->string('email', 100)->nullable();
				$table->string('marital_status', 100)->nullable();
				$table->string('partner_name', 255)->nullable();
				$table->string('employer', 100)->nullable();
				$table->string('emergency_contact', 100)->nullable();
				$table->string('emergency_phone', 100)->nullable();
				$table->string('reminder_method', 100)->nullable();
				$table->string('cell_carrier', 100)->nullable();
				$table->string('reminder_to', 100)->nullable();
				$table->string('photo', 255)->nullable();
				$table->string('preferred_provider', 255)->nullable();
				$table->string('preferred_pharmacy', 255)->nullable();
				$table->boolean('active')->nullable();
				$table->timestamp('date')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
				$table->string('other1', 255)->nullable();
				$table->string('other2', 255)->nullable();
				$table->longtext('comments')->nullable();
				$table->string('tobacco', 5)->nullable();
				$table->string('sexuallyactive', 5)->nullable();
				$table->string('pregnant', 255)->nullable();
				$table->string('caregiver', 255)->nullable();
				$table->string('referred_by', 255)->nullable();
				$table->longtext('billing_notes')->nullable();
				$table->longtext('imm_notes')->nullable();
				$table->string('rcopia_sync', 4)->nullable();
				$table->string('rcopia_update_medications', 4)->nullable();
				$table->string('rcopia_update_medications_date', 20)->nullable();
				$table->string('rcopia_update_allergy', 4)->nullable();
				$table->string('rcopia_update_allergy_date', 20)->nullable();
				$table->string('rcopia_update_prescription', 4)->nullable();
				$table->string('rcopia_update_prescription_date', 20)->nullable();
				$table->string('registration_code', 255)->nullable();
				$table->string('race_code', 100)->nullable();
				$table->string('ethnicity_code', 100)->nullable();
				$table->string('guardian_firstname', 255)->nullable();
				$table->string('guardian_lastname', 255)->nullable();
				$table->string('guardian_code', 100)->nullable();
				$table->string('guardian_address', 255)->nullable();
				$table->string('guardian_city', 100)->nullable();
				$table->string('guardian_state', 100)->nullable();
				$table->string('guardian_zip', 100)->nullable();
				$table->string('guardian_phone_home', 100)->nullable();
				$table->string('guardian_phone_work', 100)->nullable();
				$table->string('guardian_phone_cell', 100)->nullable();
				$table->string('guardian_email', 100)->nullable();
				$table->string('guardian_relationship', 100)->nullable();
				$table->string('lang_code', 100)->nullable();
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
        Schema::drop('demographics');
    }

}

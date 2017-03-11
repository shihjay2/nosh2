<?php

use Illuminate\Database\Migrations\Migration;

class CreatePracticeinfoTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('practiceinfo')) {
			Schema::create('practiceinfo', function($table) {
				$table->increments('practice_id');
				$table->string('practice_name', 255)->nullable();
				$table->string('street_address1', 255)->nullable();
				$table->string('street_address2', 255)->nullable();
				$table->string('city', 100)->nullable();
				$table->string('state', 100)->nullable();
				$table->string('zip', 100)->nullable();
				$table->string('phone', 100)->nullable();
				$table->string('fax', 100)->nullable();
				$table->string('email', 100)->nullable();
				$table->string('website', 255)->nullable();
				$table->string('primary_contact', 100)->nullable();
				$table->string('npi', 100)->nullable();
				$table->string('medicare', 100)->nullable();
				$table->string('tax_id', 100)->nullable();
				$table->string('weight_unit', 100)->nullable();
				$table->string('height_unit', 100)->nullable();
				$table->string('temp_unit', 100)->nullable();
				$table->string('hc_unit', 100)->nullable();
				$table->string('sun_o', 10)->nullable();
				$table->string('sun_c', 10)->nullable();
				$table->string('mon_o', 10)->nullable();
				$table->string('mon_c', 10)->nullable();
				$table->string('tue_o', 10)->nullable();
				$table->string('tue_c', 10)->nullable();
				$table->string('wed_o', 10)->nullable();
				$table->string('wed_c', 10)->nullable();
				$table->string('thu_o', 10)->nullable();
				$table->string('thu_c', 10)->nullable();
				$table->string('fri_o', 10)->nullable();
				$table->string('fri_c', 10)->nullable();
				$table->string('sat_o', 10)->nullable();
				$table->string('sat_c', 10)->nullable();
				$table->string('minTime', 10)->nullable();
				$table->string('maxTime', 10)->nullable();
				$table->boolean('weekends')->nullable();
				$table->boolean('default_pos_id')->nullable();
				$table->string('documents_dir', 255)->nullable();
				$table->string('billing_street_address1', 255)->nullable();
				$table->string('billing_street_address2', 255)->nullable();
				$table->string('billing_city', 100)->nullable();
				$table->string('billing_state', 100)->nullable();
				$table->string('billing_zip', 100)->nullable();
				$table->string('fax_type', 100)->nullable();
				$table->string('fax_email', 100)->nullable();
				$table->string('fax_email_password', 100)->nullable();
				$table->string('fax_email_hostname', 100)->nullable();
				$table->string('smtp_user', 100)->nullable();
				$table->string('smtp_pass', 100)->nullable();
				$table->string('patient_portal', 255)->nullable();
				$table->string('rcopia_extension', 4)->nullable();
				$table->string('rcopia_apiVendor', 100)->nullable();
				$table->string('rcopia_apiPass', 100)->nullable();
				$table->string('rcopia_apiPractice', 100)->nullable();
				$table->string('rcopia_apiSystem', 100)->nullable();
				$table->string('rcopia_update_notification_lastupdate', 100)->nullable();
				$table->string('updox_extension', 4)->nullable();
				$table->string('version', 20)->nullable();
				$table->string('mtm_extension', 4)->nullable();
				$table->string('practice_logo', 255)->nullable();
				$table->string('mtm_logo', 255)->nullable();
				$table->longtext('mtm_alert_users')->nullable();
				$table->longtext('additional_message')->nullable();
				$table->string('snomed_extension', 4)->nullable();
				$table->string('vivacare', 255)->nullable();
				$table->string('sales_tax', 10)->nullable();
				$table->string('practicehandle', 255)->nullable();
				$table->string('peacehealth_id', 100)->nullable();
				$table->string('active', 10)->nullable();
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
        Schema::drop('practiceinfo');
    }

}

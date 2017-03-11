<?php

use Illuminate\Database\Migrations\Migration;

class CreateInsuranceTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('insurance')) {
			Schema::create('insurance', function($table) {
				$table->increments('insurance_id');
				$table->bigInteger('pid')->nullable();
				$table->bigInteger('address_id')->nullable();
				$table->string('insurance_plan_name', 255)->nullable();
				$table->string('insurance_order', 255)->nullable();
				$table->string('insurance_id_num', 255)->nullable();
				$table->string('insurance_group', 255)->nullable();
				$table->string('insurance_relationship', 255)->nullable();
				$table->string('insurance_copay', 255)->nullable();
				$table->string('insurance_deductible', 255)->nullable();
				$table->longtext('insurance_comments')->nullable();
				$table->string('insurance_plan_active', 255)->nullable();
				$table->string('insurance_insu_firstname', 255)->nullable();
				$table->string('insurance_insu_lastname', 255)->nullable();
				$table->string('insurance_insu_address', 255)->nullable();
				$table->string('insurance_insu_city', 255)->nullable();
				$table->string('insurance_insu_state', 255)->nullable();
				$table->string('insurance_insu_zip', 255)->nullable();
				$table->string('insurance_insu_phone', 255)->nullable();
				$table->dateTime('insurance_insu_dob')->nullable();
				$table->string('insurance_insu_gender', 255)->nullable();
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
        Schema::drop('insurance');
    }

}

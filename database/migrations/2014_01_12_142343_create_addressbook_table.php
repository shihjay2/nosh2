<?php

use Illuminate\Database\Migrations\Migration;

class CreateAddressbookTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('addressbook')) {
			Schema::create('addressbook', function($table) {
				$table->increments('address_id');
				$table->string('specialty', 100)->nullable();
				$table->string('displayname', 255)->nullable();
				$table->string('lastname', 100)->nullable();
				$table->string('firstname', 100)->nullable();
				$table->string('facility', 100)->nullable();
				$table->string('prefix', 100)->nullable();
				$table->string('suffix', 100)->nullable();
				$table->string('street_address1', 255)->nullable();
				$table->string('street_address2', 255)->nullable();
				$table->string('city', 100)->nullable();
				$table->string('state', 100)->nullable();
				$table->string('zip', 100)->nullable();
				$table->string('phone', 100)->nullable();
				$table->string('fax', 100)->nullable();
				$table->string('email', 100)->nullable();
				$table->string('comments', 255)->nullable();
				$table->string('insurance_plan_payor_id', 255)->nullable();
				$table->string('insurance_plan_type', 255)->nullable();
				$table->string('insurance_plan_assignment', 4)->nullable();
				$table->string('insurance_plan_ppa_phone', 255)->nullable();
				$table->string('insurance_plan_ppa_fax', 255)->nullable();
				$table->string('insurance_plan_ppa_url', 255)->nullable();
				$table->string('insurance_plan_mpa_phone', 255)->nullable();
				$table->string('insurance_plan_mpa_fax', 255)->nullable();
				$table->string('insurance_plan_mpa_url', 255)->nullable();
				$table->string('ordering_id', 255)->nullable();
				$table->string('insurance_box_31', 4)->nullable();
				$table->string('insurance_box_32a', 4)->nullable();
				$table->string('npi', 255)->nullable();
				$table->string('electronic_order', 255)->nullable();
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
        Schema::drop('addressbook');
    }

}

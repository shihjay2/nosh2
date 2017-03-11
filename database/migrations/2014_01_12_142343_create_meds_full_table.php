<?php

use Illuminate\Database\Migrations\Migration;

class CreateMedsfullTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('meds_full')) {
			Schema::create('meds_full', function($table) {
				$table->string('PRODUCTNDC', 255)->primary();
				$table->string('PRODUCTTYPENAME', 255)->nullable();
				$table->string('PROPRIETARYNAME', 255)->nullable();
				$table->string('PROPRIETARYNAMESUFFIX', 255)->nullable();
				$table->string('NONPROPRIETARYNAME', 255)->nullable();
				$table->string('DOSAGEFORMNAME', 255)->nullable();
				$table->string('ROUTENAME', 255)->nullable();
				$table->string('STARTMARKETINGDATE', 255)->nullable();
				$table->string('ENDMARKETINGDATE', 255)->nullable();
				$table->string('MARKETINGCATEGORYNAME', 255)->nullable();
				$table->string('APPLICATIONNUMBER', 255)->nullable();
				$table->string('LABELERNAME', 255)->nullable();
				$table->string('SUBSTANCENAME', 255)->nullable();
				$table->string('ACTIVE_NUMERATOR_STRENGTH', 255)->nullable();
				$table->string('ACTIVE_INGRED_UNIT', 255)->nullable();
				$table->string('PHARM_CLASSES', 255)->nullable();
				$table->string('DEASCHEDULE', 255)->nullable();
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
        Schema::drop('meds_full');
    }

}

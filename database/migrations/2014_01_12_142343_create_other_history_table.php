<?php

use Illuminate\Database\Migrations\Migration;

class CreateOtherhistoryTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('other_history')) {
			Schema::create('other_history', function($table) {
				$table->bigIncrements('oh_id');
				$table->bigInteger('eid')->nullable();
				$table->bigInteger('pid')->nullable();
				$table->string('encounter_provider', 255)->nullable();
				$table->timestamp('oh_date')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
				$table->longtext('oh_pmh')->nullable();
				$table->longtext('oh_psh')->nullable();
				$table->longtext('oh_fh')->nullable();
				$table->longtext('oh_sh')->nullable();
				$table->longtext('oh_etoh')->nullable();
				$table->longtext('oh_tobacco')->nullable();
				$table->longtext('oh_drugs')->nullable();
				$table->longtext('oh_employment')->nullable();
				$table->longtext('oh_meds')->nullable();
				$table->longtext('oh_supplements')->nullable();
				$table->longtext('oh_allergies')->nullable();
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
        Schema::drop('other_history');
    }

}

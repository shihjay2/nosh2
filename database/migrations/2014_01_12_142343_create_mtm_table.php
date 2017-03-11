<?php

use Illuminate\Database\Migrations\Migration;

class CreateMtmTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('mtm')) {
			Schema::create('mtm', function($table) {
				$table->increments('mtm_id');
				$table->bigInteger('pid')->nullable();
				$table->longtext('mtm_description')->nullable();
				$table->longtext('mtm_recommendations')->nullable();
				$table->longtext('mtm_beneficiary_notes')->nullable();
				$table->string('complete', 4)->nullable();
				$table->longtext('mtm_action')->nullable();
				$table->longtext('mtm_outcome')->nullable();
				$table->longtext('mtm_related_conditions')->nullable();
				$table->string('mtm_duration', 255)->nullable();
				$table->date('mtm_date_completed')->nullable();
				$table->integer('practice_id')->nullable();
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
        Schema::drop('mtm');
    }

}

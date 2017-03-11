<?php

use Illuminate\Database\Migrations\Migration;

class CreateImmunizationsTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('immunizations')) {
			Schema::create('immunizations', function($table) {
				$table->increments('imm_id');
				$table->bigInteger('pid')->nullable();
				$table->bigInteger('eid')->nullable();
				$table->string('cpt', 255)->nullable();
				$table->dateTime('imm_date')->nullable();
				$table->string('imm_immunization', 255)->nullable();
				$table->string('imm_sequence', 255)->nullable();
				$table->string('imm_body_site', 255)->nullable();
				$table->string('imm_dosage', 255)->nullable();
				$table->string('imm_dosage_unit', 255)->nullable();
				$table->string('imm_route', 255)->nullable();
				$table->string('imm_elsewhere', 255)->nullable();
				$table->string('imm_vis', 255)->nullable();
				$table->string('imm_lot', 255)->nullable();
				$table->string('imm_manufacturer', 255)->nullable();
				$table->dateTime('imm_expiration')->nullable();
				$table->string('imm_brand', 255)->nullable();
				$table->string('imm_cvxcode', 255)->nullable();
				$table->string('imm_provider', 255)->nullable();
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
        Schema::drop('immunizations');
    }

}

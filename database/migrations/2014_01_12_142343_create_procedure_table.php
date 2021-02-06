<?php

use Illuminate\Database\Migrations\Migration;

class CreateProcedureTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('procedure')) {
			Schema::create('procedure', function($table) {
				$table->bigInteger('eid')->primary();
				$table->bigInteger('pid')->nullable();
				$table->string('encounter_provider', 255)->nullable();
				$table->timestamp('proc_date')->useCurrent();
				$table->string('proc_type', 100)->nullable();
				$table->string('proc_cpt', 5)->nullable();
				$table->longtext('proc_description')->nullable();
				$table->longtext('proc_complications')->nullable();
				$table->string('proc_ebl', 100)->nullable();
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
        Schema::drop('procedure');
    }

}

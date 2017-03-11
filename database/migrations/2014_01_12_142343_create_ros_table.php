<?php

use Illuminate\Database\Migrations\Migration;

class CreateRosTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('ros')) {
			Schema::create('ros', function($table) {
				$table->bigInteger('eid')->primary();
				$table->bigInteger('pid')->nullable();
				$table->string('encounter_provider', 255)->nullable();
				$table->timestamp('ros_date')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
				$table->longtext('ros_gen')->nullable();
				$table->longtext('ros_eye')->nullable();
				$table->longtext('ros_ent')->nullable();
				$table->longtext('ros_resp')->nullable();
				$table->longtext('ros_cv')->nullable();
				$table->longtext('ros_gi')->nullable();
				$table->longtext('ros_gu')->nullable();
				$table->longtext('ros_mus')->nullable();
				$table->longtext('ros_neuro')->nullable();
				$table->longtext('ros_psych')->nullable();
				$table->longtext('ros_heme')->nullable();
				$table->longtext('ros_endocrine')->nullable();
				$table->longtext('ros_skin')->nullable();
				$table->longtext('ros_wcc')->nullable();
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
        Schema::drop('ros');
    }

}

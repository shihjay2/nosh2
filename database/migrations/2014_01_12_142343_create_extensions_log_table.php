<?php

use Illuminate\Database\Migrations\Migration;

class CreateExtensionslogTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('extensions_log')) {
			Schema::create('extensions_log', function($table) {
				$table->increments('extensions_id');
				$table->string('extensions_name', 255)->nullable();
				$table->bigInteger('pid')->nullable();
				$table->string('action', 255)->nullable();
				$table->longtext('description')->nullable();
				$table->timestamp('timestamp')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
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
        Schema::drop('extensions_log');
    }

}

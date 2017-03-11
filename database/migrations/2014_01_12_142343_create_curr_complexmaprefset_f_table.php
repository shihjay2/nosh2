<?php

use Illuminate\Database\Migrations\Migration;

class CreateCurrcomplexmaprefsetfTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('curr_complexmaprefset_f')) {
			Schema::create('curr_complexmaprefset_f', function($table) {
				$table->string('id', 36)->index();
				$table->char('effectivetime', 8)->index();
				$table->char('active', 1)->index();
				$table->string('moduleid', 18)->index();
				$table->string('refsetid', 18)->index();
				$table->string('referencedcomponentid', 18)->index();
				$table->smallInteger('mapGroup');
				$table->smallInteger('mapPriority');
				$table->string('mapRule', 18)->nullable();
				$table->string('mapAdvice', 18)->nullable();
				$table->string('mapTarget', 18)->nullable()->index();
				$table->string('correlationId', 18);
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
        Schema::drop('curr_complexmaprefset_f');
    }

}

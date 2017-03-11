<?php

use Illuminate\Database\Migrations\Migration;

class CreatePeTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('pe')) {
			Schema::create('pe', function($table) {
				$table->bigInteger('eid')->primary();
				$table->bigInteger('pid')->nullable();
				$table->string('encounter_provider', 255)->nullable();
				$table->timestamp('pe_date')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
				$table->longtext('pe_gen1')->nullable();
				$table->longtext('pe_eye1')->nullable();
				$table->longtext('pe_eye2')->nullable();
				$table->longtext('pe_eye3')->nullable();
				$table->longtext('pe_ent1')->nullable();
				$table->longtext('pe_ent2')->nullable();
				$table->longtext('pe_ent3')->nullable();
				$table->longtext('pe_ent4')->nullable();
				$table->longtext('pe_ent5')->nullable();
				$table->longtext('pe_ent6')->nullable();
				$table->longtext('pe_neck1')->nullable();
				$table->longtext('pe_neck2')->nullable();
				$table->longtext('pe_resp1')->nullable();
				$table->longtext('pe_resp2')->nullable();
				$table->longtext('pe_resp3')->nullable();
				$table->longtext('pe_resp4')->nullable();
				$table->longtext('pe_cv1')->nullable();
				$table->longtext('pe_cv2')->nullable();
				$table->longtext('pe_cv3')->nullable();
				$table->longtext('pe_cv4')->nullable();
				$table->longtext('pe_cv5')->nullable();
				$table->longtext('pe_cv6')->nullable();
				$table->longtext('pe_ch1')->nullable();
				$table->longtext('pe_ch2')->nullable();
				$table->longtext('pe_gi1')->nullable();
				$table->longtext('pe_gi2')->nullable();
				$table->longtext('pe_gi3')->nullable();
				$table->longtext('pe_gi4')->nullable();
				$table->longtext('pe_gu1')->nullable();
				$table->longtext('pe_gu2')->nullable();
				$table->longtext('pe_gu3')->nullable();
				$table->longtext('pe_gu4')->nullable();
				$table->longtext('pe_gu5')->nullable();
				$table->longtext('pe_gu6')->nullable();
				$table->longtext('pe_gu7')->nullable();
				$table->longtext('pe_gu8')->nullable();
				$table->longtext('pe_gu9')->nullable();
				$table->longtext('pe_lymph1')->nullable();
				$table->longtext('pe_lymph2')->nullable();
				$table->longtext('pe_lymph3')->nullable();
				$table->longtext('pe_ms1')->nullable();
				$table->longtext('pe_ms2')->nullable();
				$table->longtext('pe_ms3')->nullable();
				$table->longtext('pe_ms4')->nullable();
				$table->longtext('pe_ms5')->nullable();
				$table->longtext('pe_ms6')->nullable();
				$table->longtext('pe_ms7')->nullable();
				$table->longtext('pe_ms8')->nullable();
				$table->longtext('pe_ms9')->nullable();
				$table->longtext('pe_ms10')->nullable();
				$table->longtext('pe_ms11')->nullable();
				$table->longtext('pe_ms12')->nullable();
				$table->longtext('pe_skin1')->nullable();
				$table->longtext('pe_skin2')->nullable();
				$table->longtext('pe_neuro1')->nullable();
				$table->longtext('pe_neuro2')->nullable();
				$table->longtext('pe_neuro3')->nullable();
				$table->longtext('pe_psych1')->nullable();
				$table->longtext('pe_psych2')->nullable();
				$table->longtext('pe_psych3')->nullable();
				$table->longtext('pe_psych4')->nullable();
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
        Schema::drop('pe');
    }

}

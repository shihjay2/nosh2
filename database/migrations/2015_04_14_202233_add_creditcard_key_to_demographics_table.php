<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCreditcardKeyToDemographicsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('demographics', function(Blueprint $table)
		{
			$table->string('creditcard_key', 255)->nullable()->default('');
		});
		$query = DB::table('demographics')
			->where(function($query_array1) {
				$query_array1->where('creditcard_number', '!=', '')
				->orWhereNotNull('creditcard_number');
			})
			->get();
		if ($query) {
			foreach ($query as $row) {
				$key = MD5(microtime());
				Crypt::setKey($key);
				$new = Crypt::encrypt($row->creditcard_number);
				$data = array(
					'creditcard_key' => $key,
					'creditcard_number' => $new
				);
				DB::table('demographics')->where('pid', '=', $row->pid)->update($data);
			}
		}
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('demographics', function(Blueprint $table)
		{
			$table->dropColumn('creditcard_key');
		});
	}

}

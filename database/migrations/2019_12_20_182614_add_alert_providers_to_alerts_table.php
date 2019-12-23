<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAlertProvidersToAlertsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->string('alert_providers', 255)->nullable();
        });
        $alerts = DB::table('alerts')->get();
        if ($alerts->count()) {
            foreach ($alerts as $alert) {
                if (!empty($alert->alert_provider)) {
                    $alert_data['alert_providers'] = $alert->alert_provider;
                    DB::table('alerts')->where('alert_id', '=', $alert->alert_id)->update($alert_data);
                }
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
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn('alert_providers');
        });
    }
}

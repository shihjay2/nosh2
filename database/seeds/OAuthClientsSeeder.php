<?php
use Illuminate\Database\Seeder;

class OAuthClientsSeeder extends Seeder {

    public function run() {
        DB::table('oauth_clients')->insert(array(
            'client_id' => "testclient",
            'client_secret' => "testpass",
            'redirect_uri' => "http://fake/",
        ));
    }
}

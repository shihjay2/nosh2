<?php

namespace App\Http\Controllers;

use App;
use App\Http\Requests;
use DB;
use Illuminate\Http\Request;
use Response;
use URL;

class MedicationController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index(Request $request)
	{
		//
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		//
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		//
	}


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		$rxnormapi = new RxNormApi();
		$rxnormapi->output_type = 'json';
		$rxnorm = json_decode($rxnormapi->findRxcuiById("NDC", $id), true);
		if (isset($rxnorm['idGroup']['rxnormId'][0])) {
			$rxnorm1 = json_decode($rxnormapi->getRxConceptProperties($rxnorm['idGroup']['rxnormId'][0]), true);
			$med_rxnorm_code = $rxnorm['idGroup']['rxnormId'][0];
			$med_name = $rxnorm1['properties']['name'];
			$statusCode = 200;
			$response['resourceType'] = 'Medication';
			$response['id'] = $id;
			$response['text']['status'] = 'generated';
			$response['text']['div'] = '<div>' .  $rxnorm1['properties']['name'] . '</div>';
			$response['code']['text'] = $rxnorm1['properties']['name'];
		} else {
			$response = [
				'error' => "Medication doesn't exist."
			];
			$statusCode = 404;
		}
		return response()->json($response, $statusCode)->header('Content-Type', 'application/fhir+json');
	}


	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		//
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		//
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		//
	}


}

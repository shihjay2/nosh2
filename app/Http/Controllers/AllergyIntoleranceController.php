<?php

namespace App\Http\Controllers;

use App;
use App\Http\Requests;
use DB;
use Illuminate\Http\Request;
use Response;
use URL;

class AllergyIntoleranceController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index(Request $request)
	{
		$data = $request->all();
		if ($data) {
			$resource = 'AllergyIntolerance';
			$table = 'allergies';
			$table_primary_key = 'allergies_id';
			$table_key = [
				'identifier' => 'allergies_id',
				'patient' => 'pid',
				'substance' => 'allergies_med'
			];
			$result = $this->resource_translation($data, $table, $table_primary_key, $table_key);
			if ($result['response'] == true) {
				$statusCode = 200;
				$response['resourceType'] = 'Bundle';
				$response['type'] = 'searchset';
				$response['id'] = 'urn:uuid:' . $this->gen_uuid();
				$response['total'] = $result['total'];
				foreach ($result['data'] as $row_id) {
					$row = DB::table($table)->where($table_primary_key, '=', $row_id)->first();
					$resource_content = $this->resource_detail($row, $resource);
					$response['entry'][] = [
						'fullUrl' => Request::url() . '/' . $row_id,
						'resource' => $resource_content
					];
				}
			} else {
				$response = [
					'error' => "Query returned 0 records.",
				];
				$statusCode = 404;
			}
		} else {
			$practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
			if ($practice->patient_centric == 'y') {
				// Patient Centric only
				$data1['patient'] = '1';
				$resource = 'AllergyIntolerance';
				$table = 'allergies';
				$table_primary_key = 'allergies_id';
				$table_key = [
					'identifier' => 'allergies_id',
					'patient' => 'pid',
					'substance' => 'allergies_med'
				];
				$result = $this->resource_translation($data1, $table, $table_primary_key, $table_key);
				if ($result['response'] == true) {
					$statusCode = 200;
					$response['resourceType'] = 'Bundle';
					$response['type'] = 'searchset';
					$response['id'] = 'urn:uuid:' . $this->gen_uuid();
					$response['total'] = $result['total'];
					foreach ($result['data'] as $row_id) {
						$row = DB::table($table)->where($table_primary_key, '=', $row_id)->first();
						$resource_content = $this->resource_detail($row, $resource);
						$response['entry'][] = [
							'fullUrl' => $request->url() . '/' . $row_id,
							'resource' => $resource_content
						];
					}
				} else {
					$response = [
						'error' => "Query returned 0 records.",
					];
					$statusCode = 404;
				}
			} else {
				$response = [
					'error' => "Invalid query."
				];
				$statusCode = 404;
			}
		}
		return response()->json($response, $statusCode)->header('Content-Type', 'application/fhir+json');
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
	public function store(Request $request)
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
		//
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
	public function update(Request $request, $id)
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

<?php

namespace App\Http\Controllers;

use App;
use App\Http\Requests;
use DB;
use Illuminate\Http\Request;
use Response;
use URL;

class PatientController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index(Request $request)
	{
		$data = $request->all();
		if ($data) {
			$resource = 'Patient';
			$table = 'demographics';
			$table_primary_key = 'pid';
			$table_key = [
				'name' => ['lastname','firstname'],
				'identifier' => 'pid',
				'_id' => 'pid',
				'telcom' => ['phone_home','phone_work','phone_cell'],
				'gender' => 'sex',
				'birthDate' => 'dob',
				'address' => ['address','city','state','zip'],
				'contact.relationship' => 'guardian_relationship',
				'contact.name' => ['guardian_lastname','guardian_firstname'],
				'contact.telcom' => 'guardian_phone_home',
				'active' => 'active'
			];
			$result = $this->resource_translation($data, $table, $table_primary_key, $table_key);
			$queries = DB::getQueryLog();
			$sql = end($queries);
			if (!empty($sql['bindings'])) {
				$pdo = DB::getPdo();
				foreach($sql['bindings'] as $binding) {
					$sql['query'] = preg_replace('/\?/', $pdo->quote($binding), $sql['query'], 1);
				}
			}
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
		$resource = 'Patient';
		$table = 'demographics';
		$table_primary_key = 'pid';
		$row = DB::table($table)->where($table_primary_key, '=', $id)->first();
		if ($row) {
			$statusCode = 200;
			$response = $this->resource_detail($row, $resource);
		} else {
			$response = [
				'error' => $resource . " doesn't exist."
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

<?php

namespace App\Http\Controllers;

use App\Models\DataTypes\Collision;
use App\Models\DataTypes\Humidity;
use App\Models\DataTypes\Rotation;
use App\Models\DataTypes\Temperature;
use App\Models\Day;
use App\Models\Tower;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Str;

class DataCollectionController extends Controller
{
    public array $allowed_data_types = ['temperature','collision','rotation','humidity'];

	public function ingest(int $id, Request $request) {
		// 1. Decide which device this belongs to
		$now = now();
		$tower = Tower::find($id);
		$user_id = $tower->user_id;
		\Log::log('info', "Tower $tower->name sent HTTP request");

		// 2. Get data from request - Example: "{"topic":"node/push-button:0/orientation","payload":1}"
		if($request->getContent() == null) {
			return response()->json([ 'error' => 'There was no content found in the HTTP request.' ]);
		}
		$response = $request->getContent();

		// 3. Parse
		$response_json = json_decode($response, true);
		$data_type = Str::afterLast($response_json['topic'], '/');
		$value = $response_json['payload'];
		\Log::log('info', "Tower $tower->name Topic: $data_type, Value: $value");


		// 4. Validate
		if(in_array($data_type, $this->allowed_data_types) && isset($value)) {
			\Log::log('info', "Data type is valid and value is set.");

			$today = now()->format('Y-m-d');
			$current_day_table_entry = Day::where('data_type', '=', $data_type)->first() ?? null;

			if(!Day::exists() || $current_day_table_entry == null || $current_day_table_entry->date != $today) {
				\Log::log('info', "Day Entry doesn't exist, creating $data_type's entry for today.");

				Day::create([
					'tower_id' => $tower->id,
					'date' => $today,
					'data_type' => $data_type,
				]);
			}
			// Create the entry
			else {
				$day = Day::where('data_type', '=', $data_type)->first() ?? null;

				switch ($data_type) {
					case 'temperature':
						Temperature::create([
							'day_id' => $day->id,
							'temperature' => $value,
						]);
						break;
					case 'collision':
						Collision::create([
							'day_id' => $day->id,
							'collision' => $value,
						]);
						break;
					case 'rotation':
						// TODO: parse the three rotation values into variables to be added into the model.

						Rotation::create([
							'day_id' => $day->id,
							'rotation_x' => $value,
							'rotation_y' => $value,
							'rotation_z' => $value,
						]);
						break;
					case 'humidity':
						Humidity::create([
							'day_id' => $day->id,
							'humidity' => $value,
						]);
						break;
					default:
						return response()->json([ 'error' => 'Something went wrong when trying to add data to the database.' ]);
				}
			}

//			Carbon::today();
 		}
		else return response()->json([ 'error' => 'Invalid request.' ]);

		\Log::log('info', "Successfully ingested new entry into the database.");

		return response()->json([
			'message' => "Request type $data_type for tower $tower->name (id $tower->id) value $value stored successfully.",
			'data' => [
				'tower_id' => $tower->id,
				'tower_name' => $tower->name,
				'data_type' => $data_type,
				'value' => $value,
				'tower_owner' => User::whereId($user_id)->first()->name,
			]
		]);

	}
}
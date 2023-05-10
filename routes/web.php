<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use App\Contact;
use GuzzleHttp\Exception\RequestException;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/', function(Request $request) {
    $contacts = Contact::get();
    return view('welcome',compact('contacts'));
});


Route::post('upload', function(Request $request) {
    $request->validate([
        'file' => 'required|mimes:csv,txt|max:2048'
    ]); 
    $file = $request->file('file');
    $csvData = file_get_contents($file);
    $rows = array_map('str_getcsv', explode("\n", $csvData));
    $header = array_shift($rows);
    Contact::truncate();
    foreach ($rows as $row) {
        // Make sure that the number of columns matches the number of headers
        if (count($header) == count($row)) {
            $data = array_combine($header, $row); 
            // Save the data to the database
            Contact::create($data);
        }
    }
    return back()->with('success', 'File uploaded successfully.');
})->name('upload');

Route::get('send-messsage', function(Request $request) {
    // TODO: validate incoming params first!
    $phoneNumbers = Contact::pluck('phones')->toArray(); 
    foreach ($phoneNumbers as $phoneNumber) { 
        $response = Http::withBasicAuth('70220a1b','Afq5UuiTrdcG3aHR')
        ->post('https://messages-sandbox.nexmo.com/v1/messages', [
            'from' => '14157386102',
            'to' => $phoneNumber,
            'message_type' => 'text',
            'text' => 'This is a WhatsApp Message sent from the Messages API',
            'channel' => 'whatsapp'
        ]);
    } 
    return back()->with('success', 'Message Send.');
 
});

Route::post('/webhooks/status', function(Request $request) {
    $data = $request->all();
    Log::Info($data);
});

Route::post('/webhooks/inbound', function(Request $request) {
    $data = $request->all();

    $text = $data['message']['content']['text'];
    $number = intval($text);
    Log::Info($number);
    if($number > 0) {
        $random = rand(1, 8);
        Log::Info($random);
        $respond_number = $number * $random;
        Log::Info($respond_number);
        $url = "https://messages-sandbox.nexmo.com/v0.1/messages";
        $params = ["to" => ["type" => "whatsapp", "number" => $data['from']['number']],
            "from" => ["type" => "whatsapp", "number" => "14157386170"],
            "message" => [
                "content" => [
                    "type" => "text",
                    "text" => "The answer is " . $respond_number . ", we multiplied by " . $random . "."
                ]
            ]
        ];
        $headers = ["Authorization" => "Basic " . base64_encode(env('NEXMO_API_KEY') . ":" . env('NEXMO_API_SECRET'))];

        $client = new \GuzzleHttp\Client();
        $response = $client->request('POST', $url, ["headers" => $headers, "json" => $params]);
        $data = $response->getBody();
    }
    Log::Info($data);
});

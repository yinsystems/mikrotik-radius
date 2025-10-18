<?php

namespace App\Http\Controllers;

use App\Http\Ussd\Actions\WifiWelcomeAction;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sparors\Ussd\Facades\Ussd;
use Exception;

class WifiUssdController extends Controller
{
    /**
     * Handle the incoming USSD request for WiFi portal
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        try {
            Log::info('WiFi USSD Request:', $request->all());

            $session = uniqid();
            $user_input = $request->get('USERDATA');
            $msisdn = $request->get('MSISDN');

            // Check if this is initial USSD code dial (*123#)
            if ($user_input === "*920*199") { // Your WiFi USSD code without #
                Cache::put($msisdn, $session, 600); // 10 minutes cache
            }

            // Get or create customer
            $customer = Customer::firstOrCreate(
                ['phone' => $msisdn],
                [

                    'username'=> $msisdn, // Customer's chosen RADIUS username (defaults to phone)
                    'password'=> Str::random(12), // Customer's chosen RADIUS password
                    'name' => 'USSD-' . substr($msisdn, -4),
                    'phone' => $msisdn,
                    'status' => 'active',
                    'registration_date' => now()
                ]
            );

            $ussd = Ussd::machine()
                ->setSessionId(Cache::get($msisdn))
                ->setFromRequest([
                    'phone_number' => 'MSISDN',
                    'network' => 'NETWORK',
                ])
                ->setInput(
                    strpos($request->get('USERDATA'), '*') !== false ?
                        substr($request->get('USERDATA'), strrpos($request->get('USERDATA'), '*') + 1) :
                        $request->get('USERDATA')
                )
                ->setInitialState(WifiWelcomeAction::class)
                ->setResponse(function (string $message, string $action) {
                    return match ($action) {
                        "input" =>  $message,
                        default => "END" . $message,
                    };
//                    return [
//                        "USERID" => request('USERID'),
//                        "MSISDN" => request('MSISDN'),
//                        "USERDATA" => request('USERDATA'),
//                        "MSG" => $message,
//                        "MSGTYPE" => $action === "input",
//                    ];
                });

            return $ussd->run();

        } catch (Exception $e) {
            Log::error('WiFi USSD Error: ' . $e->getMessage());
            Log::error('WiFi USSD Stack Trace: ' . $e->getTraceAsString());

            return [
                "USERID" => request('USERID'),
                "MSISDN" => request('MSISDN'),
                "USERDATA" => request('USERDATA'),
                "MSG" => "Service temporarily unavailable. Please try again later.",
                "MSGTYPE" => false, // END
            ];
        }
    }
}

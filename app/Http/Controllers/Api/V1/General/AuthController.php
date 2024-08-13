<?php

namespace App\Http\Controllers\Api\V1\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Pusher\Pusher;

class AuthController extends Controller
{

    public function authenticate(Request $request)
    {
        // Get the raw data from the request body
        $rawData = $request->getContent();

        // Assuming the raw data is JSON, you can decode it
        parse_str($rawData, $data);
        $request->merge($data);
        $request->validate([
            "channel_name" => "required|string",
            "socket_id" => "required|string",
        ]);

        $user = auth()->user();

        if ($user) {
            $pusher = new Pusher(
                config('broadcasting.connections.pusher.key'),
                config('broadcasting.connections.pusher.secret'),
                config('broadcasting.connections.pusher.app_id'),
                [
                    'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                    'useTLS' => true
                ]
            );

            $channelName = $data['channel_name'];
            $socketId = $data['socket_id'];

            $auth = $pusher->socket_auth($channelName, $socketId);
            return response($auth);
        }

        return response('Unauthorized', 401);
    }
}

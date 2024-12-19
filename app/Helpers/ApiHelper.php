<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use App\Constants\General\ApiConstants;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Facades\Image as Image;


class ApiHelper
{
    static function problemResponse(string $message = null, int $status_code, Request $request = null, Exception $trace = null)
    {
        $code = !empty($status_code) ? $status_code : null;
        $error_code = null;

        if (!empty($trace)) {
            $trace_msg = $trace?->getMessage();
            $error_code = $trace?->getCode();
        }

        $body = [
            "message" => app()->environment(['local', 'staging']) ? $trace?->getMessage() : $message,
            "code" => $code,
            "success" => false,
            "error_code" => $error_code,
            "error_debug" => app()->environment(['local', 'staging']) ? $trace?->getTrace() : $trace_msg ?? null

        ];

        !empty($trace) ? logger($trace?->getMessage(), $trace?->getTrace()) : null;
        return response()->json($body)->setStatusCode($code);
    }


    /** Return error api response */
    static function inputErrorResponse(string $message = null, int $status_code = null, Request $request = null, ValidationException $trace = null)
    {
        $code = ($status_code != null) ? $status_code : "";
        $error_code = null;

        if (!empty($trace)) {
            $errors = $trace->errors();
            $error_code = $trace->getCode();
        }

        $body = [
            "message" => $message,
            "code" => $code,
            "success" => false,
            "error_code" => $error_code,
            "errors" => $errors ?? null,
        ];

        return response()->json($body)->setStatusCode($code);
    }

    /** Return valid api response */
    static function validResponse(string $message = null, $data = null, $request = null)
    {
        // if (is_null($data) || blank($data)) {
        //     $data = null;
        // }
        $body = [
            "message" => $message,
            "data" => $data,
            "success" => true,
            "code" => ApiConstants::GOOD_REQ_CODE,

        ];
        return response()->json($body);
    }

    static function validData(string $message = null, $data = null, $terminus = null)
    {
        if (is_null($data) || empty($data)) {
            $data = [];
        }

        $body = [
            'terminus' => $terminus,
            'status' => "OK",
            'response' => [
                'code' => ApiConstants::GOOD_REQ_CODE,
                'title' => "Operation successful",
                'message' => $message,
                'data' => $data,
            ]
        ];

        return $body;
    }

    static function problemData(string $message = null, int $status_code, Exception $trace = null, $terminus = null)
    {
        $code = !empty($status_code) ? $status_code : null;
        $traceMsg = empty($trace) ?  null  : $trace->getMessage();

        $body = [
            'terminus' => $terminus,
            'status' => "F9",
            'response' => [
                'title' => "Operation failed",
                'message' => $message,
                'code' => $code,
                "error_debug" => $traceMsg,
                "error_trace" => optional($trace)->getTrace()
            ]
        ];

        return $body;
    }

    static function inputErrorData(string $message = null, int $status_code = null, ValidationException $trace = null, $terminus = null)
    {
        $code = ($status_code != null) ? $status_code : '';

        $body = [
            'terminus' => $terminus,
            'status' => "F9",
            'response' => [
                'title' => "Operation failed",
                'message' => $message,
                'code' => $code,
                'errors' => empty($trace) ?  null  : $trace?->errors(),
            ]
        ];

        return $body;
    }

    /**Returns the available auth instance with user
     * @param bool $getUser
     */
    static function auth($getUser = false)
    {
        return $getUser ? auth("api")->user() : auth("api");
    }

}

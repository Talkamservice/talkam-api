<?php

use App\Constants\General\AppConstants;
use App\Helpers\MethodsHelper;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

function pillClasses($value)
{
    return AppConstants::PILL_CLASSES[$value] ?? "primary";
}

function convertJsonStringToText($string)
{
    return MethodsHelper::convertJsonStringToText($string);
}
function str_limit($string, $limit = 20, $end  = '...')
{
    return MethodsHelper::str_limit(strip_tags($string), $limit, $end);
}

function slugify($value)
{
    return Str::slug($value);
}

function sudo()
{
    return User::where("email", env("SUDO_EMAIL", "sudo@yourmentra.com"))->first();
}


function encrypt_decrypt($action, $string)
{
    try {
        $output = false;

        $encrypt_method = "AES-256-CBC";
        $secret_key = 'Hg99JHShjdfhjhejkse@14447DP';
        $secret_iv = 'T0EHVn0dUIK888JSBGDD';

        // hash
        $key = hash('sha256', $secret_key);

        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hash('sha256', $secret_iv), 0, 16);

        if ($action == 'encrypt') {
            $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
            $output = base64_encode($output);
        } elseif ($action == 'decrypt') {
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }

        return $output;
    } catch (\Throwable $e) {
        return false;
    }
}

function formatNumber($value)
{
    return MethodsHelper::int_format($value);
}

function formatDate($value)
{
    if (is_null($value) || empty($value)) {
        return $value;
    }

    if (!auth()->check()) {
        return Carbon::parse($value)->format("Y-m-d H:i:s");
    }

    return MethodsHelper::formatDateWithTimezone($value, auth()->user());
}

function groupAges($years, $currentYear, $intervals)
{
    $groupedAges = [];

    foreach ($intervals as $i => $start) {
        $end = isset($intervals[$i + 1]) ? $intervals[$i + 1] - 1 : $start + 9;
        $range = "$start-$end";
        $groupedAges[$range] = 0;
    }

    foreach ($years as $year) {
        if (!is_numeric($year) || strlen($year) != 4) {
            continue;
        }

        $age = $currentYear - (int)$year;

        foreach ($groupedAges as $range => &$count) {
            list($start, $end) = explode('-', $range);
            if ($age >= $start && $age <= $end) {
                $count++;
                break;
            }
        }
        unset($count);  // Unset the reference to avoid any side effects
    }

    // Remove age ranges with zero count
    $groupedAges = array_filter($groupedAges);

    return $groupedAges;
}

function divideNumber($numerator, $denominator, $format = false)
{
    $number = ($denominator == 0) ? 0 : $numerator / $denominator;

    if ($format) {
        $number = formatNumber($number);
    }

    return $number;
}

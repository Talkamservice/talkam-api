<?php

use App\Constants\General\AppConstants;
use App\Helpers\MethodsHelper;
use App\Models\User;
use App\Services\User\BlockUserService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

function pillClasses($value)
{
    return AppConstants::PILL_CLASSES[$value] ?? "primary";
}

function convertJsonStringToText($string)
{
    return MethodsHelper::convertJsonStringToText($string);
}
function str_limit($string, $limit = 20, $end = '...')
{
    return MethodsHelper::str_limit(strip_tags($string), $limit, $end);
}

function slugify($value)
{
    return Str::slug($value);
}

function sudo()
{
    return User::where("email", env("SUDO_EMAIL", "sudo@talkam.com"))->first();
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

function formatDateOfBirth($value)
{
    // Check if the value is null or empty, return as-is
    if (is_null($value) || empty($value)) {
        return $value;
    }
    // Format the date if it's not null
    return Carbon::parse($value)->format("Y-m-d");
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

        $age = $currentYear - (int) $year;

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

function collectPagination(LengthAwarePaginator $pagination, $appendQuery = true)
{
    $request = request();
    unset($request["token"]);
    if ($appendQuery) {
        $pagination->appends($request->query());
    }
    $all_pg_data = $pagination->toArray();
    unset($all_pg_data["links"]); // remove links
    unset($all_pg_data["data"]); // remove old data mapping

    $buildResponse["pagination_meta"] = $all_pg_data;
    $buildResponse["pagination_meta"]["can_load_more"] = $all_pg_data["to"] < $all_pg_data["total"];
    // $buildResponse["pagination_meta"]["query"] = $request->query();
    $buildResponse["data"] = $pagination->getCollection();
    return $buildResponse;
}

function ensureUniqueKeys(array $array)
{
    $uniqueArray = [];

    foreach ($array as $key => $value) {
        // If the key doesn't exist in the uniqueArray, add it
        if (!array_key_exists($key, $uniqueArray)) {
            $uniqueArray[$key] = $value;
        }
    }

    return $uniqueArray;
}

function carbon()
{
    return new Carbon;
}

function isBlocked($blocker, $blocked_user)
{
    return (new BlockUserService)->isBlocked($blocker, $blocked_user);
}

// Function to filter array and avoid duplicate words
function filterUniqueWords($array)
{
    $unique_words = [];
    $filtered_array = [];

    foreach ($array as $string) {
        $words_in_string = explode(' ', strtolower($string)); // Split string into words, convert to lowercase
        $filtered_words = [];

        foreach ($words_in_string as $word) {
            if (!in_array($word, $unique_words)) {
                $filtered_words[] = $word; // Keep the word if not seen before
                $unique_words[] = $word;   // Add it to the list of seen words
            }
        }

        // Join filtered words back into a string
        $filtered_string = implode(' ', $filtered_words);

        // Add the filtered string to the result array if it's not empty
        if (!empty($filtered_string)) {
            $filtered_array[] = $filtered_string;
        }
    }

    return $filtered_array;
}

function slugPermission(string $string)
{
    return "can_" . str_replace("-", "_", slugify($string));
}

function findSpecialWords($string)
{
    // Regular expression to match words starting and ending with $
    $pattern = '/\$@(\w+)\$/';

    // Find all matches
    preg_match_all($pattern, $string, $matches);

    // Return the matched words
    return $matches[1];
}

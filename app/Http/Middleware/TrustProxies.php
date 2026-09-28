<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Was never set (null), so Laravel trusted only the raw Host header PHP
     * received and ignored X-Forwarded-Host/-Proto from whatever reverse
     * proxy sits in front — the direct cause of route()/url() minting URLs
     * against the app server's own internal bind address instead of the
     * public domain (e.g. image URLs resolving to 127.0.0.1:PORT). '*' is
     * correct here since the app is only ever reached through its own
     * reverse proxy (Nginx), never called directly by the public internet.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}

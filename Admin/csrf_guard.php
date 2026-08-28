<?php
// Browser-based CSRF protection for state-changing Admin POST requests.
// Accept only requests originating from this application.
function require_same_origin_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';

    if ($host === '') {
        http_response_code(403);
        exit('Forbidden');
    }

    $valid = false;
    if ($origin !== '') {
        $originHost = parse_url($origin, PHP_URL_HOST);
        $originPort = parse_url($origin, PHP_URL_PORT);
        $expectedHost = $host;
        if ($originHost !== null) {
            $originHostWithPort = $originHost . ($originPort ? ':' . $originPort : '');
            $valid = hash_equals($expectedHost, $originHostWithPort);
        }
    } elseif ($referer !== '') {
        $refererHost = parse_url($referer, PHP_URL_HOST);
        $refererPort = parse_url($referer, PHP_URL_PORT);
        if ($refererHost !== null) {
            $refererHostWithPort = $refererHost . ($refererPort ? ':' . $refererPort : '');
            $valid = hash_equals($host, $refererHostWithPort);
        }
    }

    if (!$valid) {
        http_response_code(403);
        exit('Forbidden');
    }
}

require_same_origin_post();

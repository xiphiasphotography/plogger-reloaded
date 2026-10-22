<?php

declare(strict_types=1);

// Definitions only; no configuration, sessions or database bootstrapping.
require_once dirname(__DIR__) . '/plog-includes/plog-functions.php';

/** Read fixture responses without PHP's deprecated magic response-header variable. */
function plogger_test_http_response(string $url, $context): array
{
    $stream = fopen($url, 'rb', false, $context);
    if ($stream === false) {
        throw new RuntimeException('Cannot open fixture response: ' . $url);
    }
    try {
        $headers = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        $body = stream_get_contents($stream);
        if ($body === false) {
            throw new RuntimeException('Cannot read fixture response: ' . $url);
        }
        return [$body, $headers];
    } finally {
        fclose($stream);
    }
}

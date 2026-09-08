<?php

declare(strict_types=1);

$baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost:8000', '/');

function request(string $method, string $url, ?array $body = null): array
{
    $context = [
        'http' => [
            'method' => $method,
            'header' => "Content-Type: application/json\r\n",
            'ignore_errors' => true,
        ],
    ];

    if ($body !== null) {
        $context['http']['content'] = json_encode($body);
    }

    $response = file_get_contents($url, false, stream_context_create($context));
    $statusLine = $http_response_header[0] ?? 'HTTP/1.1 000 Unknown';
    preg_match('/\s(\d{3})\s/', $statusLine, $matches);

    return [
        'status' => (int) ($matches[1] ?? 0),
        'body' => json_decode($response ?: '[]', true),
    ];
}

$suffix = time();

$candidate = request('POST', "{$baseUrl}/candidates", [
    'name' => "Smoke Candidate {$suffix}",
    'party' => 'QA',
]);

$voter = request('POST', "{$baseUrl}/voters", [
    'name' => "Smoke Voter {$suffix}",
    'email' => "smoke{$suffix}@example.com",
]);

$vote = request('POST', "{$baseUrl}/votes", [
    'voter_id' => $voter['body']['data']['id'] ?? 0,
    'candidate_id' => $candidate['body']['data']['id'] ?? 0,
]);

$duplicateVote = request('POST', "{$baseUrl}/votes", [
    'voter_id' => $voter['body']['data']['id'] ?? 0,
    'candidate_id' => $candidate['body']['data']['id'] ?? 0,
]);

$statistics = request('GET', "{$baseUrl}/votes/statistics");

$checks = [
    'candidate created' => $candidate['status'] === 201,
    'voter created' => $voter['status'] === 201,
    'vote created' => $vote['status'] === 201,
    'duplicate vote rejected' => $duplicateVote['status'] === 409,
    'statistics returned' => $statistics['status'] === 200,
];

foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
}

if (in_array(false, $checks, true)) {
    exit(1);
}

<?php

declare(strict_types=1);

use VotingSystem\Config\Env;
use VotingSystem\Controllers\CandidateController;
use VotingSystem\Controllers\VoteController;
use VotingSystem\Controllers\VoterController;
use VotingSystem\Core\HttpException;
use VotingSystem\Core\Request;
use VotingSystem\Core\Response;
use VotingSystem\Core\Router;

require dirname(__DIR__) . '/vendor/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

$router = new Router();

$router->add('GET', '/', static fn () => Response::json([
    'success' => true,
    'message' => 'Voting System API',
    'documentation' => '/docs/openapi.yaml',
]));

$router->add('GET', '/docs/openapi.yaml', static function (): void {
    header('Content-Type: application/yaml; charset=utf-8');
    readfile(dirname(__DIR__) . '/docs/openapi.yaml');
});

$router->add('POST', '/voters', static fn (Request $request) => (new VoterController())->store($request));
$router->add('GET', '/voters', static fn (Request $request) => (new VoterController())->index($request));
$router->add('GET', '/voters/{id}', static fn (Request $request, array $params) => (new VoterController())->show($request, $params));
$router->add('DELETE', '/voters/{id}', static fn (Request $request, array $params) => (new VoterController())->destroy($request, $params));

$router->add('POST', '/candidates', static fn (Request $request) => (new CandidateController())->store($request));
$router->add('GET', '/candidates', static fn (Request $request) => (new CandidateController())->index($request));
$router->add('GET', '/candidates/{id}', static fn (Request $request, array $params) => (new CandidateController())->show($request, $params));
$router->add('DELETE', '/candidates/{id}', static fn (Request $request, array $params) => (new CandidateController())->destroy($request, $params));

$router->add('POST', '/votes', static fn (Request $request) => (new VoteController())->store($request));
$router->add('GET', '/votes', static fn () => (new VoteController())->index());
$router->add('GET', '/votes/statistics', static fn () => (new VoteController())->statistics());

try {
    $router->dispatch(Request::capture());
} catch (HttpException $exception) {
    Response::error($exception->getMessage(), $exception->statusCode(), $exception->errors());
} catch (Throwable $exception) {
    $debug = Env::bool('APP_DEBUG', false);

    Response::error(
        'Internal server error.',
        500,
        $debug ? ['exception' => $exception->getMessage()] : []
    );
}

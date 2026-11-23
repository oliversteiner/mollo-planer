<?php

namespace App\Core\Identity\Http\Controllers;

use App\Core\Identity\OAuthClients;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\Bridge\Client;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\User;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;
use Laravel\Passport\Passport;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * JSON side of the consent page of the frontend: what the application asks for, and the answer of the person.
 * The decision is made by the controllers of Passport; here the redirect they would send becomes a JSON field,
 * because the page calls with fetch and then navigates itself (also to the URL scheme of an app).
 */
class ConsentController
{
    public function show(Request $request, ClientRepository $clients): JsonResponse
    {
        $authRequest = $this->pending($request);
        abort_if($authRequest === null, Response::HTTP_NOT_FOUND);

        $client = $clients->find($authRequest->getClient()->getIdentifier());
        abort_if($client === null, Response::HTTP_NOT_FOUND);

        $scopes = collect($authRequest->getScopes())
            ->map(fn ($scope): string => $scope->getIdentifier())
            ->unique()
            ->values();

        return response()->json([
            'client' => [
                'name' => $client->name,
                'selfRegistered' => OAuthClients::isSelfRegistered($client),
                'redirectHost' => parse_url((string) $authRequest->getRedirectUri(), PHP_URL_HOST) ?: parse_url((string) $authRequest->getRedirectUri(), PHP_URL_SCHEME),
            ],
            'scopes' => $scopes->map(fn (string $id): array => ['id' => $id, 'description' => __(Passport::$scopes[$id] ?? $id)])->all(),
            'authToken' => $request->session()->get('authToken'),
            'user' => ['name' => $request->user()->name],
        ]);
    }

    public function approve(): JsonResponse
    {
        return $this->answer(fn () => app()->call([app(ApproveAuthorizationController::class), 'approve']));
    }

    public function deny(): JsonResponse
    {
        return $this->answer(fn () => app()->call([app(DenyAuthorizationController::class), 'deny']));
    }

    /** The authorization request that Passport keeps in the session until the person has answered. */
    private function pending(Request $request): ?AuthorizationRequestInterface
    {
        $serialized = $request->session()->get('authRequest');

        if (! is_string($serialized)) {
            return null;
        }

        return unserialize($serialized, ['allowed_classes' => [
            AuthorizationRequest::class,
            Client::class,
            Scope::class,
            User::class,
        ]]) ?: null;
    }

    /** @param  \Closure(): Response  $decision */
    private function answer(\Closure $decision): JsonResponse
    {
        try {
            $response = $decision();
        } catch (HttpResponseException $exception) {
            // Passport sends an OAuth error (for example access_denied) as an exception that carries the response.
            $response = $exception->getResponse();
        }

        if ($response->isRedirection()) {
            return response()->json(['redirect' => $response->headers->get('Location')]);
        }

        return response()->json(['error' => 'oauth_error', 'message' => $response->getContent()], $response->getStatusCode());
    }
}

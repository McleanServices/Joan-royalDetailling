<?php

namespace App\Services\SumUp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use SumUp\Exception\ConnectionException as SumUpConnectionException;
use SumUp\HttpClient\HttpClientInterface;
use SumUp\HttpClient\RequestOptions;
use SumUp\HttpClient\Response;

class LaravelHttpClient implements HttpClientInterface
{
    public function __construct(private readonly string $baseUrl = 'https://api.sumup.com') {}

    /**
     * @param  array<int|string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    public function send(string $method, string $url, array $body, array $headers, ?RequestOptions $options = null): Response
    {
        $endpoint = str_starts_with($url, 'http')
            ? $url
            : rtrim($this->baseUrl, '/').'/'.ltrim($url, '/');

        try {
            $timeout = 30;
            $connectTimeout = 10;

            if ($options instanceof RequestOptions) {
                $timeout = $options->timeout ?? $timeout;
                $connectTimeout = $options->connectTimeout ?? $connectTimeout;
            }

            $pending = Http::withHeaders($headers)
                ->timeout($timeout)
                ->connectTimeout($connectTimeout);

            $caBundle = $this->caBundle();

            if ($caBundle !== null) {
                $pending = $pending->withOptions(['verify' => $caBundle]);
            }

            $response = $body === []
                ? $pending->send($method, $endpoint)
                : $pending->send($method, $endpoint, ['json' => $body]);
        } catch (ConnectionException $exception) {
            throw new SumUpConnectionException($exception->getMessage(), (int) $exception->getCode());
        }

        /** @var array<string, array<int, string>> $responseHeaders */
        $responseHeaders = $response->headers();

        return new Response(
            $response->status(),
            $response->json() ?? $response->body(),
            $responseHeaders,
            $response->body(),
        );
    }

    /**
     * Windows PHP often has no CA file, so the call to SumUp fails before it leaves the machine.
     * Use the bundled bundle only in that case.
     */
    private function caBundle(): ?string
    {
        $configured = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return null;
        }

        $bundled = base_path('certs/cacert.pem');

        return is_file($bundled) ? $bundled : null;
    }
}

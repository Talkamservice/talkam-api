<?php

namespace App\Services\General\Guzzle;

use App\Exceptions\General\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Throwable;

class GuzzleService
{
    public array $headers;

    public array $auth = [];

    public Client $client;

    public function __construct(array $headers = [])
    {
        $this->headers = array_merge(
            [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            $headers
        );
        $this->client = new Client(['verify' => false]);
    }

    public function setauth(array $data)
    {
        $this->auth = $data;

        return $this;
    }

    public function post(string $url, array $data = [])
    {
       
        try {
            $response = $this->client->post(
                $url,
                [
                    'headers' => $this->headers,
                    'auth' => $this->auth,
                    'json' => $data,
                ],
            );
            return $this->success($response);
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    public function postWithFormParams(string $url, array $data = [])
    {
        try {
            $response = $this->client->post(
                $url,
                [
                    'headers' => $this->headers,
                    'auth' => $this->auth,
                    'form_params' => $data,
                ]
            );

            return $this->success($response);
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    public function put(string $url, array $data = [])
    {
        try {
            $response = $this->client->put(
                $url,
                [
                    'headers' => $this->headers,
                    'json' => $data,
                ]
            );

            return $this->success($response);
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    public function get(string $url, array $data = [])
    {
        try {
            $response = $this->client->get(
                $url,
                [
                    'headers' => $this->headers,
                    'json' => $data,
                ]
            );

            return $this->success($response);
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    public function getWithQuery(string $url, array $data = [])
    {
        try {
            $response = $this->client->get(
                $url,
                [
                    'headers' => $this->headers,
                    'query' => $data,
                ]
            );
            return $this->success($response);
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    public function delete(string $url)
    {
        try {
            $response = $this->client->delete(
                $url,
                [
                    'headers' => $this->headers,
                ]
            );

            return $this->success($response);
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    public function request(string $method, string $url, array $options = [])
    {
        try {
            $response = $this->client->request($method, $url, $options);

            return $this->success($response);
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    private function success($response)
    {
        $body = $response->getBody();

        return self::response(
            $response->getReasonPhrase(),
            $response->getStatusCode(),
            (json_decode((string) $body, true))
        );
    }

    private function error(Throwable $e)
    {
        try {
            throw $e;
        } catch (BadResponseException $e) {
            return self::response($e->getResponse()->getBody()->getContents(), $e->getCode());
        } catch (Throwable $e) {
            return self::response($e->getMessage(), $e->getCode());
        }
    }

    private function response($message, $status, $data = null)
    {
        return [
            'status' => $status,
            'message' => json_decode($message, true),
            'data' => $data,
        ];
    }

    public function validateResponse(array $process)
    {
        if ($process['status'] == 0) {
            throw new GuzzleException('Unable to connect to remote server. Kindly check your internet connection and retry.');
        }
    }
}

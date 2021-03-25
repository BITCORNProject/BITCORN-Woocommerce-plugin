<?php
require_once "vendor/autoload.php";

use \Firebase\JWT\JWT;
JWT::$leeway = 60*5;
class Bitcorn
{
    public function __construct($token_domain, $client_id, $client_secret, $audience, $validation_key)
    {
        $this->token_domain = $token_domain;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->audience = $audience;
        $this->validation_key = $validation_key;
    }

    const API = "https://bitcorncheckout.azurewebsites.net/api/v1";
    //"http:/localhost:8080/api/v1";//
    public function get_access_token()
    {
        $url = "https://" . $this->token_domain;
        $grant_type = "client_credentials";
        $request_body = [
            'form_params' => [
                "client_id" => $this->client_id,
                "client_secret" => $this->client_secret,
                "audience" => $this->audience,
                "grant_type" => $grant_type

            ]
        ];

        $client = new \GuzzleHttp\Client();
        $response = $client->request('POST', $url . "/oauth/token", $request_body);
        $body = $response->getBody();
        return json_decode($body)->access_token;
    }

    public function validate_transaction($transaction)
    {
        try {
            return JWT::decode($transaction, $this->validation_key, array('HS256'));
        } catch (Exception $e) {
            $object = new stdClass();
            $object->error = $e->getMessage() . " tx: " . $transaction;
            return $object;
        }
    }

    public function create_order($body)
    {
        return $this->api_request("/order/create", $body);
    }

    public function close_order($order_id, $tx_id)
    {
        return $this->api_request(
            "/order/close",
            [
                "clientId" =>  $this->client_id,
                "txId" => $tx_id,
                "orderId" => $order_id
            ]
        );
    }

    public function api_request($resource, $data)
    {

        $token = $this->get_access_token();

        $request_url = Bitcorn::API . $resource;
        $headers = [
            'Authorization' => "Bearer {$token}",
            "Content-Type" => "application/json"
        ];

        $data = json_encode($data);
        $client = new \GuzzleHttp\Client();
        $request = new \GuzzleHttp\Psr7\Request('POST', $request_url, $headers, $data);

        $response = $client->send($request);
        if ($response->getStatusCode() == 200) {
            return json_decode($response->getBody());
        } else {
            $object = new stdClass();
            $object->status = $response->getStatusCode();
            return $object;
        }
    }
}

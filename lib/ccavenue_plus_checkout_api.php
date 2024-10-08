<?php
use Blesta\Core\Util\Common\Traits\Container;

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'ccavenue_plus_checkout_response.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'commands' . DIRECTORY_SEPARATOR . 'ccavenue_plus_checkout_orders.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'commands' . DIRECTORY_SEPARATOR . 'ccavenue_plus_checkout_payments.php';

/**
 * CCAvenue Checkout API
 */
class CCAvenuePlusCheckoutApi
{
    // Load traits
    use Container;
    
    /**
     * @var array The API URL
     */
    private $api_url = [
        'sandbox' => 'https://test.ccavenue.com/transaction/transaction.do?command=initiateTransaction',
        'live' => 'https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction'
    ];

    /**
     * @var array The data sent with the last request served by this API
     */
    private $last_request = [];

    /**
     * @var string The merchant ID used for OAuth authentication
     */
    private $merchant_id;

    /**
     * @var string The Access code used for OAuth authentication
     */
    private $access_code;

    /**
     * @var string The working key used for OAuth authentication
     */
    private $working_key;

    /**
     * @var string The API environment, it could be live or sandbox
     */
    private $environment;

    /**
     * @var string The OAuth token returned by CCAvenue to be used on this instance
     */
    private $token;

    /**
     * Initializes the request parameter
     */
    public function __construct(string $merchant_id, string $access_code, string $working_key, string $environment = 'sandbox')
    {
        $this->merchant_id = $merchant_id;
        $this->access_code = $access_code;
        $this->working_key = $working_key;
        $this->environment = $environment;

        // Initialize logger
        $logger = $this->getFromContainer('logger');
        $this->logger = $logger;
    }

    /**
     * Authenticates to the CCAvenue API using OAuth
     *
     * @return string The OAuth token
     */
    private function authenticate()
    {
        $permissions = ['grant_type' => 'client_credentials'];
        $headers = [
            'Authorization: Basic ' . base64_encode($this->merchant_id . ':' . $this->access_code),
            'Content-Type: application/x-www-form-urlencoded'
        ];
        $session = $this->apiRequest('/v1/oauth2/token', $permissions, 'POST', $headers);
        $response = $session->response();

        $this->token = $response->access_token ?? null;

        if (empty($this->token) || $session->errors()) {
            throw new Exception('It was not possible to authenticate to the API. Verify that the credentials are correct.');
        }

        return $this->token;
    }

    /**
     * Send an API request to CcavenuePlusCheckout
     *
     * @param string $route The path to the API method
     * @param array $body The data to be sent
     * @param string $method Data transfer method (POST, GET, PUT, DELETE)
     * @param array $headers Overrides the default headers for this request
     * @return CCAvenuePlusCheckoutResponse
     */
    public function apiRequest($route, array $body = [], $method = 'GET', array $headers = [])
    {
        $url = $this->api_url[$this->environment] . '/' . ltrim($route ?? '', '/');
        $curl = curl_init();

        if (!empty($body)) {
            switch (strtoupper($method)) {
                case 'DELETE':
                    // Set data using get parameters
                case 'GET':
                    $url .= empty($body) ? '' : '?' . http_build_query($body);
                    break;
                case 'POST':
                    curl_setopt($curl, CURLOPT_POST, 1);
                // Use the default behavior to set data fields
                default:
                    if (in_array('Content-Type: application/x-www-form-urlencoded', $headers)) {
                        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($body));
                    } else {
                        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
                    }
                    break;
            }
        }

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSLVERSION, 1);

        if (Configure::get('Blesta.curl_verify_ssl')) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        // Set request headers
        if (empty($headers)) {
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->token
            ];
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $this->last_request = ['content' => $body, 'headers' => $headers];
        $result = curl_exec($curl);
        if (curl_errno($curl)) {
            $error = [
                'error' => 'Curl Error',
                'message' => 'An internal error occurred, or the server did not respond to the request.',
                'status' => 500
            ];

            return new CCAvenuePlusCheckoutResponse(['content' => json_encode($error), 'headers' => []]);
        }
        curl_close($curl);

        $data = explode("\n", $result);

        // Return request response
        return new CCAvenuePlusCheckoutResponse([
            'content' => $data[count($data) - 1],
            'headers' => array_splice($data, 0, count($data) - 1)]
        );
    }

    /**
     * Returns the details of the last request made
     *
     * @return array An array containing:
     *  - content The data of the request
     *  - headers The headers sent to the request
     */
    public function lastRequest() : array
    {
        return $this->last_request;
    }
}

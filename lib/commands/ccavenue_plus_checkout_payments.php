<?php
/**
 * CCAvenue Checkout Payments Management
 */
class CCAvenuePlusCheckoutPayments
{
    /**
     * @var CCAvenuePlusCheckoutApi
     */
    private $api;

    /**
     * Sets the API to use for communication
     *
     * @param CCAvenuePlusCheckoutApi $api The API to use for communication
     */
    public function __construct(CCAvenuePlusCheckoutApi $api)
    {
        $this->api = $api;
    }

    /**
     * Show details for captured payment
     *
     * @param array $vars An array of input params including:
     *
     *  - id The ID of the captured payment for which to show details
     * @return CCAvenuePlusCheckoutResponse The response object
     */
    public function get(array $vars) : CCAvenuePlusCheckoutResponse
    {
        return $this->api->apiRequest('/v2/payments/captures/' . ($vars['id'] ?? ''));
    }

    /**
     * Refund captured payment
     *
     * @param array $vars An array of input params including:
     */
    public function refund(array $vars) : CCAvenuePlusCheckoutResponse
    {
        $params = $vars;
        unset($params['capture_id']);

        return $this->api->apiRequest(
            '/v2/payments/captures/' . ($vars['capture_id'] ?? '') . '/refund',
            $params,
            'POST'
        );
    }

    /**
     * Void an authorized payment
     *
     * @param array $vars An array of input params including:
     */
    public function void(array $vars) : CCAvenuePlusCheckoutResponse
    {
        return $this->api->apiRequest(
            '/v2/payments/authorization_id/' . ($vars['authorization_id'] ?? '') . '/void',
            [],
            'POST'
        );
    }

    /**
     * Capture payment
     *
     * @param array $vars An array of input params including:
     */
    public function capture(array $vars) : CCAvenuePlusCheckoutResponse
    {
        $params = $vars;
        unset($params['id']);

        return $this->api->apiRequest(
            '/v2/payments/authorizations/' . ($vars['id'] ?? '') . '/capture',
            $params,
            'POST'
        );
    }
}
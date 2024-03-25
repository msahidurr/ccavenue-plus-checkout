<?php

Configure::errorReporting(E_ALL);
Configure::errorReporting(0);
Configure::errorReporting(-1);
/**
 * CCAvenue Checkout Gateway
 *
 * Allows users to pay via CCAvenue and 10+ local payment methods
 */
class CcavenuePlusCheckout extends NonmerchantGateway
{
    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;

    /**
     * Construct a new merchant gateway
     */
    public function __construct()
    {
        // Load the CCAvenue Checkout API
        // Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'ccavenue_plus_checkout_api.php');

        // Load configuration required by this gateway
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load components required by this gateway
        Loader::loadComponents($this, ['Input']);

        Loader::loadModels($this, ['Clients', 'Contacts', 'Companies']);

        // Load the language required by this gateway
        Language::loadLang('ccavenue_plus_checkout', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Sets the meta data for this particular gateway
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }

    /**
     * Create and return the view content required to modify the settings of this gateway
     *
     * @param array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(array $meta = null)
    {
        // Load the view into this object, so helpers can be automatically add to the view
        $this->view = new View('settings', 'default');
        $this->view->setDefaultView('components' . DS . 'gateways' . DS . 'nonmerchant' . DS . 'ccavenue_plus_checkout' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('meta', $meta);

        return $this->view->fetch();
    }

    /**
     * Validates the given meta (settings) data to be updated for this gateway
     *
     * @param array $meta An array of meta (settings) data to be updated for this gateway
     * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
     */
    public function editSettings(array $meta)
    {
        // Set unset checkboxes
        $checkbox_fields = ['sandbox'];
        foreach ($checkbox_fields as $checkbox_field) {
            if (!isset($meta[$checkbox_field])) {
                $meta[$checkbox_field] = 'false';
            }
        }

        // Set rules
        $rules = [
            'merchant_id' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('CcavenuePlusCheckout.!error.merchant_id.valid', true)
                ]
            ],
            'access_code' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('CcavenuePlusCheckout.!error.access_code.valid', true)
                ]
            ],
            'working_key' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('CcavenuePlusCheckout.!error.working_key.valid', true)
                ]
            ],
        ];

        $this->Input->setRules($rules);

        // Validate the given meta data to ensure it meets the requirements
        $this->Input->validates($meta);

        // Return the meta data, no changes required regardless of success or failure for this gateway
        return $meta;
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields()
    {
        return ['access_code', 'working_key'];
    }

    /**
     * Sets the currency code to be used for all subsequent payments
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent payments
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * Returns all HTML markup required to render an authorization and capture payment form
     *
     * @param array $contact_info An array of contact info including:
     *  - id The contact ID
     *  - merchant_id The ID of the client this contact belongs to
     *  - user_id The user ID this contact belongs to (if any)
     *  - contact_type The type of contact
     *  - contact_type_id The ID of the contact type
     *  - first_name The first name on the contact
     *  - last_name The last name on the contact
     *  - title The title of the contact
     *  - company The company name of the contact
     *  - address1 The address 1 line of the contact
     *  - address2 The address 2 line of the contact
     *  - city The city of the contact
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-cahracter country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the contact
     * @param float $amount The amount to charge this contact
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @param array $options An array of options including:
     *  - description The Description of the charge
     *  - return_url The URL to redirect users to after a successful payment
     *  - recur An array of recurring info including:
     *      - amount The amount to recur
     *      - term The term to recur
     *      - period The recurring period (day, week, month, year, onetime) used in conjunction
     *          with term in order to determine the next recurring payment
     * @return string HTML markup required to render an authorization and capture payment form
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {
        // Force 2-decimal places only
        $amount = round($amount, 2);
        
        $formData = $this->formData($contact_info, $invoice_amounts);

        $formData['amount'] = $amount;

        $merchant_data = http_build_query($formData);

        $post_to = '';

        if($this->meta['sandbox'] == 'true') {
            $post_to = 'https://test.ccavenue.com/transaction/transaction.do?command=initiateTransaction';
        } else {
            $post_to = 'https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction';
        }

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        $this->view->set('post_to', $post_to);

        // Method for encrypting the data.
        $encRequest = $this->Clients->systemEncrypt($merchant_data, $this->meta['encryption_key'] ?? '');
        $this->view->set('encRequest', $encRequest);
        $this->view->set('access_code', (isset($this->meta['access_code']) ? $this->meta['access_code'] : null));
        $this->view->set('merchant_id', (isset($Merchant_Id) ? $Merchant_Id : null));

        // Log request received
        return $this->view->fetch();
    }

    /**
     * Validates the incoming POST/GET response from the gateway to ensure it is
     * legitimate and can be trusted.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, sets any errors using Input if the data fails to validate
     *  - merchant_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this
     *      transaction's original transaction (in the case of refunds)
     */
    public function validate(array $get, array $post)
    {
        echo "validate";
        print_r("<pre>");
        print_r($get);
        print_r("post: ");
        // print_r($post); die();        
    }

    /**
     * Returns data regarding a success transaction. This method is invoked when
     * a client returns from the non-merchant gateway's web site back to Blesta.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, may set errors using Input if the data appears invalid
     *  - merchant_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction
     */
    public function success(array $get, array $post)
    {
        echo "success";
        print_r("<pre>");
        print_r($get);
        print_r("post: ");
        print_r($post); die();
    }

    /**
     * Serializes an array of invoice info into a string
     *
     * @param array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     * @return string A serialized string of invoice info in the format of key1=value1|key2=value2
     */
    private function serializeInvoices(array $invoices)
    {
        $str = '';
        foreach ($invoices as $i => $invoice) {
            $str .= ($i > 0 ? '|' : '') . $invoice['id'] . '=' . $invoice['amount'];
        }
        return $str;
    }

    /**
     * Unserializes a string of invoice info into an array
     *
     * @param string A serialized string of invoice info in the format of key1=value1|key2=value2
     * @return array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     */
    private function unserializeInvoices($str)
    {
        $invoices = [];
        $temp = explode('|', $str);
        foreach ($temp as $pair) {
            $pairs = explode('=', $pair, 2);
            if (count($pairs) != 2) {
                continue;
            }
            $invoices[] = ['id' => $pairs[0], 'amount' => $pairs[1]];
        }
        return $invoices;
    }

    /**
     * Loads the given API if not already loaded
     *
     * @param string $merchant_id The client ID of CCAvenue Checkout
     * @param string $access_code The client secret key
     * @param string $sandbox Whether or not to use the sandbox environment
     */
    private function formData( array $contact_info, array $invoice_amounts = null)
    {
        $client = $this->Clients->get($contact_info['client_id']);

        $order_id = '';

        if(isset($contact_info['client_id'])) {
            $order_id = $contact_info['client_id'] . '-' . time();
        } else {
            $order_id =  time();
        }

        $redirect_url = Configure::get('Blesta.gw_callback_url')
            . Configure::get('Blesta.company_id') . '/ccavenue_plus_checkout/'
            . (isset($contact_info['client_id']) ? $contact_info['client_id'] : null);

        return [
            'merchant_id' => $this->meta['merchant_id'] ?? '',
            'currency' => $this->currency,
            'order_id' => $order_id,
            'redirect_url' => $redirect_url,
            'billing_name' => (
                (isset($contact_info['first_name']) ? $contact_info['first_name'] : null) .' '.  (isset($contact_info['last_name']) ? $contact_info['last_name'] : null)
            ),
            'billing_address' => (
                (isset($contact_info['address1']) ? $contact_info['address1'] : null) . ' ' . (isset($contact_info['address2']) ? $contact_info['address2'] : null)
            ),
            'billing_city' =>  (isset($contact_info['city']) ? $contact_info['city'] : null),
            'billing_state' => (isset($contact_info['state']['name']) ? $contact_info['state']['name'] : null),
            'billing_zip' => (isset($contact_info['zip']) ? $contact_info['zip'] : null),
            'billing_country' => trim((isset($contact_info['country']['name']) ? $contact_info['country']['name'] : null)),
            'billing_tel' => ($this->getContact($client)),
            'billing_email' => (isset($client->email) ? $client->email : null),
            'delivery_name' => (
                (isset($contact_info['first_name']) ? $contact_info['first_name'] : null) .' '.  (isset($contact_info['last_name']) ? $contact_info['last_name'] : null)
            ),
            'delivery_address' => (
                (isset($contact_info['address1']) ? $contact_info['address1'] : null) . ' ' . (isset($contact_info['address2']) ? $contact_info['address2'] : null)
            ),
            'delivery_city' => (isset($contact_info['city']) ? $contact_info['city'] : null),
            'delivery_state' => (isset($contact_info['state']['name']) ? $contact_info['state']['name'] : null),
            'delivery_zip' => (isset($contact_info['zip']) ? $contact_info['zip'] : null),
            'delivery_country' => trim((isset($contact_info['country']['name']) ? $contact_info['country']['name'] : null)),
            'delivery_tel' => ($this->getContact($client)),
            'merchant_param1' =>  $this->serializeInvoices($invoice_amounts),
            'merchant_param2' => (isset($client->id) ? $client->id : null)
        ];
    }

    private function getContact($client)
    {
        // Get any phone/fax numbers
        $contact_numbers = $this->Contacts->getNumbers($client->contact_id);

        // Set any contact numbers (only the first of a specific type found)
        $data = '';
        foreach ($contact_numbers as $contact_number) {
            switch ($contact_number->location) {
                case 'home':
                    // Set home phone number
                    if ($contact_number->type == 'phone') {
                        $data = $contact_number->number;
                    }
                    break;
                case 'work':
                    // Set work phone/fax number
                    if ($contact_number->type == 'phone') {
                        $data = $contact_number->number;
                    }
                    // No break?
                case 'mobile':
                    // Set mobile phone number
                    if ($contact_number->type == 'phone') {
                        $data = $contact_number->number;
                    }
                    break;
            }
        }
        
        return preg_replace('/[^0-9]/', '', $data);
    }
}

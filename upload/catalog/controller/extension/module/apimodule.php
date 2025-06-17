<?php

class ControllerExtensionModuleApimodule extends Controller {
    private $API_VERSION = 2;

    private $get_site_info_url = 'https://check.pinta.com.ua/api/site';
    private $get_access_token_url = 'https://check.pinta.com.ua/api/auth';
    private $increment_push_count_url = 'https://check.pinta.com.ua/api/site/push';
    private $get_push_limit_url = 'https://check.pinta.com.ua/api/site/push';

    public function __construct($registry) {
        parent::__construct($registry);
        $this->load->model('extension/module/apimodule');
    }

    public function getVersion() {
        return $this->API_VERSION;
    }

    // catalog/controller/module/apimodule/*/before
    public function routerIndex(&$route, &$args) {
        $route = 'extension/' . $route;
    }

    // catalog/model/checkout/order/addOrderHistory/after
    public function afterAoh(&$route, &$args, &$output) {
        if (!empty($args) && (count($args) >= 2)) {
            $order_id = $args[0];
            if (count($this->model_extension_module_apimodule->getOrderHistory($order_id)) == 1) {
                $order = $this->model_extension_module_apimodule->getOrderFindById($order_id);
                $total = $this->currency->format($order['total'], $order['currency_code'], $order['currency_value']);
                $body = 'You have a new order! ' . $total;

                $this->sendNotifications([
                    'action' => 'place_new_order',
                    'body' => $body,
                    'data' => [
                        'order_id' => $order_id,
                        'total' => $total,
                        'currency_code' => $order['currency_code'],
                        'site_url' => $this->getBaseUrl()
                    ]
                ]);
            } else {
                $order = $this->model_checkout_order->getOrder($order_id);
                $body = $order['order_status'];

                $this->sendNotifications([
                    'action' => 'order_status_changed',
                    'body' => $body,
                    'data' => [
                        'order_id' => $order_id,
                        'order_status_id' => $order['order_status'],
                        'site_url' => $this->getBaseUrl()
                    ]
                ]);
            }
        }
    }

    // catalog/model/account/customer/addCustomer/after
    public function afterAddCustomer(&$route, &$args, &$output) {
        if (!empty($output)) {
            $customer_id = (int) $output;
            $customer = $this->model_account_customer->getCustomer($customer_id);
            $body = $customer['firstname'];

            $this->sendNotifications([
                'action' => 'new_customer',
                'body' => $body,
                'data' => [
                    'customer_id' => $customer_id,
                    'customer_firstname' => $customer['firstname'],
                    'site_url' => $this->getBaseUrl()
                ]
            ]);
        }
    }

    // catalog/model/catalog/review/addReview/after
    public function afterAddReview(&$route, &$args, &$output) {
        $product_id = $args[0];
        $this->load->model('catalog/product');
        $product = $this->model_catalog_product->getProduct($product_id);
        $body = $product['name'];

        $this->sendNotifications([
            'action' => 'new_review',
            'body' => $body,
            'data' => [
                'product_id' => $product_id,
                'product_name' => $product['name'],
                'site_url' => $this->getBaseUrl()
            ]
        ]);
    }

    /**
     * @api {post} index.php?route=module/apimodule/orders  Orders List
     * @apiName  GetOrders
     * @apiDescription  List of user orders
     * @apiGroup Order
     *
     * @apiParam {Token}     token your unique token.
     * @apiParam {Number}    page number of the page.
     * @apiParam {Number}    limit limit of the orders for the page.
     * @apiParam {Array[]}   filter Array of the filters.
     * @apiParam {String}    filter.fio full name of the client.
     * @apiParam {Number}    filter.order_status_id unique id of the order.
     * @apiParam {Number}    filter.min_price min price of order.
     * @apiParam {Number}    filter.max_price max price of order.
     * @apiParam {Date}      filter.date_min min date adding of the order.
     * @apiParam {Date}      filter.date_max max date adding of the order.
     *
     * @apiSuccess {Number}  version                          Current API version.
     * @apiSuccess {Bool}    status                           Response status.
     * @apiSuccess {Array[]} response                         Array with content response.
     * @apiSuccess {String}  response.total_quantity          Total quantity of the orders.
     * @apiSuccess {String}  response.currency_code           Default currency of the shop.
     * @apiSuccess {Number}  response.total_sum               Total amount of orders.
     * @apiSuccess {String}  response.max_price               Maximum order amount.
     * @apiSuccess {Number}  response.api_version             Current API version.
     *
     * @apiSuccess {Array[]} response.orders                  Array of the orders.
     * @apiSuccess {Array[]} response.statuses                Array of the order statuses.

     * @apiSuccess {String} response.orders.order_id          ID of the order.
     * @apiSuccess {String} response.orders.order_number      Number of the order.
     * @apiSuccess {String} response.orders.fio               Client's FIO.
     * @apiSuccess {String} response.orders.status            Status of the order.
     * @apiSuccess {String} response.orders.total             Total sum of the order.
     * @apiSuccess {String} response.orders.date_added        Date added of the order.
     * @apiSuccess {String} response.orders.currency_code     Currency of the order.
     *
     * @apiSuccess {String} response.statuses.name            Status Name.
     * @apiSuccess {String} response.statuses.order_status_id Status id.
     * @apiSuccess {String} response.statuses.language_id     Language id.
     *
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *     "Response" {
     *        "orders": {
     *            {
     *             "order_id" : "1",
     *             "order_number" : "1",
     *             "fio" : "Anton Kiselev",
     *             "status" : "Сделка завершена",
     *             "total" : "106.00",
     *             "date_added" : "2016-12-09 16:17:02",
     *             "currency_code": "RUB"
     *             },
     *            {
     *             "order_id" : "2",
     *             "order_number" : "2",
     *             "fio" : "Vlad Kochergin",
     *             "status" : "В обработке",
     *             "total" : "506.00",
     *             "date_added" : "2016-10-19 16:00:00",
     *             "currency_code": "RUB"
     *             }
     *        },
     *        "statuses" : {
     *             {
     *              "name": "Отменено",
     *              "order_status_id": "7",
     *              "language_id": "1"
     *              },
     *             {
     *              "name": "Сделка завершена",
     *              "order_status_id": "5",
     *              "language_id": "1"
     *              },
     *              {
     *               "name": "Ожидание",
     *               "order_status_id": "1",
     *               "language_id": "1"
     *               }
     *       },
     *       "currency_code": "RUB",
     *       "total_quantity": 50,
     *       "total_sum": 2026.00,
     *       "max_price": "1405.00"
     *   },
     *   "Status" : true,
     *   "version": 2.0
     * }
     * @apiErrorExample Error-Response: {
     *      "version": 2.0,
     *      "Status" : false
     * }
     *
     *
     */
    public function orders()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {

            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
            return;
        }

        if (isset($_REQUEST['page']) && (int)$_REQUEST['page'] != 0 && isset($_REQUEST['limit']) && (int)$_REQUEST['limit'] != 0) {
            $page = ($_REQUEST['page'] - 1) * $_REQUEST['limit'];
            $limit = $_REQUEST['limit'];
        } else {
            $page = 0;
            $limit = 9999;
        }

        $this->load->model('extension/module/apimodule');

//        var_export($_REQUEST['filter']);
//        die();
        if (isset($_REQUEST['filter'])) {
            $orders = $this->model_extension_module_apimodule->getOrders(['filter' => $_REQUEST['filter'], 'page' => $page, 'limit' => $limit]);
        } elseif (isset($_REQUEST['platform']) && $_REQUEST['platform'] == 'android') {
            $filter = [];
            if (isset($_REQUEST['order_status_id'])) {
                $filter['order_status_id'] = $_REQUEST['order_status_id'];
            }
            if (isset($_REQUEST['fio'])) {
                $filter['fio'] = $_REQUEST['fio'];
            }
            if (isset($_REQUEST['min_price']) && $_REQUEST['min_price'] != 0) {
                $filter['min_price'] = $_REQUEST['min_price'];
            } else {
                $filter['min_price'] = 1;
            }
            if (isset($_REQUEST['max_price'])) {
                $filter['max_price'] = $_REQUEST['max_price'];
            } else {
                $filter['max_price'] = $this->model_extension_module_apimodule->getMaxOrderPrice();
            }

            $filter['date_min'] = $_REQUEST['date_min'];
            $filter['date_max'] = $_REQUEST['date_max'];

            $orders = $this->model_extension_module_apimodule->getOrders(['filter' => $filter, 'page' => $page, 'limit' => $limit]);

        } else {
            $orders = $this->model_extension_module_apimodule->getOrders(['page' => $page, 'limit' => $limit]);
        }
        $response = [];
        $orders_to_response = [];

        $currency = $this->model_extension_module_apimodule->getUserCurrency();
        if (empty($currency)) {
            $currency = $this->model_extension_module_apimodule->getDefaultCurrency();
        }
        $response['currency_code'] = $currency;

        foreach ($orders->rows as $order) {
            $data['order_number'] = $order['order_id'];
            $data['order_id'] = $order['order_id'];
            if (isset($order['firstname']) && isset($order['lastname'])) {
                $data['fio'] = $order['firstname'] . ' ' . $order['lastname'];
            } else {
                $data['fio'] = $order['payment_firstname'] . ' ' . $order['payment_lastname'];
            }
            $data['status'] = $order['name'];

            $data['total'] = $this->currency->format($order['total'], $order['currency_code'], $order['currency_value']);
            $data['date_added'] = $order['date_added'];
            $data['currency_code'] = $order['currency_code'];
            $orders_to_response[] = $data;
        }

        $response['total_quantity'] = $orders->quantity;
        $response['currency_code'] = $currency;
        $response['total_sum'] = $this->calculatePrice($orders->totalsumm, $currency);
        //$response['total_sum'] = number_format($orders->totalsumm, 2, '.', '');
        $response['orders'] = $orders_to_response;
        $response['max_price'] = $this->model_extension_module_apimodule->getMaxOrderPrice();
        $statuses = $this->model_extension_module_apimodule->OrderStatusList();
        $response['statuses'] = $statuses;
        $response['api_version'] = $this->API_VERSION;

        $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'response' => $response, 'status' => true]));
        return;
    }


    /**
     * @return void
     */
    public function getReview()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => $error,
                'status' => false]));
            return;
        }

        $product_id = $_REQUEST['product_id'];

        $this->load->model('extension/module/apimodule');
        $reviews = $this->model_extension_module_apimodule->getReviewComment($product_id);

        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'status' => true,
                'response' =>[
                    'attributes' => $reviews
                ]
            ]
        ));
    }


    /**
     * @return void
     */
    public function getOption()
    {

        ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => $error,
                'status' => false]));
            return;
        }

        $value = $_REQUEST['value'];

        if(isset($option_id)) {
            $option_id = $_REQUEST['option_value_id'];
        } else {
//            $option_id = '';
        }

        $this->load->model('extension/module/apimodule');
        $option = $this->model_extension_module_apimodule->getOption($value,$option_id = null);

        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'status' => true,
                'response' =>[
                    'attributes' => $option
                ]
            ]
        ));
    }


    /**
     * @return void
     */
    public function getCustomerGroup()
    {


        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => $error,
                'status' => false]));
            return;
        }

        $this->load->model('extension/module/apimodule');
        $option = $this->model_extension_module_apimodule->getCustomerGroups();


        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'status' => true,
                'response' =>[
                    'attributes' => $option
                ]
            ]
        ));
    }


    /**
     * @return void
     */
    public function getStore()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => $error,
                'status' => false]));
            return;
        }

        $this->load->model('extension/module/apimodule');
        $option = $this->model_extension_module_apimodule->getStores();


        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'status' => true,
                'response' =>[
                    'attributes' => $option
                ]
            ]
        ));
    }


    /**
     * @return void
     */
    public function getCountry()
    {


        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => $error,
                'status' => false]));
            return;
        }

        $this->load->model('extension/module/apimodule');
        $option = $this->model_extension_module_apimodule->getCountries();


        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'status' => true,
                'response' =>[
                    'attributes' => $option
                ]
            ]
        ));
    }


    /**
     * @return void
     */
    public function setZone()
    {


        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => $error,
                'status' => false]));
            return;
        }

        $id_country = $_REQUEST['country_id'];

        $this->load->model('extension/module/apimodule');
        $option = $this->model_extension_module_apimodule->getZone($id_country);


        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'status' => true,
                'response' =>[
                    'attributes' => $option
                ]
            ]
        ));
    }


    /**
     * @return void
     */
    public function getClient()
    {


        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => $error,
                'status' => false]));
            return;
        }


        $client_info = $_REQUEST['store_id'];


        $this->load->model('extension/module/apimodule');
        $option = $this->model_extension_module_apimodule->getClientStore($client_info);


        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'status' => true,
                'response' =>[
                    'attributes' => $option
                ]
            ]
        ));
    }


    /**
     * @return void
     */
    public function getCurrency()
    {


        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => $error,
                'status' => false]));
            return;
        }


        $this->load->model('extension/module/apimodule');
        $option = $this->model_extension_module_apimodule->getCurrency();


        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'status' => true,
                'response' =>[
                    'attributes' => $option
                ]
            ]
        ));
    }


    /**
     * @return void
     */
    public function getProducts()
    {


        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => $error,
                'status' => false]));
            return;
        }


        $client_info = $_REQUEST['store_id'];


        $this->load->model('extension/module/apimodule');
        $option = $this->model_extension_module_apimodule->getProd($client_info);


        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'status' => true,
                'response' =>[
                    'attributes' => $option
                ]
            ]
        ));
    }


    /**
     * @return void
     */
    public function getMethodPayment()
    {

        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => $error,
                'status' => false]));
            return;
        }


        $this->load->language('checkout/checkout');

        // Totals
        $totals = array();
        $taxes = $this->cart->getTaxes();
        $total = 0;

        // Because __call can not keep var references so we put them into an array.
        $total_data = array(
            'totals' => &$totals,
            'taxes'  => &$taxes,
            'total'  => &$total
        );

        $this->load->model('setting/extension');

        $sort_order = array();

        $results = $this->model_setting_extension->getExtensions('total');

        foreach ($results as $key => $value) {
            $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
        }

        array_multisort($sort_order, SORT_ASC, $results);


        foreach ($results as $result) {
            if ($this->config->get('total_' . $result['code'] . '_status')) {
                $this->load->model('extension/total/' . $result['code']);

                // We have to put the totals in an array so that they pass by reference.
                $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
            }
        }

        // Payment Methods
        $method_data = array();

        $this->load->model('setting/extension');

        $results = $this->model_setting_extension->getExtensions('payment');

        $recurring = $this->cart->hasRecurringProducts();

        foreach ($results as $result) {



            $this->load->model('extension/payment/' . $result['code']);
            $address = array();

            $method = $this->{'model_extension_payment_' . $result['code']}->getMethod($address, $total);

            if ($method) {

                $method_data[] = $method;
            }
        }


        $sort_order = array();

        foreach ($method_data as $key => $value) {

            $sort_order[$key] = $value['sort_order'];
        }

        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'status' => true,
                'response' =>[
                    'attributes' => $method_data
                ]
            ]
        ));

    }


    /**
     * @return void
     */
    public function getMethodShipping()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => $error,
                'status' => false]));
            return;
        }

        $this->load->language('checkout/checkout');


        // Shipping Methods
        $method_data = array();

        $this->load->model('setting/extension');

        $results = $this->model_setting_extension->getExtensions('shipping');

        foreach ($results as $result) {

            $this->load->model('extension/shipping/' . $result['code']);

            $quote = $this->{'model_extension_shipping_' . $result['code']}->getQuote($this->session->data['shipping_address']);






            $method_data[] = $quote['quote']['flat'];


        }

//        var_dump($method_data);
//        die();



        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'status' => true,
                'response' =>
                    $method_data

            ]
        ));
    }


    /**
     * @return void
     */
    public function addReview()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => $error,
                'status' => false]));
            return;
        }


        $data = $_REQUEST['data'];


        $this->load->model('extension/module/apimodule');
        $new_review = $this->model_extension_module_apimodule->addReview($data);




        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'status' => true,
                'response' =>
                    $new_review

            ]
        ));
    }


    /**
     * @return void
     */
    public function editReview()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => $error,
                'status' => false]));
            return;
        }


        $data = $_REQUEST['data'];
        $review_id = $_REQUEST['review_id'];



        $this->load->model('extension/module/apimodule');
        $new_review = $this->model_extension_module_apimodule->editReview($review_id,$data);
        // $this->model_catalog_review->editReview($this->request->get['review_id'], $this->request->post);




        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'status' => true,
                'response' =>
                    $new_review

            ]
        ));
    }


    /**
     * @return void
     */
    public function newOrder()
    {

        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $this->load->model('checkout/order');

        $order_data = array();
        $totals = array();

        $order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
        $order_data['store_id'] = $this->config->get('config_store_id');
        $order_data['store_name'] = $this->config->get('config_name');

        if ($order_data['store_id']) {
            $order_data['store_url'] = $this->config->get('config_url');
        } else {
            if ($this->request->server['HTTPS']) {
                $order_data['store_url'] = HTTPS_SERVER;
            } else {
                $order_data['store_url'] = HTTP_SERVER;
            }
        }

        $this->load->model('account/customer');

        $order_customer = $_REQUEST['client'];

        $order_customer_data = $_REQUEST['client'];



        if (isset($order_customer_data)) {
            $customer_info = $this->model_account_customer->getCustomer($order_customer['customer_id']);



            if(isset($customer_info)) {
                $order_data['customer_id'] = $customer_info['customer_id'];
                $order_data['customer_group_id'] = $customer_info['customer_group_id'];
                $order_data['firstname'] = $customer_info['firstname'];
                $order_data['lastname'] = $customer_info['lastname'];
                $order_data['email'] = $customer_info['email'];
                $order_data['telephone'] = $customer_info['telephone'];
                $order_data['custom_field'] = json_decode($customer_info['custom_field'], true);
            } else {
                $order_data['customer_id'] = $order_customer['customer_id'];
                $order_data['customer_group_id'] = $order_customer['customer_group_id'];
                $order_data['firstname'] = $order_customer['firstname'];
                $order_data['lastname'] = $order_customer['lastname'];
                $order_data['email'] = $order_customer['email'];
                $order_data['telephone'] = $order_customer['telephone'];
                $order_data['custom_field'] = json_decode($order_customer['custom_field'], true);
            }

        } elseif (isset($this->session->data['guest'])) {
            $order_data['customer_id'] = 0;
            $order_data['customer_group_id'] = $this->session->data['guest']['customer_group_id'];
            $order_data['firstname'] = $this->session->data['guest']['firstname'];
            $order_data['lastname'] = $this->session->data['guest']['lastname'];
            $order_data['email'] = $this->session->data['guest']['email'];
            $order_data['telephone'] = $this->session->data['guest']['telephone'];
            $order_data['custom_field'] = $this->session->data['guest']['custom_field'];
        }



        $payment_data = $_REQUEST['payment'];

        $order_data['payment_firstname'] = $payment_data['payment_firstname'];
        $order_data['payment_lastname'] = $payment_data['payment_lastname'];
        $order_data['payment_company'] = $payment_data['payment_company'];
        $order_data['payment_address_1'] = $payment_data['payment_address_1'];
        $order_data['payment_address_2'] = $payment_data['payment_address_2'];
        $order_data['payment_city'] = $payment_data['payment_address_2'];
        $order_data['payment_postcode'] = $payment_data['payment_postcode'];
        $order_data['payment_zone'] = $payment_data['payment_zone'];
        $order_data['payment_zone_id'] = $payment_data['payment_zone_id'];
        $order_data['payment_country'] = $payment_data['payment_country'];
        $order_data['payment_country_id'] = $payment_data['payment_country_id'];
        $order_data['payment_address_format'] = $payment_data['payment_address_format'];
        $order_data['payment_custom_field'] = (isset($this->session->data['payment_address']['custom_field']) ? $this->session->data['payment_address']['custom_field'] : array());


        if (isset($payment_data['payment_method'])) {
            $order_data['payment_method'] = $payment_data['payment_method'];
        } else {
            $order_data['payment_method'] = '';
        }

        if (isset($pyment_order['payment_code'])) {
            $order_data['payment_code'] = $pyment_order['payment_code'];
        } else {
            $order_data['payment_code'] = '';
        }

        $shipping_order = $_REQUEST['shipping'];



        if (isset($shipping_order)) {
            $order_data['shipping_firstname'] = $shipping_order['shipping_firstname'];
            $order_data['shipping_lastname'] = $shipping_order['shipping_lastname'];
            $order_data['shipping_company'] = $shipping_order['shipping_company'];
            $order_data['shipping_address_1'] = $shipping_order['shipping_address_1'];
            $order_data['shipping_address_2'] = $shipping_order['shipping_address_2'];
            $order_data['shipping_city'] = $shipping_order['shipping_city'];
            $order_data['shipping_postcode'] = $shipping_order['shipping_postcode'];
            $order_data['shipping_zone'] = $shipping_order['shipping_zone'];
            $order_data['shipping_zone_id'] = $shipping_order['shipping_zone_id'];
            $order_data['shipping_country'] = $shipping_order['shipping_country'];
            $order_data['shipping_country_id'] = $shipping_order['shipping_country_id'];
            $order_data['shipping_address_format'] = $shipping_order['shipping_address_format'];
            $order_data['shipping_custom_field'] = $shipping_order['shipping_custom_field'];

            if (isset($shipping_order['shipping_method'])) {
                $order_data['shipping_method'] = $shipping_order['shipping_method'];
            } else {
                $order_data['shipping_method'] = '';
            }

            if (isset($shipping_order['shipping_code'])) {
                $order_data['shipping_code'] = $shipping_order['shipping_code'];
            } else {
                $order_data['shipping_code'] = '';
            }
        } else {
            $order_data['shipping_firstname'] = '';
            $order_data['shipping_lastname'] = '';
            $order_data['shipping_company'] = '';
            $order_data['shipping_address_1'] = '';
            $order_data['shipping_address_2'] = '';
            $order_data['shipping_city'] = '';
            $order_data['shipping_postcode'] = '';
            $order_data['shipping_zone'] = '';
            $order_data['shipping_zone_id'] = '';
            $order_data['shipping_country'] = '';
            $order_data['shipping_country_id'] = '';
            $order_data['shipping_address_format'] = '';
            $order_data['shipping_custom_field'] = array();
            $order_data['shipping_method'] = '';
            $order_data['shipping_code'] = '';
        }

        $order_product = array();

        $order_product['products'] = $_REQUEST['products'];

        //  $order_products = json_decode($order_product, true);

//        var_dump(json_decode($order_product, true));
//
//        var_dump($order_product);


//        foreach ($order_product['products'] as $key => $product) {
//            foreach ($product['option'] as $option) {
//
//
//                $option_data[] = array(
//                    'product_option_id'       => $option['product_option_id'],
//                    'product_option_value_id' => $option['product_option_value_id'],
//                    'option_id'               => $option['option_id'],
//                    'option_value_id'         => $option['option_value_id'],
//                    'name'                    => $option['name'],
//                    'value'                   => $option['value'],
//                    'type'                    => $option['type']
//                );
//            }
//
////            $order_product['product'][] = array(
////                'product_id' => $product['product_id'],
////                'name'       => $product['name'],
////                'model'      => $product['model'],
////                'option'     => $option_data,
////                'download'   => $product['download'],
////                'quantity'   => $product['quantity'],
////                'subtract'   => $product['subtract'],
////                'price'      => $product['price'],
////                'total'      => $product['total'],
////                'tax'        => $this->tax->getTax($product['price'], $product['tax_class_id']),
////                'reward'     => $product['reward']
////            );
//
//
//        }
        $order_data['products'] = $_REQUEST['products'];
//        var_dump($order_data['products']);
//        die();


        // Gift Voucher
        $order_data['vouchers'] = array();

        if (!empty($this->session->data['vouchers'])) {
            foreach ($this->session->data['vouchers'] as $voucher) {
                $order_data['vouchers'][] = array(
                    'description'      => $voucher['description'],
                    'code'             => token(10),
                    'to_name'          => $voucher['to_name'],
                    'to_email'         => $voucher['to_email'],
                    'from_name'        => $voucher['from_name'],
                    'from_email'       => $voucher['from_email'],
                    'voucher_theme_id' => $voucher['voucher_theme_id'],
                    'message'          => $voucher['message'],
                    'amount'           => $voucher['amount']
                );
            }
        }

        $order_data['comment'] = $_REQUEST['comment'];

        $sub_total = $_REQUEST['total'] - $_REQUEST['total_ship'];


        $totals[0] = [
            'code' => 'sub_total',
            'title' => 'Sub-Total',
            'value' => $sub_total,
            'sort_order' => 1
        ];

        $totals[1] = [
            'code' => 'shipping',
            'title' => 'Flat Shipping Rate',
            'value' => $_REQUEST['total_ship'],
            'sort_order' => 3
        ];

        $totals[2] = [
            'code' => 'total',
            'title' => 'Total',
            'value' => $_REQUEST['total'],
            'sort_order' => 9
        ];


        $order_data['totals'] = $totals;
        $order_data['total'] = $_REQUEST['total'];


        if (isset($this->request->cookie['tracking'])) {
            $order_data['tracking'] = $this->request->cookie['tracking'];

            $subtotal = $this->cart->getSubTotal();

            // Affiliate
            $affiliate_info = $this->model_account_customer->getAffiliateByTracking($this->request->cookie['tracking']);

            if ($affiliate_info) {
                $order_data['affiliate_id'] = $affiliate_info['customer_id'];
                $order_data['commission'] = ($subtotal / 100) * $affiliate_info['commission'];
            } else {
                $order_data['affiliate_id'] = 0;
                $order_data['commission'] = 0;
            }

            // Marketing
            $this->load->model('checkout/marketing');

            $marketing_info = $this->model_checkout_marketing->getMarketingByCode($this->request->cookie['tracking']);

            if ($marketing_info) {
                $order_data['marketing_id'] = $marketing_info['marketing_id'];
            } else {
                $order_data['marketing_id'] = 0;
            }
        } else {
            $order_data['affiliate_id'] = 0;
            $order_data['commission'] = 0;
            $order_data['marketing_id'] = 0;
            $order_data['tracking'] = '';
        }

        $order_data['order_status_id'] = $_REQUEST['status_id'];
        $order_data['language_id'] = $this->config->get('config_language_id');
        $order_data['currency_id'] = $this->currency->getId($this->session->data['currency']);
        $order_data['currency_code'] = $this->session->data['currency'];
        $order_data['currency_value'] = $this->currency->getValue($this->session->data['currency']);
        $order_data['ip'] = $this->request->server['REMOTE_ADDR'];

        if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
            $order_data['forwarded_ip'] = $this->request->server['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
            $order_data['forwarded_ip'] = $this->request->server['HTTP_CLIENT_IP'];
        } else {
            $order_data['forwarded_ip'] = '';
        }

        if (isset($this->request->server['HTTP_USER_AGENT'])) {
            $order_data['user_agent'] = $this->request->server['HTTP_USER_AGENT'];
        } else {
            $order_data['user_agent'] = '';
        }

        if (isset($this->request->server['HTTP_ACCEPT_LANGUAGE'])) {
            $order_data['accept_language'] = $this->request->server['HTTP_ACCEPT_LANGUAGE'];
        } else {
            $order_data['accept_language'] = '';
        }






        $order_info = $this->model_extension_module_apimodule->addOrder($order_data);

        $data_history = [
            'order_id' => $order_info,
            'order_status_id' => $order_data['order_status_id'],
            'notify' => 0,
            'comment' => $order_data['comment'],
            'date_added' => date("Y-m-d H:i:s"),
        ];


        $add_hisory_order = $this->model_extension_module_apimodule->historyorder($data_history);


        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => $error,
                'status' => false]));
            return;
        }

        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'status' => true,
                'response' =>[
                    'attributes' => $order_info,
                    'history' => $add_hisory_order,
                ]
            ]
        ));
    }




    /**
     * @api {post} index.php?route=module/apimodule/getorderinfo  Order Info
     * @apiName getOrderInfo
     * @apiDescription Order details
     * @apiGroup Order
     *
     * @apiParam {Token}       token                              Your unique token.
     * @apiParam {Number}      order_id                           Unique order ID.
     *
     * @apiSuccess {Number}    version                            Current API version.
     * @apiSuccess {Bool}      status                             Response status.
     * @apiSuccess {Array[]}   response                           Array with content response.
     *
     * @apiSuccess {String}    response.order_number              Number of the order.
     * @apiSuccess {String}    response.fio                       Client's FIO.
     * @apiSuccess {String}    response.status                    Status of the order.
     * @apiSuccess {String}    response.email                     Client's email.
     * @apiSuccess {String}    response.telephone                 Client's phone.
     * @apiSuccess {String}    response.total                     Total sum of the order.
     * @apiSuccess {String}    response.currency_code             Default currency of the shop.
     * @apiSuccess {String}    response.date_added                Date added of the order.
     * @apiSuccess {Array[]}   response.statuses                  Statuses list for order.
     *
     * @apiSuccess {String}    response.statuses.language_id      Language id
     * @apiSuccess {String}    response.statuses.name             Status name
     * @apiSuccess {String}    response.statuses.order_status_id  Status id
     *
     *
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *      "response" :
     *          {
     *              "order_number" : "6",
     *              "currency_code": "RUB",
     *              "fio" : "Anton Kiselev",
     *              "email" : "client@mail.ru",
     *              "telephone" : "056 000-11-22",
     *              "date_added" : "2016-12-24 12:30:46",
     *              "total" : "1405.00",
     *              "status" : "Сделка завершена",
     *              "statuses" :
     *                  {
     *                         {
     *                             "name": "Отменено",
     *                             "order_status_id": "7",
     *                             "language_id": "1"
     *                         },
     *                         {
     *                             "name": "Сделка завершена",
     *                             "order_status_id": "5",
     *                             "language_id": "1"
     *                          },
     *                          {
     *                              "name": "Ожидание",
     *                              "order_status_id": "1",
     *                              "language_id": "1"
     *                           }
     *                    }
     *          },
     *      "status" : true,
     *      "version": 1.0
     * }
     *
     * @apiErrorExample Error-Response: {
     *       "error" : "Can not found order with id = 5",
     *       "version": 1.0,
     *       "Status" : false
     *     }
     */
    public function getorderinfo()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');
        if (isset($_REQUEST['order_id']) && $_REQUEST['order_id'] != '') {
            $id = $_REQUEST['order_id'];

            $error = $this->valid();
            if ($error != null) {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
                return;
            }

            $this->load->model('extension/module/apimodule');
            $order = $this->model_extension_module_apimodule->getOrderById($id);

            if (count($order) > 0) {
                $data['order_number'] = $order[0]['order_id'];

                if (isset($order[0]['firstname']) && isset($order[0]['lastname'])) {
                    $data['fio'] = $order[0]['firstname'] . ' ' . $order[0]['lastname'];
                } else {
                    $data['fio'] = $order[0]['payment_firstname'] . ' ' . $order[0]['payment_lastname'];
                }
                if (isset($order[0]['email'])) {
                    $data['email'] = $order[0]['email'];
                } else {
                    $data['email'] = '';
                }
                if (isset($order[0]['telephone'])) {
                    $data['telephone'] = $order[0]['telephone'];
                } else {
                    $data['telephone'] = '';
                }

                $data['date_added'] = $order[0]['date_added'];

                if (isset($order[0]['total'])) {
                    $data['total'] = $this->currency->format($order[0]['total'], $order[0]['currency_code'], $order[0]['currency_value']);
                }
                if (isset($order[0]['name'])) {
                    $data['status'] = $order[0]['name'];
                } else {
                    $data['status'] = '';
                }
                $statuses = $this->model_extension_module_apimodule->OrderStatusList();
                $data['statuses'] = $statuses;
                $data['currency_code'] = $order[0]['currency_code'];
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'response' => $data, 'status' => true]));
            } else {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'Can not found order with id = ' . $id, 'status' => false]));
            }
        } else {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'You have not specified ID', 'status' => false]));
        }
    }

    /**
     * @api {post} index.php?route=module/apimodule/paymentanddelivery  Payment and delivery by order
     * @apiName getOrderPaymentAndDelivery
     * @apiDescription Receive payment and delivery by order
     * @apiGroup Order
     *
     * @apiParam {Number} order_id                                Unique order ID.
     * @apiParam {Token}  token your                              Unique token.
     *
     * @apiSuccess {Number}    version                            Current API version.
     * @apiSuccess {Bool}      status                             Response status.

     * @apiSuccess {Array[]}   response                           Array with content response.
     * @apiSuccess {String}    response.payment_method            Payment method.
     * @apiSuccess {String}    response.shipping_method           Shipping method.
     * @apiSuccess {String}    response.shipping_address          Shipping address.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *          "response":
     *              {
     *                  "payment_method" : "Оплата при доставке",
     *                  "shipping_method" : "Доставка с фиксированной стоимостью доставки",
     *                  "shipping_address" : "проспект Карла Маркса 1, Днепропетровск, Днепропетровская область, Украина."
     *              },
     *          "status": true,
     *          "version": 1.0
     *      }
     * @apiErrorExample Error-Response:
     *
     *    {
     *      "error": "Can not found order with id = 90",
     *      "version": 1.0,
     *      "Status" : false
     *   }
     *
     */
    public function paymentanddelivery()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');
        if (isset($_REQUEST['order_id']) && $_REQUEST['order_id'] != '') {
            $id = $_REQUEST['order_id'];

            $error = $this->valid();
            if ($error != null) {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
                return;
            }

            $this->load->model('extension/module/apimodule');
            $order = $this->model_extension_module_apimodule->getOrderById($id);



//var_dump($data);
//die();
            if (count($order) > 0) {
                $data['shipping_address'] = '';
//                var_dump($order);
//                die();
                if (isset($order[0]['payment_method']) && $order[0]['payment_method'] != '') {
                    $data['payment_method'] = $order[0]['payment_method'];
                }
                if (isset($order[0]['shipping_method']) && $order[0]['shipping_method'] != '') {
                    $data['shipping_method'] = $order[0]['shipping_method'];
                }

//                var_dump();
//                die();
                if (isset($order[0]['shipping_address_1']) && $order[0]['shipping_address_1'] != '') {
                    $data['shipping_address'] .= $order[0]['shipping_address_1'];
                }
                if (isset($order[0]['shipping_address_2']) && $order[0]['shipping_address_2'] != '') {
                    $data['shipping_address'] .= ', ' . $order[0]['shipping_address_2'];
                }
                if (isset($order[0]['shipping_city']) && $order[0]['shipping_city'] != '') {
                    $data['shipping_address'] .= ', ' . $order[0]['shipping_city'];
                }
                if (isset($order[0]['shipping_country']) && $order[0]['shipping_country'] != '') {
                    $data['shipping_address'] .= ', ' . $order[0]['shipping_country'];
                }
                if (isset($order[0]['shipping_zone']) && $order[0]['shipping_zone'] != '') {
                    $data['shipping_address'] .= ', ' . $order[0]['shipping_zone'];
                }

                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'response' => $data, 'status' => true]));
            } else {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'Can not found order with id = ' . $id, 'status' => false]));
            }
        } else {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'You have not specified ID', 'status' => false]));
        }
    }

    /**
     * @api {post} index.php?route=module/apimodule/orderhistory  Order Change History
     * @apiName getOrderHistory
     * @apiDescription View order changes list
     * @apiGroup Order
     *
     * @apiParam {Number}      order_id                           Unique order ID.
     * @apiParam {Token}       token                              Your unique token.
     *
     * @apiSuccess {Number}    version                            Current API version.
     * @apiSuccess {Bool}      status                             Response status.
     *
     * @apiSuccess {Array[]}   response                           Array with content response.
     * @apiSuccess {Array[]}   response.orders                    An array with information about the order.
     * @apiSuccess {String}    response.orders.name               Status of the order.
     * @apiSuccess {String}    response.orders.order_status_id    ID of the status of the order.
     * @apiSuccess {String}    response.orders.date_added         Date of adding status of the order.
     * @apiSuccess {String}    response.orders.comment            Some comment added from manager.
     * @apiSuccess {Array[]}   response.statuses                  Statuses list for order.
     *
     * @apiSuccess {String}    response.statuses.name             Status name.
     * @apiSuccess {String}    response.statuses.language_id      Language id.
     * @apiSuccess {String}    response.statuses.order_status_id  Status id.
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *           "response":
     *               {
     *                   "orders":
     *                      {
     *                          {
     *                              "name": "Отменено",
     *                              "order_status_id": "7",
     *                              "date_added": "2016-12-13 08:27:48.",
     *                              "comment": "Some text"
     *                          },
     *                          {
     *                              "name": "Сделка завершена",
     *                              "order_status_id": "5",
     *                              "date_added": "2016-12-25 09:30:10.",
     *                              "comment": "Some text"
     *                          },
     *                          {
     *                              "name": "Ожидание",
     *                              "order_status_id": "1",
     *                              "date_added": "2016-12-01 11:25:18.",
     *                              "comment": "Some text"
     *                           }
     *                       },
     *                    "statuses":
     *                        {
     *                             {
     *                                  "name": "Отменено",
     *                                  "order_status_id": "7",
     *                                  "language_id": "1"
     *                             },
     *                             {
     *                                  "name": "Сделка завершена",
     *                                  "order_status_id": "5",
     *                                  "language_id": "1"
     *                              },
     *                              {
     *                                  "name": "Ожидание",
     *                                  "order_status_id": "1",
     *                                  "language_id": "1"
     *                              }
     *                         }
     *               },
     *           "status": true,
     *           "version": 1.0
     *       }
     * @apiErrorExample Error-Response:  {
     *          "error": "Can not found any statuses for order with id = 5",
     *          "version": 1.0,
     *          "Status" : false
     *     }
     */
    public function orderhistory()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');
        if (isset($_REQUEST['order_id']) && $_REQUEST['order_id'] != '') {
            $id = $_REQUEST['order_id'];

            $error = $this->valid();
            if ($error != null) {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
                return;
            }

            $this->load->model('extension/module/apimodule');
            $statuses = $this->model_extension_module_apimodule->getOrderHistory($id);

            $data = [];
            $response = [];
            if (count($statuses) > 0) {
                for ($i = 0; $i < count($statuses); $i++) {
                    $data['name'] = $statuses[$i]['name'];
                    $data['order_status_id'] = $statuses[$i]['order_status_id'];
                    $data['date_added'] = $statuses[$i]['date_added'];
                    $data['comment'] = $statuses[$i]['comment'];
                    $response['orders'][] = $data;
                }

                $statuses = $this->model_extension_module_apimodule->OrderStatusList();
                $response['statuses'] = $statuses;

                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'response' => $response, 'status' => true]));
            } else {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'Can not found any statuses for order with id = ' . $id, 'status' => false]));
            }
        } else {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'You have not specified ID', 'status' => false]));
        }
    }

    /**
     * @api {post} index.php?route=module/apimodule/orderproducts  List of products in the order
     * @apiName getOrderProducts
     * @apiDescription Get the full list of products in the order
     * @apiGroup Order
     *
     * @apiParam   {Token}     token                              Your unique token.
     * @apiParam   {Number}    order_id                           Unique order id.
     *
     * @apiSuccess {Array[]}   response                           Array with content response.
     * @apiSuccess {Number}    version                            Current API version.
     *
     * @apiSuccess {Array[]}   response.products                  Array of products.
     * @apiSuccess {String}    response.products.name             Name of the product.
     * @apiSuccess {String}    response.products.image            Picture of the product.
     * @apiSuccess {String}    response.products.model            Model of the product.
     * @apiSuccess {String}    response.products.quantity         Quantity of the product.
     * @apiSuccess {String}    response.products.price            Price of the product.
     * @apiSuccess {String}    response.products.product_id       Unique product id.
     *
     * @apiSuccess {Array[]}   response.products.options                   Array of of the product options.
     * @apiSuccess {String}    response.products.options.option_id         Option id.
     * @apiSuccess {String}    response.products.options.option_name       Option name.
     * @apiSuccess {String}    response.products.options.option_value_id   Option value id.
     * @apiSuccess {String}    response.products.options.option_value_name Option value name.
     * @apiSuccess {String}    response.products.options.language_id       Language id of options and option values.
     *
     * @apiSuccess {Array[]}   response.products.attributes                        Array of of the product attributes.
     * @apiSuccess {String}    response.products.attributes.attribute_group_id     Attribute group id.
     * @apiSuccess {String}    response.products.attributes.name                   Attribute name.
     * @apiSuccess {Array[]}   response.products.attributes.attribute              Product attributes.
     * @apiSuccess {String}    response.products.attributes.attribute.attribute_id Attribute id.
     * @apiSuccess {String}    response.products.attributes.attribute.name         Attribute name.
     * @apiSuccess {String}    response.products.attributes.attribute.text         Attribute text.
     *
     * @apiSuccess {Array[]}   response.total_order_price                  The array with a list of prices.
     * @apiSuccess {Number}    response.total_order_price.total_discount     The amount of the discount for the order.
     * @apiSuccess {Number}    response.total_order_price.total_price        Sum of product's prices.
     * @apiSuccess {Number}    response.total_order_price.shipping_price     Cost of the shipping.
     * @apiSuccess {Number}    response.total_order_price.total              Total order sum.
     * @apiSuccess {String}    response.total_order_price.currency_code      Currency of the order.
     *
     * @apiSuccess {Bool}      status                             Response status.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *      "response": {
     *              "products": [{
     *                  "image" : "http://opencart/image/catalog/demo/htc_touch_hd_1.jpg",
     *                  "name" : "HTC Touch HD",
     *                  "model" : "Product 1",
     *                  "quantity" : 3,
     *                  "price" : 100.00,
     *                  "product_id" : 90,
     *                  "options": [
     *                      {
     *                          "option_id": "11",
     *                          "option_value_id": "46",
     *                          "option_value_name": "Small",
     *                          "option_name": "Size"
     *                      },
     *                      {
     *                          "option_id": "11",
     *                          "option_value_id": "47",
     *                          "option_value_name": "Medium",
     *                          "option_name": "Size"
     *                      },
     *                      {
     *                          "option_id": "11",
     *                          "option_value_id": "48",
     *                          "option_value_name": "Large",
     *                          "option_name": "Size"
     *                      }
     *                  ],
     *                  "attributes": [
     *                      {
     *                          "attribute_group_id": "4",
     *                          "name": "Технические",
     *                          "attribute": [
     *                              {
     *                                  "attribute_id": "5",
     *                                  "name": "Аккумулятор русская версия",
     *                                  "text": "5555 часиков"
     *                              }
     *                          ]
     *                      }
     *                  ]
     *              },
     *              {
     *                  "image" : "http://opencart/image/catalog/demo/iphone_1.jpg",
     *                  "name" : "iPhone",
     *                  "model" : "Product 11",
     *                  "quantity" : 1,
     *                  "price" : 500.00,
     *                  "product_id" : 97,
     *                  "options" : []
     *               }
     *            ],
     *            "total_order_price":
     *              {
     *                   "total_discount": 0,
     *                   "total_price": 2250,
     *                     "currency_code": "RUB",
     *                   "shipping_price": 35,
     *                   "total": 2285
     *               }
     *
     *         },
     *      "status": true,
     *      "version": 1.0
     * }
     *
     *
     * @apiErrorExample Error-Response: {
     *          "error": "Can not found any products in order with id = 10",
     *          "version": 1.0,
     *          "Status" : false
     *     }
     *
     */
    public function orderproducts()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');
        if (isset($_REQUEST['order_id']) && $_REQUEST['order_id'] != '') {
            $id = $_REQUEST['order_id'];

            $error = $this->valid();
            if ($error != null) {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
                return;
            }

            $this->load->model('extension/module/apimodule');
            $products = $this->model_extension_module_apimodule->getOrderProducts($id);



            $this->load->model('catalog/product');

            if (count($products)) {
                $data = [];
                $total_discount_sum = 0;
                $a = 0;
                $this->load->model('tool/image');
                for ($i = 0; $i < count($products); $i++) {
                    if (!empty($products[$i]['image'])) {
                        $image = $this->model_tool_image->resize($products[$i]['image'], 200, 200);
                        $product['image'] = !empty($image) ? $image : "";
                    }
                    if (!empty($products[$i]['name'])) {
                        $product['name'] = strip_tags(htmlspecialchars_decode($products[$i]['name']));
                    }
                    if (!empty($products[$i]['model'])) {
                        $product['model'] = $products[$i]['model'];
                    }
                    if (!empty($products[$i]['quantity'])) {
                        $product['quantity'] = $products[$i]['quantity'];
                    }
                    if (!is_null($products[$i]['price'])) {
                        $currency = $this->model_extension_module_apimodule->getUserCurrency();
                        if (empty($currency)) {
                            $currency = $this->model_extension_module_apimodule->getDefaultCurrency();
                        }
                        $product['price'] = $this->calculatePriceProduct($products[$i]['price'], $products[$i]['tax_class_id'], $currency);
                    }
                    $product['product_id'] = $products[$i]['product_id'];

                    $discount_price = null;
                    if (!is_null($products[$i]['discount'])) {
                        $discount_price = $products[$i]['discount'];
                    }
                    if (!is_null($products[$i]['special'])) {
                        $discount_price = $products[$i]['special'];
                    }
                    if (!is_null($discount_price)) {
                        $product['discount_price'] = $discount_price;
                        $discount = $products[$i]['price'] - $discount_price;
                        $product['discount'] = sprintf('%.2F%%', $discount * 100 / $products[$i]['price']);
                        $total_discount_sum += $discount * $products[$i]['quantity'];
                    }

                    $a += ($products[$i]['price'] * $products[$i]['quantity']);
                    $shipping_price = $products[$i]['value'];

                    $product_options = $this->model_extension_module_apimodule->getProductOptionsByID($_REQUEST['order_id']);

                    $attributes = $this->model_catalog_product->getProductAttributes($products[$i]['product_id']);
                    $product['options'] = $product_options;
                    $product['attributes'] = $attributes;

                    $data['products'][] = $product;
                }

                $data['total_order_price'] = [
                    'total_discount' => sprintf('%.2F', round($total_discount_sum, 2, PHP_ROUND_HALF_EVEN)),
                    'total_price' => sprintf('%.2F', round($a, 2, PHP_ROUND_HALF_EVEN)),
                    'shipping_price' => sprintf('%.2F', round($shipping_price, 2, PHP_ROUND_HALF_EVEN)),
                    'total' => sprintf('%.2F', round($a + $shipping_price, 2, PHP_ROUND_HALF_EVEN)),
                    'currency_code' => $products[0]['currency_code']
                ];

                $this->response->addHeader('Content-Type: application/json; charset=utf-8');
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'response' => $data, 'status' => true]));
            } else {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'Can not found any products in order with id = ' . $id, 'status' => false]));
            }
        } else {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'You have not specified ID', 'status' => false]));
        }
    }

    /**
     * @api {post} index.php?route=module/apimodule/delivery  Change shipping method
     * @apiName changeOrderDelivery
     * @apiDescription Changes to the delivery method
     * @apiGroup Order
     *
     * @apiParam {Token}  token             Your unique token.
     * @apiParam {Number} order_id          Unique order ID.
     * @apiParam {String} address           New shipping address.
     * @apiParam {String} city              New shipping city.
     *
     * @apiSuccess {Number}  version  Current API version.
     * @apiSuccess {Boolean} response Status of change address.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *         "status": true,
     *         "version": 1.0
     *    }
     * @apiErrorExample Error-Response:  {
     *       "error": "Can not change address",
     *       "version": 1.0,
     *       "Status" : false
     *     }
     *
     */
    public function delivery()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');
        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
            return;
        }

        if (isset($_REQUEST['address']) && $_REQUEST['address'] != '' && isset($_REQUEST['order_id'])) {
            $address = $_REQUEST['address'];
            $order_id = $_REQUEST['order_id'];
            if (isset($_REQUEST['city']) && $_REQUEST['city'] != '') {
                $city = $_REQUEST['city'];
            } else {
                $city = false;
            }

            $this->load->model('extension/module/apimodule');
            $data = $this->model_extension_module_apimodule->ChangeOrderDelivery($address, $city, $order_id);
            if ($data) {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'status' => true]));
            } else {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'Can not change address', 'status' => false]));
            }
        } else {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'Missing some params', 'status' => false]));
        }
    }

    /**
     * @api {get} index.php?route=module/apimodule/changestatus  changeStatus
     * @apiName changeStatus
     * @apiGroup All
     *
     * @apiParam {String} comment New comment for order status.
     * @apiParam {Number} order_id unique order ID.
     * @apiParam {Number} status_id unique status ID.
     * @apiParam {Token} token your unique token.
     * @apiParam {Boolean} inform status of the informing client.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {String} name Name of the new status.
     * @apiSuccess {String} date_added Date of adding status.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *          "response":
     *              {
     *                  "name" : "Сделка завершена",
     *                  "date_added" : "2016-12-27 12:01:51"
     *              },
     *          "status": true,
     *          "version": 1.0
     *   }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error" : "Missing some params",
     *       "version": 1.0,
     *       "Status" : false
     *     }
     *
     */
    public function changestatus()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');
        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
            return;
        }

        if (isset($_REQUEST['comment']) && isset($_REQUEST['status_id']) && $_REQUEST['status_id'] != '' && isset($_REQUEST['order_id']) && $_REQUEST['order_id'] != '') {
            $statusID = $_REQUEST['status_id'];
            $orderID = $_REQUEST['order_id'];
            $comment = $_REQUEST['comment'];
            $inform = $_REQUEST['inform'];
            $this->load->model('extension/module/apimodule');
            //$this->model_extension_module_apimodule->addOrderHistory($orderID, $statusID);
            $data = $this->model_extension_module_apimodule->AddComment($orderID, $statusID, $comment, $inform);

            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'response' => $data, 'status' => true]));
        } else {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'Missing some params', 'status' => false]));
        }
        return;
    }

    /**
     * @api {post} index.php?route=module/apimodule/login  User authorization
     * @apiName login
     * @apiDescription uthorizing a new and existing user
     * @apiGroup Authorization
     *
     * @apiParam {String} username                                User unique username.
     * @apiParam {String} password                                User's  password.
     * @apiParam {String} device_token                            User's device's token for firebase notifications.
     * @apiParam {String} os_type                                 Type of the user's device's OS android or ios.
     * @apiParam {String} device_name                             Name of the user's device.
     *
     * @apiSuccess {Array[]}   response                           Array with content response.
     * @apiSuccess {Number}    version                            Current API version.
     * @apiSuccess {Bool}      status                             Response status.
     *
     * @apiSuccess {String}    response.token                     Token.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *       "version": 1.0,
     *       "response": {
     *          "token": "e9cf23a55429aa79c3c1651fe698ed7b",
     *       }
     *       "status": true
     *   }
     *
     * @apiErrorExample Error-Response: {
     *       "error": "Incorrect username or password",
     *       "version": 1.0,
     *       "Status" : false
     *     }
     *
     */
    public function login()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $this->load->model('extension/module/apimodule');

        if (!isset($this->request->post['username']) || !isset($this->request->post['password'])) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'You have not specified a user name or password.', 'status' => false]));
            return;
        } else {
            $user = $this->model_extension_module_apimodule->checkLogin($this->request->post['username'], $this->request->post['password']);
            if (!isset($user['user_id'])) {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'Incorrect username or password', 'status' => false]));
                return;
            }
        }

        $token = $this->model_extension_module_apimodule->getUserToken($user['user_id']);
        if (!isset($token['token'])) {
            $token = md5(mt_rand());
            $this->model_extension_module_apimodule->setUserToken($user['user_id'], $token);
        }
        $token = $this->model_extension_module_apimodule->getUserToken($user['user_id']);

        $this->response->setOutput(
            json_encode(['version' => $this->API_VERSION, 'response' => ['token' => $token['token']], 'status' => true])
        );
    }

    /**
     * @api {post} index.php?route=module/apimodule/deletedevicetoken  Delete user device token
     * @apiName deleteUserDeviceToken
     * @apiDescription Deletes the old user's token
     * @apiGroup Token
     *
     * @apiParam   {String}    old_token                    User's device's token for firebase notifications.
     *
     * @apiSuccess {Array[]}   response                     Array with content response
     * @apiSuccess {Number}    response.version             Current API version.
     * @apiSuccess {Boolean}   response.status              true.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *       "response":  {
     *          "status": true,
     *          "version": 1.0
     *       }
     *   }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error": "Missing some params",
     *       "version": 1.0,
     *       "Status" : false
     *     }
     *
     */
    // public function deletedevicetoken()
    // {
    //     header("Access-Control-Allow-Origin: *");
    //     $this->response->addHeader('Content-Type: application/json');
    //     if (isset($_REQUEST['old_token'])) {
    //         $this->load->model('extension/module/apimodule');

    //         $deleted = $this->model_extension_module_apimodule->findUserToken($_REQUEST['old_token']);
    //         if (count($deleted) != 0) {
    //             $this->model_extension_module_apimodule->deleteUserDeviceToken($_REQUEST['old_token']);
    //             $this->response->setOutput(json_encode(['response' => ['version' => $this->API_VERSION, 'status' => true]]));
    //         } else {
    //             $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'Can not find your token', 'status' => false]));
    //         }
    //     } else {
    //         $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'Missing some params', 'status' => false]));
    //     }
    // }

    /**
     * @api {post} index.php?route=module/apimodule/updatedevicetoken  Update user device token
     * @apiName updateUserDeviceToken
     * @apiDescription Updating the old user's token to a new one
     * @apiGroup Token
     *
     * @apiParam {String} new_token                         User's device's new token for firebase notifications.
     * @apiParam {String} old_token                         User's device's old token for firebase notifications.
     *
     * @apiSuccess {Array[]}   response                     Array with content response
     * @apiSuccess {Number}    response.version             Current API version.
     * @apiSuccess {Boolean}   response.status              true.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *       "response": {
     *          "status": true,
     *          "version": 1.0
     *       }
     *   }
     *
     * @apiErrorExample Error-Response: {
     *       "error": "Missing some params",
     *       "version": 1.0,
     *       "Status" : false
     *     }
     *
     */
    // public function updatedevicetoken()
    // {
    //     header("Access-Control-Allow-Origin: *");
    //     $this->response->addHeader('Content-Type: application/json');
    //     if (isset($_REQUEST['old_token']) && isset($_REQUEST['new_token'])) {
    //         $this->load->model('extension/module/apimodule');
    //         $updated = $this->model_extension_module_apimodule->updateUserDeviceToken($_REQUEST['old_token'], $_REQUEST['new_token']);
    //         if (count($updated) != 0) {
    //             $this->response->setOutput(json_encode(['response' => ['version' => $this->API_VERSION, 'status' => true]]));
    //         } else {
    //             $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
    //                 'error' => 'Can not find your token',
    //                 'status' => false]));
    //         }
    //     } else {
    //         $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'Missing some params', 'status' => false]));
    //     }
    // }

    /**
     * @api {post} index.php?route=module/apimodule/statistic  getDashboardStatistic
     * @apiName          getDashboardStatistic
     * @apiDescription   Get full statistics on the selected filter
     * @apiGroup         Statistic
     *
     * @apiParam   {String} filter Period for filter(day/week/month/year).
     * @apiParam   {Token}  token your unique token.
     *
     * @apiSuccess {Array[]}   response                           Array with content response.
     * @apiSuccess {Number}    version                            Current API version.
     * @apiSuccess {Bool}      status                             Response status.

     * @apiSuccess {Array[]}   response.xAxis               Period of the selected filter.
     * @apiSuccess {Array[]}   response.clients             Clients for the selected period.
     * @apiSuccess {Array[]}   response.orders              Orders for the selected period.
     * @apiSuccess {String}    response.currency_code       Default currency of the shop.
     * @apiSuccess {Number}    response.total_sales         Sum of sales of the shop.
     * @apiSuccess {String}    response.sale_year_total     Sum of sales of the current year.
     * @apiSuccess {String}    response.orders_total        Total orders of the shop.
     * @apiSuccess {String}    response.clients_total       Total clients of the shop.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *           "response": {
     *               "xAxis": [
     *                  1,
     *                  2,
     *                  3,
     *                  4,
     *                  5,
     *                  6,
     *                  7
     *              ],
     *              "clients": [
     *                  0,
     *                  0,
     *                  0,
     *                  0,
     *                  0,
     *                  0,
     *                  0
     *              ],
     *              "orders": [
     *                  1,
     *                  0,
     *                  0,
     *                  0,
     *                  0,
     *                  0,
     *                  0
     *              ],
     *              "total_sales": "1920.00",
     *              "sale_year_total": "305.00",
     *              "currency_code": "UAH",
     *              "orders_total": "4",
     *              "clients_total": "3"
     *           },
     *           "status": true,
     *           "version": 1.0
     *  }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error": "Unknown filter set",
     *       "version": 1.0,
     *       "Status" : false
     *     }
     *
     */
    public function statistic()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');
        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
            return;
        }
        $this->load->model('extension/module/apimodule');

        if (isset($_REQUEST['filter']) && $_REQUEST['filter'] != '') {
            $clients = $this->model_extension_module_apimodule->getTotalCustomers(['filter' => $_REQUEST['filter']]);
            $orders = $this->model_extension_module_apimodule->getTotalOrders(['filter' => $_REQUEST['filter']]);

            if ($clients === false || $orders === false) {
                $this->response->setOutput(json_encode(['error' => 'Unknown filter set', 'status' => false]));
                return;
            } else {
                $clients_for_time = [];
                $orders_for_time = [];
                if ($_REQUEST['filter'] == 'month') {
                    $hours = range(1, 30);
                    for ($i = 1; $i <= 30; $i++) {
                        $b = 0;
                        $o = 0;
                        foreach ($clients as $value) {
                            $day = strtotime($value['date_added']);
                            $day = date("d", $day);

                            if ($day == $i) {
                                $b = $b + 1;
                            }
                        }
                        $clients_for_time[] = $b;

                        foreach ($orders as $value) {
                            $day = strtotime($value['date_added']);
                            $day = date("d", $day);

                            if ($day == $i) {
                                $o = $o + 1;
                            }
                        }
                        $orders_for_time[] = $o;
                    }
                } elseif ($_REQUEST['filter'] == 'day') {
                    $hours = range(0, 23);

                    for ($i = 0; $i <= 23; $i++) {
                        $b = 0;
                        $o = 0;
                        foreach ($clients as $value) {
                            $hour = strtotime($value['date_added']);
                            $hour = date("H", $hour);

                            if ($hour == $i) {
                                $b = $b + 1;
                            }
                        }
                        $clients_for_time[] = $b;

                        foreach ($orders as $value) {
                            $day = strtotime($value['date_added']);
                            $day = date("H", $day);

                            if ($day == $i) {
                                $o = $o + 1;
                            }
                        }
                        $orders_for_time[] = $o;
                    }
                } elseif ($_REQUEST['filter'] == 'week') {
                    $start = new DateTime(date('Y-m-d'));
                    $start->modify('-6 day');
                    $end = new DateTime(date('Y-m-d'));
                    $end->modify('+1 day');

                    $interval = new DateInterval('P1D');
                    $daterange = new DatePeriod($start, $interval, $end);

                    foreach ($daterange as $date) {
                        $countClients = 0;
                        $countOrders = 0;
                        $hours[] = (int)$date->format("d");
                        $currentDay = $date->format("d");

                        foreach ($clients as $value) {
                            $date = strtotime($value['date_added']);
                            $dateOrder = date("d", $date);
                            if ($dateOrder == $currentDay) {
                                $countClients++;
                            }
                        }
                        $clients_for_time[] = $countClients;

                        foreach ($orders as $val) {
                            $day = strtotime($val['date_added']);
                            $day = date("d", $day);
                            if ($day == $currentDay) {
                                $countOrders++;
                            }
                        }
                        $orders_for_time[] = $countOrders;
                    }

                } elseif ($_REQUEST['filter'] == 'year') {
                    $hours = range(1, 12);

                    for ($i = 1; $i <= 12; $i++) {
                        $b = 0;
                        $o = 0;
                        foreach ($clients as $value) {
                            $date = strtotime($value['date_added']);

                            $f = date("m", $date);

                            if ($f == $i) {
                                $b = $b + 1;
                            }
                        }
                        $clients_for_time[] = $b;

                        foreach ($orders as $val) {
                            $day = strtotime($val['date_added']);
                            $day = date("m", $day);

                            if ($day == $i) {
                                $o = $o + 1;
                            }
                        }
                        $orders_for_time[] = $o;
                    }
                }

                $data['xAxis'] = $hours;
                $data['clients'] = $clients_for_time;
                $data['orders'] = $orders_for_time;
            }

            $sale_total = $this->model_extension_module_apimodule->getTotalSales();

            // $data['total_sales'] = number_format($sale_total, 2, '.', '');
            $currency = $this->model_extension_module_apimodule->getUserCurrency();
            if (empty($currency)) {
                $currency = $this->model_extension_module_apimodule->getDefaultCurrency();
            }
            $data['currency_code'] = $currency;
            $data['total_sales'] = $this->calculatePrice($sale_total, $currency);

            $sale_year_total = $this->model_extension_module_apimodule->getTotalSales(['this_year' => true]);

            $data['sale_year_total'] = number_format($sale_year_total, 2, '.', '');
            $orders_total = $this->model_extension_module_apimodule->getTotalOrders();
            $data['orders_total'] = $orders_total[0]['COUNT(*)'];
            $clients_total = $this->model_extension_module_apimodule->getTotalCustomers();
            $data['clients_total'] = $clients_total[0]['COUNT(*)'];
            //$data['currency_code'] = $this->model_extension_module_apimodule->getDefaultCurrency();

            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'response' => $data, 'status' => true]));
        } else {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'Missing some params', 'status' => false]));
        }
    }

    private function valid()
    {
        if (!isset($_REQUEST['token']) || $_REQUEST['token'] == '') {
            $error = 'You need to be logged!';
        } else {
            $this->load->model('extension/module/apimodule');
            $tokens = $this->model_extension_module_apimodule->getTokens();
            if (count($tokens) > 0) {
                foreach ($tokens as $token) {
                    if ($_REQUEST['token'] == $token['token']) {
                        return null;
                    } else {
                        $error = 'Your token is no longer relevant!';
                    }
                }
            } else {
                $error = 'You need to be logged!';
            }
        }
        return $error;
    }

    /**
     * @api {post} index.php?route=module/apimodule/clients  Get list of the all clients
     * @apiName GetClients
     * @apiDescription Get a list of all clients for a specific filter
     * @apiGroup Client
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} page number of the page.
     * @apiParam {Number} limit limit of the orders for the page.
     * @apiParam {String} fio full name of the client.
     * @apiParam {String} sort param for sorting clients(sum/quantity/date_added).
     *
     * @apiSuccess {Array[]}   response                           Array with content response.
     * @apiSuccess {Number}    version                            Current API version.
     * @apiSuccess {Bool}      status                             Response status.

     * @apiSuccess {String} response.client_id                    ID of the client.
     * @apiSuccess {String} response.fio                          Client's FIO.
     * @apiSuccess {Number} response.total                        Total sum of client's orders.
     * @apiSuccess {String} response.currency_code                Default currency of the shop.
     * @apiSuccess {String} response.quantity                     Total quantity of client's orders.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *     "Response" {
     *     "clients"
     *      {
     *          {
     *              "client_id" : "88",
     *              "fio" : "Anton Kiselev",
     *              "total" : "1006.00",
     *              "currency_code": "UAH",
     *              "quantity" : "5"
     *          },
     *          {
     *              "client_id" : "10",
     *              "fio" : "Vlad Kochergin",
     *              "currency_code": "UAH",
     *              "total" : "555.00",
     *              "quantity" : "1"
     *          }
     *      }
     *    },
     *    "Status" : true,
     *    "version": 1.0
     * }
     * @apiErrorExample Error-Response: {
     *      "Error" : "Not one client found",
     *      "version": 1.0,
     *      "Status" : false
     * }
     *
     *
     */
    public function clients()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');
        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
            return;
        }

        if (isset($_REQUEST['page']) && (int)$_REQUEST['page'] != 0 && (int)$_REQUEST['limit'] != 0 && isset($_REQUEST['limit'])) {
            $page = ($_REQUEST['page'] - 1) * $_REQUEST['limit'];
            $limit = $_REQUEST['limit'];
        } else {
            $page = 0;
            $limit = 20;
        }
        if (isset($_REQUEST['sort']) && $_REQUEST['sort'] != '') {
            $order = $_REQUEST['sort'];
        } else {
            $order = 'date_added';
        }
        if (isset($_REQUEST['fio']) && $_REQUEST['fio'] != '') {
            $fio = $_REQUEST['fio'];
        } else {
            $fio = '';
        }

        $this->load->model('extension/module/apimodule');

        $clients = $this->model_extension_module_apimodule->getClients(['page' => $page, 'limit' => $limit, 'order' => $order, 'fio' => $fio]);
        $response = [];
        if (count($clients) > 0) {
            //$currency = $this->model_extension_module_apimodule->getDefaultCurrency();
            $data = [];
            foreach ($clients as $client) {

                $data['client_id'] = $client['customer_id'];
                if (isset($client['firstname']) && $client['firstname'] != '') {
                    $data['fio'] = $client['firstname'] . ' ' . $client['lastname'];
                } elseif (isset($client['lastname']) && $client['lastname'] != '') {
                    $data['fio'] .= ' ' . $client['lastname'];
                }

                $currency = $this->model_extension_module_apimodule->getUserCurrency();
                if (empty($currency)) {
                    $currency = $this->model_extension_module_apimodule->getDefaultCurrency();
                }
                $data['total'] = $this->calculatePrice($client['sum'], $currency);
                $data['quantity'] = $client['quantity'];
                $data['currency_code'] = $currency;
                $clients_to_response[] = $data;
            }
            $response['clients'] = $clients_to_response;
        } else {
            $response['clients'] = [];
        }
        $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'response' => $response, 'status' => true]));
        return;
    }

    /**
     * @api {post} index.php?route=module/apimodule/clientinfo  Get detailed customer information
     * @apiName getClientInfo
     * @apiDescription Get detailed customer information
     * @apiGroup Client
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} client_id unique client ID.
     *
     * @apiSuccess {Array[]}   response                           Array with content response.
     * @apiSuccess {Number}    version                            Current API version.
     * @apiSuccess {Bool}      status                             Response status.
     *
     *
     * @apiSuccess {String} response.client_id                    ID of the client.
     * @apiSuccess {String} response.fio                          Client's FIO.
     * @apiSuccess {String} response.total                        Total sum of client's orders.
     * @apiSuccess {String} response.quantity                     Total quantity of client's orders.
     * @apiSuccess {String} response.email                        Client's email.
     * @apiSuccess {String} response.telephone                    Client's telephone.
     * @apiSuccess {String} response.currency_code                Default currency of the shop.
     * @apiSuccess {String} response.cancelled                    Total quantity of cancelled orders.
     * @apiSuccess {String} response.completed                    Total quantity of completed orders.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *     "Response" {
     *         "client_id" : "88",
     *         "fio" : "Anton Kiselev",
     *         "total" : "1006.00",
     *         "quantity" : "5",
     *         "cancelled" : "1",
     *         "completed" : "2",
     *         "currency_code": "UAH",
     *         "email" : "client@mail.ru",
     *         "telephone" : "13456789"
     *   },
     *   "Status" : true,
     *   "version": 1.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Not one client found",
     *      "version": 1.0,
     *      "Status" : false
     * }
     *
     *
     */
    public function clientinfo()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');
        if (isset($_REQUEST['client_id']) && $_REQUEST['client_id'] != '') {
            $id = $_REQUEST['client_id'];

            $error = $this->valid();
            if ($error != null) {

                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
                return;
            }

            $this->load->model('extension/module/apimodule');
            $client = $this->model_extension_module_apimodule->getClientInfo($id);
            $currency_code = $this->model_extension_module_apimodule->getDefaultCurrency();
            if (count($client) > 0) {
                $data['client_id'] = $client['customer_id'];

                if (isset($client['firstname']) && $client['firstname'] != '') {
                    $data['fio'] = $client['firstname'] . ' ' . $client['lastname'];
                } elseif (isset($client['lastname']) && $client['lastname'] != '') {
                    $data['fio'] .= ' ' . $client['lastname'];
                }
                if (isset($client['email']) && $client['email'] != '') {
                    $data['email'] = $client['email'];
                }
                if (isset($client['telephone']) && $client['telephone'] != '') {
                    $data['telephone'] = $client['telephone'];
                }

                // $data['total'] = number_format($client['sum'], 2, '.', '');

                $data['total'] = $this->currency->format($client['sum'], $currency_code);

                $data['quantity'] = $client['quantity'];
                $data['currency_code'] = $currency_code;
                $data['completed'] = $client['completed'];
                $data['cancelled'] = $client['cancelled'];

                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'response' => $data, 'status' => true]));
            } else {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'Can not found client with id = ' . $id, 'status' => false]));
            }
        } else {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'You have not specified ID', 'status' => false]));
        }
    }

    /**
     * @api {post} index.php?route=module/apimodule/clientorders  Get the orders of the customer
     * @apiName getClientOrders
     * @apiDescription Get a list of sales orders by id
     * @apiGroup Client
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} client_id unique client ID.
     * @apiParam {String} sort param for sorting orders(total/date_added/completed/cancelled).
     *
     * @apiSuccess {Array[]}   response                           Array with content response.
     * @apiSuccess {Number}    version                            Current API version.
     * @apiSuccess {Bool}      status                             Response status.
     *
     * @apiSuccess {Array[]} response.orders                      Array of sales orders.
     * @apiSuccess {String}  response.orders.order_id             ID of the order.
     * @apiSuccess {String}  response.orders.order_number         Number of the order.
     * @apiSuccess {String}  response.orders.status               Status of the order.
     * @apiSuccess {String}  response.orders.currency_code        Default currency of the shop.
     * @apiSuccess {String}  response.orders.total                Total sum of the order.
     * @apiSuccess {String}  response.orders.date_added           Date added of the order.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *     "Response" {
     *       "orders":
     *          {
     *             "order_id" : "1",
     *             "order_number" : "1",
     *             "status" : "Сделка завершена",
     *             "currency_code": "UAH",
     *             "total" : "106.00",
     *             "date_added" : "2016-12-09 16:17:02"
     *          },
     *          {
     *             "order_id" : "2",
     *             "currency_code": "UAH",
     *             "order_number" : "2",
     *             "status" : "В обработке",
     *             "total" : "506.00",
     *             "date_added" : "2016-10-19 16:00:00"
     *          }
     *    },
     *    "Status" : true,
     *    "version": 1.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "You have not specified ID",
     *      "version": 1.0,
     *      "Status" : false
     * }
     *
     *
     */
    public function clientorders()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');
        if (isset($_REQUEST['client_id']) && $_REQUEST['client_id'] != '') {
            $id = $_REQUEST['client_id'];

            $error = $this->valid();
            if ($error != null) {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
                return;
            }
            if (!empty($_REQUEST['sort']) && in_array($_REQUEST['sort'], ['total', 'completed', 'cancelled'])) {
                $sort = $_REQUEST['sort'];
            } else {
                $sort = 'date_added';
            }

            $this->load->model('extension/module/apimodule');
            $orders = $this->model_extension_module_apimodule->getClientOrders($id, $sort);
            $currency_code = $this->model_extension_module_apimodule->getDefaultCurrency();



            if (count($orders) > 0) {
                foreach ($orders as $order) {
                    $data['order_id'] = $order['order_id'];
                    $data['order_number'] = $order['order_id'];
                    // $data['total'] = number_format($order['total'], 2, '.', '');
                    $data['total'] = $this->currency->format($order['total'], $currency_code, $order['currency_value']);


                    $data['date_added'] = $order['date_added'];
                    $data['currency_code'] = $currency_code;
                    if (isset($order['name'])) {
                        $data['status'] = $order['name'];
                    } else {
                        $data['status'] = '';
                    }

                    $to_response[] = $data;
                }
                $response['orders'] = $to_response;

                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'response' => $response, 'status' => true]));
            } else {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'response' => ['orders' => []], 'status' => true]));
            }
        } else {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'You have not specified ID', 'status' => false]));
        }
    }

    /**
     * @api {post} index.php?route=module/apimodule/products  Products List
     * @apiName getProductsList
     * @apiDescription Get a list of products
     * @apiGroup Product
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} page number of the page.
     * @apiParam {Number} limit limit of the orders for the page.
     * @apiParam {String} name name of the product for search.
     *
     *
     * @apiSuccess {Array[]}   response                              Array with content response.
     * @apiSuccess {Number}    version                               Current API version.
     * @apiSuccess {Bool}      status                                Response status.
     *
     * @apiSuccess {Array[]}   response.products                     Array of products.
     * @apiSuccess {String}    response.products.product_id          ID of the product.
     * @apiSuccess {String}    response.products.model               Model of the product.
     * @apiSuccess {String}    response.products.name                Name of the product.
     * @apiSuccess {String}    response.products.currency_code       Default currency of the shop.
     * @apiSuccess {String}    response.products.price               Price of the product.
     * @apiSuccess {String}    response.products.quantity            Actual quantity of the product.
     * @apiSuccess {String}    response.products.image               Url to the product image.
     * @apiSuccess {String}    response.products.category            The category to which the product belongs.
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *     "Response": {
     *      "products": {
     *           {
     *             "product_id" : "1",
     *             "model" : "Black",
     *             "name" : "HTC Touch HD",
     *             "price" : "100.00",
     *             "currency_code": "UAH",
     *             "quantity" : "83",
     *             "image" : "http://site-url/image/catalog/demo/htc_touch_hd_1.jpg",
     *             "category": "Cameras"
     *           },
     *           {
     *             "product_id" : "2",
     *             "model" : "White",
     *             "name" : "iPhone",
     *             "price" : "300.00",
     *             "currency_code": "UAH",
     *             "quantity" : "30",
     *             "image" : "http://site-url/image/catalog/demo/iphone_1.jpg"
     *             "category": ""
     *           }
     *      }
     *   },
     *   "Status" : true,
     *   "version": 1.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Not one product not found",
     *      "version": 1.0,
     *      "Status" : false
     * }
     *
     *
     */
    public function products()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');
        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
            return;
        }
        if (isset($_REQUEST['page']) && (int)$_REQUEST['page'] != 0 && (int)$_REQUEST['limit'] != 0 && isset($_REQUEST['limit'])) {
            $page = ($_REQUEST['page'] - 1) * $_REQUEST['limit'];
            $limit = $_REQUEST['limit'];
        } else {
            $page = 0;
            $limit = 10;
        }
        if (isset($_REQUEST['name']) && $_REQUEST['name'] != '') {
            $name = str_replace('&', '&amp;', $_REQUEST['name']);
        } else {
            $name = '';
        }

        if (isset($_REQUEST['store_id']) && $_REQUEST['store_id'] != '') {
            $store_id = $_REQUEST['store_id'];
        } else {
            $store_id = '';
        }

        $to_response = [];
        $this->load->model('extension/module/apimodule');
        $products = $this->model_extension_module_apimodule->getProductsList($page, $limit, $name, $store_id);

        foreach ($products as $product) {
            $data['product_id'] = $product['product_id'];
            $data['model'] = $product['model'];
            $data['quantity'] = $product['quantity'];
            $this->load->model('tool/image');
            if (!empty($product['image'])) {
                $resized_omage = $this->model_tool_image->resize($product['image'], 200, 200);
                $data['image'] = $resized_omage;
            } else {
                $data['image'] = '';
            }
            $data['price'] = number_format($product['price'], 2, '.', '');

            $currency = $this->model_extension_module_apimodule->getUserCurrency();
            if (empty($currency)) {
                $currency = $this->model_extension_module_apimodule->getDefaultCurrency();
            }
            //$data['price'] = $this->calculatePriceProduct($product['price'], $product['tax_class_id'], $currency);
            $data['name'] = strip_tags(htmlspecialchars_decode($product['name']));
            $data['currency_code'] = $this->model_extension_module_apimodule->getDefaultCurrency();
            $product_categories = $this->model_extension_module_apimodule->getProductCategoriesMain($product['product_id']);
            $categories = [];
            foreach ($product_categories as $pc) {
                $categories[] = $pc['name'];
            }
            $data['category'] = htmlspecialchars_decode(implode(', ', $categories));
            $to_response[] = $data;
        }
        $response['products'] = $to_response;

        $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'response' => $response, 'status' => true]));
    }

    /**
     * @api {post} index.php?route=module/apimodule/productinfo  Get product info
     * @apiName getProductInfo
     * @apiDescription Ger full product info by product_id
     * @apiGroup Product
     *
     * @apiParam {Token} token                                  Your unique token.
     * @apiParam {Number} product_id                            Unique product ID.
     *
     * @apiSuccess {Array[]}  response                          Array with content response.
     * @apiSuccess {Number}   version                           Current API version.
     * @apiSuccess {Bool}     status                            Response status.
     *
     *
     * @apiSuccess {String}   response.product_id               ID of the product.
     * @apiSuccess {String}   response.model                    Model of the product.
     * @apiSuccess {String}   response.quantity                 Actual quantity of the product.
     * @apiSuccess {String}   response.sku                      Actual SKU of the product.
     * @apiSuccess {String}   response.stock_status_name        Stock status name of the product.
     * @apiSuccess {String}   response.name                     Name of the product.
     * @apiSuccess {String}   response.description              Detail description of the product.
     * @apiSuccess {String}   response.currency_code            Default currency of the shop.
     * @apiSuccess {String}   response.price                    Price of the product.
     * @apiSuccess {String}   response.status_name              Status name (Enabled / Disabled)
     *
     * @apiSuccess {Array[]}  response.images                   Array of the images of the product.
     * @apiSuccess {String}   response.images.image             Image Link.
     * @apiSuccess {Number}   response.images.image_id          Image id. If the image is set as the main thing then its id (-1)
     *
     * @apiSuccess {Array[]}  response.stock_statuses           Array of the stock statuses of the product.
     * @apiSuccess {String}   response.stock_statuses.name      Stock status name.
     * @apiSuccess {String}   response.stock_statuses.status_id Stock status id.
     *
     * @apiSuccess {Array[]}  response.categories               Array of the categories of the product.
     * @apiSuccess {String}   response.categories.name          Category name.
     * @apiSuccess {String}   response.categories.category_id   Category id.
     *
     * @apiSuccess {Array[]}  response.options                  Array of the options of the product.
     * @apiSuccess {String}   response.options.option_id        Option id.
     * @apiSuccess {String}   response.options.option_name      Option name.
     * @apiSuccess {String}   response.options.option_value_id  Option value id.
     * @apiSuccess {String}   response.options.option_value_name Option value name.
     * @apiSuccess {String}   response.options.language_id      Language id of options and option values.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *     "Response": {
     *       "product_id" : "1",
     *       "model" : "Black",
     *       "name" : "HTC Touch HD",
     *       "price" : "100.00",
     *       "sku": "7798-70",
     *       "status": "Enabled",
     *       "stock_status_name": "In Stock",
     *       "currency_code": "UAH",
     *       "status_name": "Enabled",
     *       "quantity" : "83",
     *       "description" : "Revolutionary multi-touch interface.↵ iPod touch features the same multi-touch screen technology as iPhone.",
     *       "images" : [
     *          {
     *               "image": "http://opencart3000.pixy.pro/image/cache/catalog/demo/htc_touch_hd_1-600x800.jpg",
     *               "image_id": -1
     *           },
     *           {
     *               "image": "http://opencart3000.pixy.pro/image/cache/catalog/demo/htc_touch_hd_3-600x800.jpg",
     *               "image_id": 2034
     *           }
     *       ],
     *       "stock_statuses": [
     *          {
     *              "status_id":"7",
     *              "name":"In Stock"
     *          },
     *          {
     *              "status_id":"9",
     *              "name":"Out Of Stock"
     *          }
     *       ],
     *       "categories": [
     *          {
     *              "name":"Cameras",
     *              "category_id":"9"
     *          },
     *          {
     *              "name":"Tablets",
     *              "category_id":"25"
     *          }
     *       ],
     *       "options": [
     *          {
     *            "option_id": "5",
     *            "option_value_id": "39",
     *            "option_value_name": "Красный",
     *            "language_id": "1",
     *            "option_name": "Список"
     *          },
     *          {
     *            "option_id": "5",
     *            "option_value_id": "42",
     *            "option_value_name": "Желтый",
     *            "language_id": "1",
     *            "option_name": "Список"
     *          },
     *          {
     *            "option_id": "5",
     *            "option_value_id": "40",
     *            "option_value_name": "Синий",
     *            "language_id": "1",
     *            "option_name": "Список"
     *          }
     *        ]
     *   },
     *   "Status" : true,
     *   "version": 1.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Can not found product with id = 10",
     *      "version": 1.0,
     *      "Status" : false
     * }
     *
     *
     */
    public function productinfo()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
            return;
        }
        if (isset($_REQUEST['product_id']) && (int)$_REQUEST['product_id'] != 0) {
            $id = $_REQUEST['product_id'];
            $this->load->model('extension/module/apimodule');
            $product = $this->model_extension_module_apimodule->getProductsByID($id);
            if (count($product) > 0) {
                $response['product_id'] = $product['product_id'];
                $response['stock_statuses'] = $this->model_extension_module_apimodule->getStockStatuses();
                $response['model'] = $product['model'];
                $response['quantity'] = $product['quantity'];
                $response['sku'] = $product['sku'];
                $response['stock_status_name'] = $product['stock_status_name'];
                $response['name'] = strip_tags(htmlspecialchars_decode($product['name']));
                //$response['description'] = $product['description'];
                $response['description'] = html_entity_decode($product['description'], ENT_QUOTES, 'UTF-8');
                $currency = $this->model_extension_module_apimodule->getUserCurrency();
                if (empty($currency)) {
                    $currency = $this->model_extension_module_apimodule->getDefaultCurrency();
                }
                $response['currency_code'] = $currency;
                $response['price'] = $this->calculatePrice($product['price'], $currency);

                // $response['price'] = $this->calculatePriceProduct($product['price'], $product['tax_class_id'], $currency);

                $this->load->model('tool/image');
                $product_img = $this->model_extension_module_apimodule->getProductImages($id);
                $response['images'] = [];
                if (count($product_img['images']) > 0) {
                    $response['images'] = [];

                    foreach ($product_img['images'] as $key => $image) {
                        $product_img['images'][$key]['image'] = $this->model_tool_image->resize($product_img['images'][$key]['image'], 600, 800);

                        $product_img['images'][$key]['image_id'] = (int)$product_img['images'][$key]['product_image_id'];

                        unset($product_img['images'][$key]['product_id'], $product_img['images'][$key]['sort_order'], $product_img['images'][$key]['product_image_id']);
                    }
                    $response['images'] = $product_img['images'];
                } else {
                    $response['images'] = [];
                }
                if ($product['status']) {
                    $response['status_name'] = 'Enabled';
                } else {
                    $response['status_name'] = 'Disabled';
                }

                $product_categories = $this->model_extension_module_apimodule->getProductCategories($id);

                $response['categories'] = $product_categories;

                $option_id = $_GET['option_value'];
                // $product_options = $this->model_extension_module_apimodule->getProductOptionsByID($id);
                $option = $this->model_extension_module_apimodule->getOption($id,$option_id);

                $response['options'] = $option;

                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'response' => $response, 'status' => true]));
            } else {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'Can not found product with id = ' . $_REQUEST['product_id'], 'status' => false]));
            }
        } else {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'You have not specified ID', 'status' => false]));
        }
    }

    /**
     * @api {post} index.php?route=module/apimodule/setQuantity  setQuantity
     * @apiName setQuantity
     * @apiGroup All
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} product_id unique product ID.
     * @apiParam {Number} quantity unique product ID.
     *
     * @apiSuccess {Number} quantity  Updated quantity of the product.
     * @apiSuccess {Number} version  Current API version.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *   "Response":
     *   {
     *       "quantity" : "999"
     *   },
     *   "Status" : true,
     *   "version": 1.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Missing some params",
     *      "version": 1.0,
     *      "Status" : false
     * }
     *
     *
     */
    public function setQuantity()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
            return;
        }
        if (!empty($_REQUEST['quantity']) && !empty($_REQUEST['product_id'])) {
            $this->load->model('extension/module/apimodule');
            $quantity = $this->model_extension_module_apimodule->setProductQuantity($_REQUEST['quantity'], $_REQUEST['product_id']);
            if ($quantity == $_REQUEST['quantity']) {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'response' => ['quantity' => $quantity], 'status' => true]));
            }
        } else {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'Missing some params', 'status' => false]));
        }
    }

    /**
     * @api {post} index.php?route=module/apimodule/updateProduct  Update product
     * @apiName updateProduct
     * @apiDescription Updating the product by its id
     * @apiGroup Product
     *
     * @apiParam {Token}      token                                                                 Your unique token.
     * @apiParam {Number}     product_id                                                            Unique product ID. If you do not send the product id, then a new product will be created
     * @apiParam {Number}     quantity                                                              New quantity for the product.
     * @apiParam {Array[]}    image                                                                 Adding new pictures for the product.
     * @apiParam {Array[]}    categories                                                            New array of product categories.
     * @apiParam {Array[]}    options                                                               New array of product options. The form of POST parameters of the options array is "options[--option id--][] = --option value id--" for each pair of option id and the corresponding option value id.
     * @apiParam {Number}     language_id                                                           New language_id for the product.
     * @apiParam {String}     name                                                                  Name of the product.
     * @apiParam {String}     description                                                           Description of the product.
     * @apiParam {String}     model                                                                 Model of the product.
     * @apiParam {String}     sku                                                                   SKU of the product.
     * @apiParam {Number}     status                                                                Status of the product.
     * @apiParam {String}     price                                                                 Product price.
     * @apiParam {String}     substatus                                                             Stock status id of the product.
     * @apiParam {Array[]}    attributes                                                            Array attributes..
     * @apiParam {String}     attributes.index.attribute_id                                         Attribute id.
     * @apiParam {String}     attributes.index.product_attribute_description.index_desc.text        Product attribute description text. attributes[0][product_attribute_description][0][text]
     *
     *
     * @apiSuccess {Array[]}  response                   Array with content response.
     * @apiSuccess {Number}   version                    Current API version.
     * @apiSuccess {Bool}     status                     Response status.
     *
     * @apiSuccess {String}   response.product_id        Unique product ID.
     *
     * @apiSuccess {Array[]}  response.images            Array images of the product.
     * @apiSuccess {String}   response.images.image             Link image.
     * @apiSuccess {Number}   response.images.image_id          Image id.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *      "version": 0,
     *       "status": true,
     *       "response": {
     *           "product_id": "28",
     *           "images": [
     *               {
     *                   "image": null,
     *                   "image_id": -1
     *               },
     *               {
     *                   "image": "http://opencart3000.pixy.pro/image/cache/catalog/demo/htc_touch_hd_3-600x800.jpg",
     *                   "image_id": 2034
     *               },
     *               {
     *                   "image": "http://opencart3000.pixy.pro/image/cache/catalog/youtube-embed-600x800.png",
     *                   "image_id": 2352
     *               }
     *           ]
     *       }
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Can not found product with id = 10",
     *      "version": 1.0,
     *      "Status" : false
     * }
     *
     *
     */
    public function updateProduct()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'error' => $error,
                'status' => false,
            ]));
            return;
        }

        $images = [];

        if (!empty($_FILES)) {
            foreach ($_FILES['image']['name'] as $key => $name) {
                $tmp_name = $_FILES['image']["tmp_name"][$key];

                if (move_uploaded_file($tmp_name, DIR_IMAGE . 'catalog/' . $name)) {
                    $images[] = 'catalog/' . $name;
                }
            }
        }

        if (isset($_REQUEST['name']) && isset($_REQUEST['categories'])) {

            $data = [];

            if (isset($_REQUEST['language_id'])) {
                $data['language_id'] = $_REQUEST['language_id'];
            } else {
                $data['language_id'] = $this->config->get('config_language_id');
            }
            if (isset($_REQUEST['name'])) {
                $data['product_description'][$data['language_id']]['name'] = $_REQUEST['name'];
            }

            if (isset($_REQUEST['description'])) {
                $data['product_description'][$data['language_id']]['description'] = $_REQUEST['description'];
            }

            if (isset($_REQUEST['price'])) {
                $currency = $this->model_extension_module_apimodule->getUserCurrency();
                if (empty($currency)) {
                    $currency = $this->model_extension_module_apimodule->getDefaultCurrency();
                }
                $this->load->model('localisation/currency');
                $result = $this->model_localisation_currency->getCurrencyByCode($currency);

                if (isset($_REQUEST['product_id'])) {
                    $product = $this->model_extension_module_apimodule->getProductsByID($_REQUEST['product_id']);
                    if (!empty($product)) {
                        if ($_REQUEST['price'] == $this->calculatePriceProduct($product['price'], $product['tax_class_id'], $currency)) {
                            $data['price_old'] = true;
                        }
                    }
                }

                $price = (float)$_REQUEST['price']/(float)$result['value'];
                $data['price'] = $price;
            } else {
                $data['price'] = 0;
            }

            $data['model'] = "";
            if (isset($_REQUEST['model'])) {
                $data['model'] = $_REQUEST['model'];
            }

            $data['sku'] = '';
            if (isset($_REQUEST['sku'])) {
                $data['sku'] = $_REQUEST['sku'];
            }

            $data['quantity'] = 0;
            if (isset($_REQUEST['quantity'])) {
                $data['quantity'] = $_REQUEST['quantity'];
            }

            $data['status'] = 0;
            if (isset($_REQUEST['status'])) {
                $data['status'] = $_REQUEST['status'];
            }

            $data['stock_status_id'] = 7;
            if (isset($_REQUEST['substatus'])) {
                $data['stock_status_id'] = $_REQUEST['substatus'];
            }

            if (!empty($_REQUEST['categories'])) {
                $data['product_category'] = $_REQUEST['categories'];
            }

//            var_dump($_REQUEST['options']);
//            die();

            if (!empty($_REQUEST['options'])) {
                $data['product_option'] = $_REQUEST['options'];
            }

            if (!empty($_REQUEST['attributes'])) {
                $data['product_attribute'] = $_REQUEST['attributes'];
            }

            if (!empty($_REQUEST['product_id'])) {
                $product_id = $_REQUEST['product_id'];
                $data['product_image'] = $images;
                $this->model_extension_module_apimodule->editProduct($product_id,$data);
            } else {
                $data['product_id'] = 0;
                if (!empty($images[0])) {
                    $data['image'] = $images[0];
                    unset($images[0]);
                }
                $data['product_image'] = $images;
                $data['product_store'] = $this->config->get('module_apimodule_store');
                $product_id = $this->model_extension_module_apimodule->addProduct($data);
            }

            $images = [];
            $product_img = $this->model_extension_module_apimodule->getProductImages($product_id);
            $this->load->model('tool/image');
            if (count($product_img['images']) > 0) {

                foreach ($product_img['images'] as $key => $image) {
                    $img = [];
                    $img['image'] = !is_null($this->model_tool_image->resize($product_img['images'][$key]['image'], 600, 800)) ? $this->model_tool_image->resize($product_img['images'][$key]['image'], 600, 800) : '';
                    $img['image_id'] = (int)$product_img['images'][$key]['product_image_id'];
                    $images[] = $img;
                }

            } else {
                $images = [];
            }
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                    'status' => true,
                    'response' =>[
                        'product_id'=>$product_id,
                        'images' => $images
                    ]
                ]
            ));
        } else {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => 'You have not specified ID',
                'status' => false]));
        }
    }

    /**
     * @api {post} index.php?route=module/apimodule/productAttributes  List product attributes
     * @apiName productAttributes
     * @apiDescription Get list product attributes
     * @apiGroup Product
     *
     * @apiParam {Token}      token                                     Your unique token.
     *
     *
     * @apiSuccess {Array[]}  response                                  Array with content response.
     * @apiSuccess {Number}   version                                   Current API version.
     * @apiSuccess {Bool}     status                                    Response status.
     *
     * @apiSuccess {String}   response.attributes                       Array attributes.
     *
     * @apiSuccess {Array[]}  response.attributes.group                 Array of attributes of this group.
     * @apiSuccess {String}   response.attributes.group.attribute_id    Attribute id.
     * @apiSuccess {String}   response.attributes.group.attribute       Attribute.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *      "version": 0,
     *       "status": true,
     *       "response": {
     *         "attributes": {
     *               "Processor": [
     *                   {
     *                       "attribute_id": "1",
     *                       "attribute": "Description"
     *                   },
     *                   {
     *                       "attribute_id": "2",
     *                       "attribute": "No. of Cores"
     *                   },
     *                   {
     *                       "attribute_id": "3",
     *                       "attribute": "Clockspeed"
     *                   }
     *               ],
     *               "Memory": [
     *                   {
     *                       "attribute_id": "9",
     *                       "attribute": "test 6"
     *                   },
     *                   {
     *                       "attribute_id": "10",
     *                       "attribute": "test 7"
     *                   },
     *                   {
     *                       "attribute_id": "11",
     *                       "attribute": "test 8"
     *                   }
     *               ]
     *           }
     *       }
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error": "You need to be logged!",
     *      "version": 2,
     *      "Status" : false
     * }
     *
     *
     */
    public function productAttributes()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
                'error' => $error,
                'status' => false]));
            return;
        }

        $this->load->model('extension/module/apimodule');
        $attributes = $this->model_extension_module_apimodule->getDefaultProductAttributes();

        $listAttributes = [];

        foreach ($attributes as $key => $value) {
            $listAttributes[$value['category']][] = [
                'attribute_id' => $value['attribute_id'],
                'attribute' => $value['attribute']
            ];
        }

        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'status' => true,
                'response' =>[
                    'attributes' => $listAttributes
                ]
            ]
        ));
    }

    /**
     * @api {post} index.php?route=module/apimodule/deleteImage  Delete image
     * @apiName deleteImage
     * @apiDescription Delete image by image_id
     * @apiGroup Image
     *
     * @apiParam {Token}  token         Your unique token.
     * @apiParam {Number} product_id    Unique product ID.
     * @apiParam {Number} image_id      Unique image ID.
     *
     * @apiSuccess {Number}   version                    Current API version.
     * @apiSuccess {Bool}     status                     Response status.
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *     "Status" : true,
     *     "version": 1.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Can not found category with id = 10",
     *      "version": 1.0,
     *      "Status" : false
     * }
     *
     *
     */
    public function deleteImage()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');
        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
            return;
        }
        if (!empty($_REQUEST['product_id'])) {
            if (!empty($_REQUEST['image_id'])) {
                if ($_REQUEST['image_id'] == -1) {
                    $this->model_extension_module_apimodule->removeProductMainImage($_REQUEST['product_id']);
                    $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'status' => true]));
                } else {
                    $this->model_extension_module_apimodule->removeProductImageById($_REQUEST['image_id'], $_REQUEST['product_id']);
                    $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'status' => true]));
                }
            } else {
                $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'You have not specified image id', 'status' => false]));
            }
        } else {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'You have not specified ID', 'status' => false]));
        }
    }

    /**
     * @api {post} index.php?route=module/apimodule/mainImage  Set main image
     * @apiName mainImage
     * @apiDescription Set main image for product
     * @apiGroup Image
     *
     * @apiParam {Token}  token your unique token.
     * @apiParam {Number} product_id unique product ID.
     * @apiParam {Number} image_id unique image ID.
     *
     *
     * @apiSuccess {Number}  version  Current API version.
     * @apiSuccess {Bool} status Status of the product update.
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *     "Status" : true,
     *     "version": 1.0
     *     }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Can not found category with id = 10",
     *      "version": 1.0,
     *      "Status" : false
     * }
     *
     *
     */
    public function mainImage()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');
        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
            return;
        }
        if (!empty($_REQUEST['product_id'])) {
            if (!empty($_REQUEST['product_id'])) {
                if (!empty($_REQUEST['image_id'])) {
                    $this->model_extension_module_apimodule->setMainImageByImageId($_REQUEST['image_id'], $_REQUEST['product_id']);
                    $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'status' => true]));
                } else {
                    $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'You have not specified image id', 'status' => false]));
                }
            }
        } else {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => 'You have not specified ID', 'status' => false]));
        }
    }

    /**
     * @api {post} index.php?route=module/apimodule/getCategories  Get categories
     * @apiName getCategories
     * @apiDescription Get list categories
     * @apiGroup Category
     *
     * @apiParam {Token}  token your unique token.
     * @apiParam {Number} category_id unique category ID.
     *
     * @apiSuccess {Array[]}  response                           Array with content response.
     * @apiSuccess {Number}   version                            Current API version.
     * @apiSuccess {Bool}     status                             Response status.
     *
     * @apiSuccess {Array[]}  response.categories                Array of categories.
     * @apiSuccess {string}   response.categories.name           Category name.
     * @apiSuccess {string}   response.categories.category_id    Category id.
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *      "version": 2,
     *       "response": {
     *          "categories": [
     *               {
     *                   "name": "Monitors",
     *                   "category_id": "28"
     *               },
     *               {
     *                   "name": "Mice and Trackballs",
     *                   "category_id": "29"
     *               },
     *               {
     *                   "name": "Printers",
     *                   "category_id": "30"
     *               },
     *               {
     *                   "name": "Scanners",
     *                   "category_id": "31"
     *               },
     *               {
     *                   "name": "Web Cameras",
     *                   "category_id": "32"
     *               }
     *           ]
     *       },
     *       "status": true
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Can not found category with id = 10",
     *      "version": 1.0,
     *      "Status" : false
     * }
     *
     *
     */
    public function getCategories()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
            return;
        }
        $this->load->model('extension/module/apimodule');
        if ($_REQUEST['category_id'] == -1) {
            $categories = $this->model_extension_module_apimodule->getCategories();
        } else {
            $categories = $this->model_extension_module_apimodule->getCategoriesById($_REQUEST['category_id']);
        }

        $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'response' => ['categories' => $categories], 'status' => true]));
    }

    /**
     * @api {post} index.php?route=module/apimodule/getSubstatus  Get substatuses
     * @apiName getSubstatus
     * @apiDescription Get list substatuses
     * @apiGroup Status
     *
     * @apiParam {Token} token your unique token.
     *
     *
     * @apiSuccess {Array[]}  response                           Array with content response.
     * @apiSuccess {Number}   version                            Current API version.
     * @apiSuccess {Bool}     status                             Response status.
     *
     * @apiSuccess {Array[]}  response.stock_statuses                     Array stock statuses.
     * @apiSuccess {String}   response.stock_statuses.name                Stock status name.
     * @apiSuccess {String}   response.stock_statuses.stock_status_id     Stock statuses id.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK {
     *      "version": 0,
     *       "response": {
     *           "stock_statuses": [
     *               {
     *                   "name": "In Stock",
     *                   "stock_status_id": "7"
     *               },
     *               {
     *                   "name": "Pre-Order",
     *                   "stock_status_id": "8"
     *               },
     *               {
     *                   "name": "Out Of Stock",
     *                   "stock_status_id": "5"
     *               },
     *               {
     *                   "name": "2-3 Days",
     *                   "stock_status_id": "6"
     *               }
     *           ]
     *       },
     *       "status": true
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Can not found category with id = 10",
     *      "version": 1.0,
     *      "Status" : false
     * }
     *
     *
     */
    public function getSubstatus()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode(['version' => $this->API_VERSION, 'error' => $error, 'status' => false]));
            return;
        }
        $this->load->model('extension/module/apimodule');

        $categories = $this->model_extension_module_apimodule->getSubstatus();

        $this->response->setOutput(json_encode(['version' => $this->API_VERSION,
            'response' => ['stock_statuses' => $categories], 'status' => true]));
    }

    private function calculatePriceProduct($priceOld, $tax_class_id, $currency)
    {
        $price = $this->currency->format($this->tax->calculate($priceOld, $tax_class_id, $this->config->get('config_tax')), $currency);
        $symbol = $this->currency->getSymbolRight($currency);
        if (empty($symbol)) {
            $symbol = $this->currency->getSymbolLeft($currency);
        }
        $price = str_replace($symbol, '', $price);
        return $price;
    }

    private function calculatePrice($priceOld, $tax_class_id)
    {
        $price = $this->tax->calculate($priceOld, $tax_class_id, $this->config->get('config_tax'));
        return $price;
    }

    public function getLanguages()
    {
        header("Access-Control-Allow-Origin: *");
        $this->response->addHeader('Content-Type: application/json');

        $error = $this->valid();
        if ($error != null) {
            $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'error' => $error,
                'status' => false
            ]));
            return;
        }
        $this->load->model('extension/module/apimodule');

        $languages = $this->model_extension_module_apimodule->getLanguages();

        $this->response->setOutput(json_encode([
                'version' => $this->API_VERSION,
                'response' => [
                    'languages' => $languages
                ],
                'status' => true]
        ));

    }

    private function getBaseUrl()
    {
        $protocol = 'http' . (empty($_SERVER['HTTPS']) ? '' : 's');
        if ($protocol == 'http') {
            $base_url = HTTP_SERVER;
        } else {
            $base_url = HTTPS_SERVER;    
        }

        return rtrim($base_url, '/');
    }

    private function getAccessKey()
    {
        return 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE3MjY3NDg0Njl9.Kcc87J592eiMgtYnIzSLpIkSoXVUGcg8Fr71FiQc224';
    }

    public function sendNotifications($msg_data)
    {
        $site_info = $this->getSiteInfo();
        $site_id = $site_info['site_id'];
        $get_push_limit = $this->getPushLimit($site_id);
        $is_limit_exeded = $get_push_limit['isLimitExeded'] ?? true;
        if (!$devices = $site_info['devices']) {
            return;
        }

        if (!$access_token = $this->getAccessToken()) {
            return;
        }
    
        $msg_ios = $this->createMessageIos($msg_data);
        $msg_android = $this->createMessageAndroid($msg_data);

        if (!$is_limit_exeded) {
            foreach ($devices as $device) {
                $fields = ['message' => $device['platform'] == 'ios' ? $msg_ios : $msg_android];
                $fields['message']['token'] = $device['fcm_token'];
                $this->sendCurl($fields, $access_token, $site_id);
            }
        }
    }

    private function createMessageIos($msg_data) {
        return [
            'notification' => [
                'title' => $this->getBaseUrl(),
                'body'=> $msg_data['body']
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'alert' => [
                          'title' => $this->getBaseUrl(),
                          'body'=> $msg_data['body']
                        ],
                        'sound' => 'default',
                        'content-available' => 1,
                        'mutable-content' => 1,
                    ],
                ],
            ],   
            'data' => [
                $msg_data['action'] => json_encode($msg_data['data']),
                'event_type' => $msg_data['action']
            ]
        ];
    }

    private function createMessageAndroid($msg_data) {
        return [
            'notification' => [
                'title' => $this->getBaseUrl(),
                'body'=> $msg_data['body']
            ],
            'android' => [
                'priority' => 'high',
            ],
            'data' => [
                $msg_data['action'] => json_encode($msg_data['data']),
                'event_type' => $msg_data['action']
            ]
        ];
    }

    private function getSiteInfo() {
        $url = $this->get_site_info_url . '?url=' . $this->getBaseUrl();

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getAccessKey()
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        if ($result['success']) {
            if (isset($result['data'][0]['devices']) && $result['data'][0]['devices']) {
                foreach ($result['data'][0]['devices'] as $device) {
                    $devices[] = [
                        'fcm_token' => $device['fcmToken'],
                        'platform' => $device['platform'],
                    ];
                }
            } else {
                $devices = [];
            }
            $site_id = $result['data'][0]['id'] ?? '';
            $site_token = $result['data'][0]['siteToken'] ?? '';
            $account_type = $result['data'][0]['accountType'] ?? '';
            $push_count = $result['data'][0]['pushCount'] ?? 0;

            return compact('devices', 'site_id', 'site_token', 'account_type', 'push_count');
        }

        return false;
    }

    private function getAccessToken() {
        $url = $this->get_access_token_url;

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getAccessKey()
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data['accessToken']) && $data['accessToken']) {
            $access_token = $data['accessToken'];
        } else {
            $access_token = null;   
        }

        return $access_token;
    }

    private function sendCurl($fields, $access_token, $site_id)
    {
        $project_id = 'pinta-7691f';

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ];
    
        $ch = curl_init('https://fcm.googleapis.com/v1/projects/' . $project_id . '/messages:send');
    
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $response = curl_exec($ch);
        $result = json_decode($response, true);
        if (!isset($result['error']) && isset($result['name'])) {
            $this->incrementPushCount($site_id, 1);
        }
        curl_close($ch);
    }

    private function incrementPushCount($site_id, $push_count)
    {
        $url = $this->increment_push_count_url;

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getAccessKey()
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['id' => $site_id, 'pushCount' => $push_count]));
        curl_exec($ch);
        curl_close($ch);
    }

    private function getPushLimit($site_id)
    {
        $url = $this->get_push_limit_url . '?id=' . $site_id;

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getApiAccessKey()
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        return $result;
    }

}
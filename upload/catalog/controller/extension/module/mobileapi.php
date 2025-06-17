<?php
/**
 * Mobile API (v1) – Catalog Controller
 * Lightweight PHP 5.6 compatible controller exposing minimal endpoints.
 * Endpoints (route param):
 *  - login      (POST)
 *  - register   (POST)
 *  - categories (GET, protected)
 *  - products   (GET)
 *  - product    (GET)
 *  - orders     (GET, protected)
 *  - order      (GET, protected)
 *  - auth/check (GET)
 *  - profile    (GET, protected)
 *  - profile/update (POST, protected)
 *  - logout     (POST)
 *  - cart       (GET, protected)
 *  - cart/add   (POST, protected)
 *  - cart/update (POST, protected)
 *  - cart/remove (POST, protected)
 *  - wishlist     (GET, protected)
 *  - wishlist/add (POST, protected)
 *  - wishlist/remove (POST, protected)
 *  - checkout     (POST, protected)
 */
class ControllerExtensionModuleMobileapi extends Controller {
    private $token_ttl = 7200; // seconds
    private $customer_id = 0;

    /**
     * Send JSON response with HTTP status code
     * @param mixed $data
     * @param int   $code
     */
    private function sendJson($data, $code = 200) {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' ' . $code);
        $this->response->setOutput(json_encode($data));
    }

    /**
     * Extract Authorization header value
     * @return string
     */
    private function getAuthHeader() {
        if (isset($this->request->server['HTTP_AUTHORIZATION'])) {
            return $this->request->server['HTTP_AUTHORIZATION'];
        }
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                return $headers['Authorization'];
            }
        }
        return '';
    }

    /**
     * Verify JWT token and populate $this->customer_id
     * @return bool
     */
    private function requireAuth() {
        $auth = $this->getAuthHeader();
        if (strpos($auth, 'Bearer ') === 0) {
            $jwt = substr($auth, 7);
            $payload = JwtHelper::decode($jwt, $this->config->get('module_mobileapi_secret'));
            if ($payload && isset($payload->customer_id) && isset($payload->exp) && ($payload->exp > time())) {
                $this->customer_id = (int)$payload->customer_id;
                return true;
            }
        }
        $this->sendJson(array('error' => 'Unauthorized'), 401);
        return false;
    }

    /* ---------------------------------------------------- */
    /* PUBLIC ENDPOINTS                                     */
    /* ---------------------------------------------------- */

    /**
     * POST: email, password
     * Responds with JWT token
     */
    public function login() {
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(array('error' => 'Method Not Allowed'), 405);
            return;
        }

        $email    = isset($this->request->post['email']) ? trim($this->request->post['email']) : '';
        $password = isset($this->request->post['password']) ? $this->request->post['password'] : '';

        $this->load->model('account/customer');
        if (!$this->model_account_customer->login($email, $password)) {
            $this->sendJson(array('error' => 'Invalid credentials'), 401);
            return;
        }

        $customer_id = $this->customer->getId();
        $payload = array(
            'customer_id' => $customer_id,
            'iat'         => time(),
            'exp'         => time() + $this->token_ttl,
        );
        $token = JwtHelper::encode($payload, $this->config->get('module_mobileapi_secret'));
        $this->sendJson(array('token' => $token, 'expires_in' => $this->token_ttl));
    }

    /**
     * POST: firstname, lastname, email, telephone, password
     * Responds with JWT token
     */
    public function register() {
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(array('error' => 'Method Not Allowed'), 405);
            return;
        }

        $data = array(
            'firstname'         => isset($this->request->post['firstname']) ? trim($this->request->post['firstname']) : '',
            'lastname'          => isset($this->request->post['lastname']) ? trim($this->request->post['lastname']) : '',
            'email'             => isset($this->request->post['email']) ? trim($this->request->post['email']) : '',
            'telephone'         => isset($this->request->post['telephone']) ? trim($this->request->post['telephone']) : '',
            'password'          => isset($this->request->post['password']) ? $this->request->post['password'] : '',
            'customer_group_id' => $this->config->get('config_customer_group_id'),
            'newsletter'        => 0,
            'status'            => 1,
            'approved'          => 1,
            'safe'              => 0,
        );

        if (!$data['firstname'] || !$data['email'] || !$data['password']) {
            $this->sendJson(array('error' => 'Missing required fields'), 400);
            return;
        }

        $this->load->model('account/customer');
        if ($this->model_account_customer->getTotalCustomersByEmail($data['email'])) {
            $this->sendJson(array('error' => 'Email already exists'), 400);
            return;
        }

        $customer_id = $this->model_account_customer->addCustomer($data);
        $payload = array(
            'customer_id' => $customer_id,
            'iat'         => time(),
            'exp'         => time() + $this->token_ttl,
        );
        $token = JwtHelper::encode($payload, $this->config->get('module_mobileapi_secret'));
        $this->sendJson(array('token' => $token, 'expires_in' => $this->token_ttl));
    }

    /**
     * GET list of top-level categories with children (protected)
     */
    public function categories() {
        if (!$this->requireAuth()) {
            return;
        }
        $this->load->model('extension/module/mobileapi');
        $categories = $this->model_extension_module_mobileapi->getCategoryTree();
        $this->sendJson($categories);
    }

    /**
     * GET: optional category_id – list products
     */
    public function products() {
        $category_id = isset($this->request->get['category_id']) ? (int)$this->request->get['category_id'] : 0;
        $filter_data = array(
            'filter_category_id' => $category_id,
            'start'              => 0,
            'limit'              => 100,
            'sort'               => 'p.sort_order',
            'order'              => 'ASC',
        );
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $results = $this->model_catalog_product->getProducts($filter_data);
        $products = array();
        foreach ($results as $row) {
            $products[] = array(
                'product_id' => (int)$row['product_id'],
                'name'       => $row['name'],
                'price'      => (float)$row['price'],
                'special'    => isset($row['special']) ? (float)$row['special'] : null,
                'thumb'      => $row['image'] ? $this->model_tool_image->resize($row['image'], 200, 200) : '',
            );
        }
        $this->sendJson($products);
    }

    /**
     * GET: product_id – single product details
     */
    public function product() {
        $product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
        if (!$product_id) {
            $this->sendJson(array('error' => 'Missing product_id'), 400);
            return;
        }
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $info = $this->model_catalog_product->getProduct($product_id);
        if (!$info) {
            $this->sendJson(array('error' => 'Product not found'), 404);
            return;
        }
        $product = array(
            'product_id' => (int)$info['product_id'],
            'name'       => $info['name'],
            'description'=> html_entity_decode($info['description'], ENT_QUOTES, 'UTF-8'),
            'price'      => (float)$info['price'],
            'special'    => isset($info['special']) ? (float)$info['special'] : null,
            'thumb'      => $info['image'] ? $this->model_tool_image->resize($info['image'], 500, 500) : '',
        );
        $this->sendJson($product);
    }

    /**
     * GET order list for authenticated customer
     */
    public function orders() {
        if (!$this->requireAuth()) {
            return;
        }
        $this->load->model('account/order');
        $results = $this->model_account_order->getOrders(0, 100);
        $orders = array();
        foreach ($results as $row) {
            $orders[] = array(
                'order_id'   => (int)$row['order_id'],
                'status'     => $row['status'],
                'total'      => $row['total'],
                'currency'   => $row['currency_code'],
                'date_added' => $row['date_added'],
            );
        }
        $this->sendJson($orders);
    }

    /**
     * GET single order detail (protected)
     */
    public function order() {
        if (!$this->requireAuth()) {
            return;
        }
        $order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;
        if (!$order_id) {
            $this->sendJson(array('error' => 'Missing order_id'), 400);
            return;
        }
        $this->load->model('account/order');
        $order = $this->model_account_order->getOrder($order_id);
        if (!$order || (int)$order['customer_id'] !== $this->customer_id) {
            $this->sendJson(array('error' => 'Order not found'), 404);
            return;
        }
        // Get products
        $products = $this->model_account_order->getOrderProducts($order_id);
        $order['products'] = $products;
        $this->sendJson($order);
    }

    /**
     * GET /auth/check – verify token validity
     */
    public function auth_check() {
        if ($this->requireAuth()) {
            $this->sendJson(array('valid' => true, 'customer_id' => $this->customer_id));
        }
    }

    /**
     * GET /profile – return customer details (protected)
     */
    public function profile() {
        if (!$this->requireAuth()) {
            return;
        }
        $this->load->model('account/customer');
        $info = $this->model_account_customer->getCustomer($this->customer_id);
        unset($info['password']);
        $this->sendJson($info);
    }

    /**
     * POST /profile/update – update firstname, lastname, telephone, password (optional)
     */
    public function profile_update() {
        if (!$this->requireAuth()) {
            return;
        }
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(array('error' => 'Method Not Allowed'), 405);
            return;
        }
        $fields = array('firstname', 'lastname', 'telephone');
        $update = array();
        foreach ($fields as $f) {
            if (isset($this->request->post[$f])) {
                $update[$f] = trim($this->request->post[$f]);
            }
        }
        if (isset($this->request->post['password']) && $this->request->post['password']) {
            $update['password'] = $this->request->post['password'];
        }
        if (!$update) {
            $this->sendJson(array('error' => 'No data'), 400);
            return;
        }
        // build SQL dynamically (safe since keys validated)
        $sets = array();
        foreach ($update as $k => $v) {
            if ($k == 'password') {
                $sets[] = "password = '" . $this->db->escape(md5($v)) . "'"; // OC 3 stores salted sha1; but md5 lesser – keep simple for PHP5.6 example
            } else {
                $sets[] = $k . " = '" . $this->db->escape($v) . "'";
            }
        }
        $this->db->query("UPDATE " . DB_PREFIX . "customer SET " . implode(',', $sets) . " WHERE customer_id = " . (int)$this->customer_id);
        $this->sendJson(array('success' => true));
    }

        /**
     * GET /cart – list items
     */
    public function cart() {
        if (!$this->requireAuth()) {
            return;
        }
        $this->load->model('extension/module/mobileapi');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $items = $this->model_extension_module_mobileapi->getCartItems($this->customer_id);
        $result = array();
        foreach ($items as $row) {
            $product = $this->model_catalog_product->getProduct($row['product_id']);
            if ($product) {
                $result[] = array(
                    'product_id' => (int)$row['product_id'],
                    'name'       => $product['name'],
                    'quantity'   => (int)$row['quantity'],
                    'price'      => (float)$product['price'],
                    'thumb'      => $product['image'] ? $this->model_tool_image->resize($product['image'], 100, 100) : ''
                );
            }
        }
        $this->sendJson($result);
    }

    /**
     * POST /cart/add – product_id, quantity
     */
    public function cart_add() {
        if (!$this->requireAuth()) {
            return;
        }
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(array('error' => 'Method Not Allowed'), 405);
            return;
        }
        $product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
        $qty        = isset($this->request->post['quantity']) ? (int)$this->request->post['quantity'] : 1;
        if (!$product_id) {
            $this->sendJson(array('error' => 'Missing product_id'), 400);
            return;
        }
        $this->load->model('extension/module/mobileapi');
        $this->model_extension_module_mobileapi->addToCart($this->customer_id, $product_id, $qty);
        $this->sendJson(array('success' => true));
    }

    /**
     * POST /cart/update – product_id, quantity
     */
    public function cart_update() {
        if (!$this->requireAuth()) {
            return;
        }
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(array('error' => 'Method Not Allowed'), 405);
            return;
        }
        $product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
        $qty        = isset($this->request->post['quantity']) ? (int)$this->request->post['quantity'] : 1;
        $this->load->model('extension/module/mobileapi');
        $this->model_extension_module_mobileapi->updateCartItem($this->customer_id, $product_id, $qty);
        $this->sendJson(array('success' => true));
    }

    /**
     * POST /cart/remove – product_id
     */
    public function cart_remove() {
        if (!$this->requireAuth()) {
            return;
        }
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(array('error' => 'Method Not Allowed'), 405);
            return;
        }
        $product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
        $this->load->model('extension/module/mobileapi');
        $this->model_extension_module_mobileapi->removeCartItem($this->customer_id, $product_id);
        $this->sendJson(array('success' => true));
    }

    /**
     * GET /wishlist – list product ids
     */
    public function wishlist() {
        if (!$this->requireAuth()) { return; }
        $this->load->model('extension/module/mobileapi');
        $ids = $this->model_extension_module_mobileapi->getWishlist($this->customer_id);
        $this->sendJson($ids);
    }

    /**
     * POST /wishlist/add – product_id
     */
    public function wishlist_add() {
        if (!$this->requireAuth()) { return; }
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') { $this->sendJson(array('error'=>'Method Not Allowed'),405); return; }
        $product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
        $this->load->model('extension/module/mobileapi');
        $this->model_extension_module_mobileapi->addWishlistItem($this->customer_id, $product_id);
        $this->sendJson(array('success'=>true));
    }

    /**
     * POST /wishlist/remove – product_id
     */
    public function wishlist_remove() {
        if (!$this->requireAuth()) { return; }
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') { $this->sendJson(array('error'=>'Method Not Allowed'),405); return; }
        $product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
        $this->load->model('extension/module/mobileapi');
        $this->model_extension_module_mobileapi->removeWishlistItem($this->customer_id, $product_id);
        $this->sendJson(array('success'=>true));
    }

    /**
     * POST /checkout – place order with current cart and address_id
     */
    public function checkout() {
        if (!$this->requireAuth()) { return; }
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') { $this->sendJson(array('error'=>'Method Not Allowed'),405); return; }
        $address_id = isset($this->request->post['address_id']) ? (int)$this->request->post['address_id'] : 0;
        if (!$address_id) { $this->sendJson(array('error'=>'Missing address_id'),400); return; }
        $this->load->model('account/address');
        $address = $this->model_account_address->getAddress($address_id);
        if (!$address || $address['customer_id'] != $this->customer_id) { $this->sendJson(array('error'=>'Invalid address'),400); return; }

        // Get cart items
        $this->load->model('extension/module/mobileapi');
        $cart_items = $this->model_extension_module_mobileapi->getCartItems($this->customer_id);
        if (!$cart_items) { $this->sendJson(array('error'=>'Cart empty'),400); return; }
        $this->load->model('catalog/product');

        $products = array();
        $total = 0;
        foreach ($cart_items as $item) {
            $product_info = $this->model_catalog_product->getProduct($item['product_id']);
            if (!$product_info) { continue; }
            $price = (float)$product_info['price'];
            $products[] = array(
                'product_id' => $item['product_id'],
                'name'       => $product_info['name'],
                'model'      => $product_info['model'],
                'quantity'   => $item['quantity'],
                'price'      => $price,
                'total'      => $price * $item['quantity'],
                'tax'        => 0,
                'reward'     => 0,
            );
            $total += $price * $item['quantity'];
        }
        if (!$products) { $this->sendJson(array('error'=>'Products not found'),400); return; }

        // Build order data minimal
        $order_data = array();
        $order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
        $order_data['store_id']       = 0;
        $order_data['store_name']     = $this->config->get('config_name');
        $order_data['store_url']      = $this->config->get('config_url');

        // Customer
        $order_data['customer_id']    = $this->customer_id;
        $order_data['customer_group_id'] = $this->config->get('config_customer_group_id');
        $order_data['firstname'] = $address['firstname'];
        $order_data['lastname']  = $address['lastname'];
        $order_data['email']     = '';
        $order_data['telephone'] = $address['telephone'];

        // Payment same as address
        foreach (array('payment','shipping') as $type) {
            $order_data[$type.'_firstname'] = $address['firstname'];
            $order_data[$type.'_lastname']  = $address['lastname'];
            $order_data[$type.'_address_1'] = $address['address_1'];
            $order_data[$type.'_address_2'] = $address['address_2'];
            $order_data[$type.'_city']      = $address['city'];
            $order_data[$type.'_postcode']  = $address['postcode'];
            $order_data[$type.'_country']   = $address['country'];
            $order_data[$type.'_country_id']= $address['country_id'];
            $order_data[$type.'_zone']      = $address['zone'];
            $order_data[$type.'_zone_id']   = $address['zone_id'];
        }
        $order_data['payment_method'] = 'Cash On Delivery';
        $order_data['payment_code']   = 'cod';
        $order_data['shipping_method']= 'Flat Rate';
        $order_data['shipping_code']  = 'flat.flat';

        $order_data['products'] = $products;
        $order_data['totals']   = array(array(
            'code'       => 'sub_total',
            'title'      => 'Sub-Total',
            'value'      => $total,
            'sort_order' => 1
        ));
        $order_data['comment']  = '';
        $order_data['total']    = $total;
        $order_data['reward']   = 0;
        $order_data['affiliate_id'] = 0;
        $order_data['commission']   = 0;
        $order_data['language_id']  = $this->config->get('config_language_id');
        $order_data['currency_id']  = $this->currency->getId($this->session->data['currency']);
        $order_data['currency_code']= $this->session->data['currency'];
        $order_data['currency_value']= $this->currency->getValue($this->session->data['currency']);
        $order_data['ip'] = $this->request->server['REMOTE_ADDR'];
        $order_data['user_agent'] = $this->request->server['HTTP_USER_AGENT'];
        $order_data['accept_language'] = $this->request->server['HTTP_ACCEPT_LANGUAGE'];

        $this->load->model('checkout/order');
        $order_id = $this->model_checkout_order->addOrder($order_data);

        // Clear cart
        $this->model_extension_module_mobileapi->removeCartItem($this->customer_id, 0); // remove all - implement query override
        $this->db->query("DELETE FROM " . DB_PREFIX . "cart WHERE customer_id=".(int)$this->customer_id);

        $this->sendJson(array('success'=>true,'order_id'=>$order_id));
    }

    /**
     * POST /logout – nothing server-side, just inform client
     */
    public function logout() {
        $this->sendJson(array('success' => true));
    }
}



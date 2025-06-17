<?php

class ModelExtensionModuleApimodule extends Model
{
    private $API_VERSION = 2.0;

    public function getVersion()
    {
        return $this->API_VERSION;
    }

    public function getMaxOrderPrice()
    {
        $query = $this->db->query("SELECT MAX(`total`) AS total FROM `" . DB_PREFIX . "order` o WHERE o.`order_status_id` != 0");

        return number_format($query->row['total'], 2, '.', '');
    }

    public function getOrders($data = [])
    {
        $sql = "SELECT * "
            ."FROM `" . DB_PREFIX . "order` AS o "
            ."LEFT JOIN `" . DB_PREFIX . "order_status` AS s ON (o.`order_status_id` = s.`order_status_id`) ";
        if (isset($data['filter'])) {
            if (!empty($data['filter']['order_status_id'])) {
                $sql .= " WHERE o.`order_status_id` = " . (int) $data['filter']['order_status_id'];
            } else {
                $sql .= " WHERE o.`order_status_id` != 0 ";
            }
            if (!empty($data['filter']['fio']) && $data['filter']['fio'] !== 'null') {
                if (!ctype_digit($data['filter']['fio']) && (intval($data['filter']['fio']) == 0)) {
                    $params = [];
                    $newparam = explode(' ', $data['filter']['fio']);

                    foreach ($newparam as $key => $value) {
                        if ($value == '') {
                            unset($newparam[$key]);
                        } else {
                            $params[] = $value;
                        }
                    }

                    $sql .= " AND (o.`firstname` LIKE '%" . $this->db->escape($params[0]) . "%' "
                        ."OR o.`lastname` LIKE '%" . $this->db->escape($params[0]) . "%' "
                        ."OR o.`payment_lastname` LIKE '%" . $this->db->escape($params[0]) . "%' "
                        ."OR o.`payment_firstname` LIKE '%" . $this->db->escape($params[0]) . "%'";

                    foreach ($params as $param) {
                        if ($param != $params[0]) {
                            $sql .= " OR o.`firstname` LIKE '%" . $this->db->escape($params[0]) . "%' "
                                ."OR o.`lastname` LIKE '%" . $this->db->escape($param) . "%' "
                                ."OR o.`payment_lastname` LIKE '%" . $this->db->escape($param) . "%' "
                                ."OR o.`payment_firstname` LIKE '%" . $this->db->escape($param) . "%'";
                        };
                    }
                    $sql .= ") ";
                } elseif (strlen(intval($data['filter']['fio'])) < 6) {
                    $order_id = (int) $data['filter']['fio'];
                    $sql .= " AND (`order_id` = " . $order_id . ") ";
                } else {
                    $str = str_replace(' ', '%', $data['filter']['fio']);
                    $sql .= " AND (`telephone` LIKE '%" . $this->db->escape($str) . "%') ";
                }
            }
            if (!empty($data['filter']['min_price']) && ((int) $data['filter']['min_price'] != 0)) {
                $sql .= " AND (o.`total` >= " . (int) $data['filter']['min_price'] . ")";
            }
            if (!empty($data['filter']['max_price']) && ((int) $data['filter']['max_price'] != 0)) {
                $sql .= " AND (o.`total` <= " . (int) $data['filter']['max_price'] . ")";
            }
            if (!empty($data['filter']['date_min'])) {
                $date_min = date('y-m-d', strtotime($data['filter']['date_min']));
                $sql .= " AND DATE_FORMAT(o.`date_added`, '%y-%m-%d') >= '" . $this->db->escape($date_min) . "'";
            }
            if (!empty($data['filter']['date_max'])) {
                $date_max = date('y-m-d', strtotime($data['filter']['date_max']));
                $sql .= " AND DATE_FORMAT(o.`date_added`, '%y-%m-%d') <= '" . $this->db->escape($date_max) . "'";
            }
        } else {
            $sql .= " WHERE (o.`order_status_id` != 0) ";
        }
        $sql .= " GROUP BY o.`order_id` ORDER BY o.`order_id` DESC";

        $total_sum = $this->db->query(
            "SELECT COALESCE(SUM(`total`), 0) AS `summa`, COUNT(*) as `quantity` "
            ."FROM `" . DB_PREFIX . "order` "
            ."WHERE `order_status_id` != 0"
        );
        $sum = $total_sum->rows[0]['summa'];
        $quantity = $total_sum->rows[0]['quantity'];

        $sql .= " LIMIT " . (int) $data['limit'] . " OFFSET " . (int) $data['page'];

        $query = $this->db->query($sql);
        $query->totalsumm=$sum;
        $query->quantity=$quantity;
        return $query;
    }

    public function getOrderById($id)
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "order` AS o "
            ."LEFT JOIN `" . DB_PREFIX . "order_status` AS s ON o.`order_status_id` = s.`order_status_id` "
            ."WHERE `order_id` = " . (int) $id . " "
            ."GROUP BY o.`order_id` "
            ."ORDER BY o.`order_id`"
        );
//        var_dump($query);
//        die();
        return $query->rows;
    }

    public function getOrderFindById($id)
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "order` AS o "
            ."LEFT JOIN `" . DB_PREFIX . "order_status` AS s ON o.`order_status_id` = s.`order_status_id` "
            ."WHERE o.`order_id` = " . (int) $id . " "
            ."AND o.`order_status_id` != 0 "
            ."GROUP BY o.`order_id` "
            ."ORDER BY o.`order_id`"
        );
        return $query->row;
    }

    public function AddComment($orderID, $statusID, $comment = '', $inform = false)
    {
        $setStatus = $this->db->query(
            "UPDATE `" . DB_PREFIX . "order` "
            ."SET `order_status_id` = " . (int) $statusID . " "
            ."WHERE `order_id` = " . (int) $orderID
        );
        if ($setStatus === true) {
            $getStatus = $this->db->query(
                "SELECT `name`, `date_added` "
                ."FROM `" . DB_PREFIX . "order_status` AS s "
                ."LEFT JOIN `" . DB_PREFIX . "order` AS o ON o.`order_status_id` = s.`order_status_id` "
                ."WHERE o.`order_id` = " . (int) $orderID
            );
            $notify = ($inform == 'true');
            $this->db->query(
                "INSERT INTO `" . DB_PREFIX . "order_history` ("
                ."`order_id`, `order_status_id`, `notify`, `comment`, `date_added`"
                .") VALUES ("
                .(int) $orderID . ", "
                .(int) $statusID . ", "
                .(int) $notify . ", "
                ."'" . $this->db->escape($comment) . "', "
                ."NOW()"
                .")"
            );

            $email = $this->db->query(
                "SELECT o.`email`, o.`store_name`, o.`firstname` "
                ."FROM `" . DB_PREFIX . "order` AS o "
                ."WHERE o.`order_id` = " . (int) $orderID
            );
            if ($inform == 'true') {
                $mail = new Mail();

                $mail->protocol = $this->config->get('config_mail_protocol');
                $mail->parameter = $this->config->get('config_mail_parameter');
                $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
                $mail->smtp_username = $this->config->get('config_mail_smtp_username');
                $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
                $mail->smtp_port = $this->config->get('config_mail_smtp_port');
                $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

                $mail->setTo($email->row['email']);


                $mail->setFrom($this->config->get('config_email'));
                $mail->setSender(html_entity_decode($email->row['store_name'], ENT_QUOTES, 'UTF-8'));
                $mail->setSubject(html_entity_decode($email->row['firstname'], ENT_QUOTES, 'UTF-8'));

                $data = $this->getTemplateForEmail($orderID, $getStatus->row['name'], $comment , $inform);

                $mail->setHtml($this->load->view('mail/order_add', $data));
                $mail->send();


            }
        }
        return $getStatus->row;
    }

    public function getTemplateForEmail($orderID, $status, $comment, $notify)
    {
        $order_id = $orderID;
        $order_info = $this->getOrder($order_id);

        // Load the language for any mails that might be required to be sent out
        $language = new Language($order_info['language_code']);
        $language->load($order_info['language_code']);
        $language->load('mail/order_add');

        // HTML Mail
        $data['title'] = sprintf($language->get('text_subject'), $order_info['store_name'], $order_info['order_id']);

        $data['text_greeting'] = sprintf($language->get('text_greeting'), $order_info['store_name']);
        $data['text_link'] = $language->get('text_link');
        $data['text_download'] = $language->get('text_download');
        $data['text_order_detail'] = $language->get('text_order_detail');
        $data['text_instruction'] = $language->get('text_instruction');
        $data['text_order_id'] = $language->get('text_order_id');
        $data['text_date_added'] = $language->get('text_date_added');
        $data['text_payment_method'] = $language->get('text_payment_method');
        $data['text_shipping_method'] = $language->get('text_shipping_method');
        $data['text_email'] = $language->get('text_email');
        $data['text_telephone'] = $language->get('text_telephone');
        $data['text_ip'] = $language->get('text_ip');
        $data['text_order_status'] = $language->get('text_order_status');
        $data['text_payment_address'] = $language->get('text_payment_address');
        $data['text_shipping_address'] = $language->get('text_shipping_address');
        $data['text_product'] = $language->get('text_product');
        $data['text_model'] = $language->get('text_model');
        $data['text_quantity'] = $language->get('text_quantity');
        $data['text_price'] = $language->get('text_price');
        $data['text_total'] = $language->get('text_total');
        $data['text_footer'] = $language->get('text_footer');

        $data['logo'] = $this->config->get('config_url') . 'image/' . $this->config->get('config_logo');
        $data['store_name'] = $order_info['store_name'];
        $data['store_url'] = $order_info['store_url'];
        $data['customer_id'] = $order_info['customer_id'];
        $data['link'] = $order_info['store_url'] . 'index.php?route=account/order/info&order_id=' . $order_info['order_id'];

        $data['download'] = '';

        $data['order_id'] = $order_info['order_id'];
        $data['date_added'] = date($language->get('date_format_short'), strtotime($order_info['date_added']));
        $data['payment_method'] = $order_info['payment_method'];
        $data['shipping_method'] = $order_info['shipping_method'];
        $data['email'] = $order_info['email'];
        $data['telephone'] = $order_info['telephone'];
        $data['ip'] = $order_info['ip'];
        $data['order_status'] = $order_info['order_status'];

        if ($comment && $notify) {
            $data['comment'] = nl2br($comment);
        } else {
            $data['comment'] = '';
        }

        if ($order_info['payment_address_format']) {
            $format = $order_info['payment_address_format'];
        } else {
            $format = '{firstname} {lastname}' . "\n" .
                '{company}' . "\n" .
                '{address_1}' . "\n" .
                '{address_2}' . "\n" .
                '{city} {postcode}' . "\n" .
                '{zone}' . "\n" .
                '{country}';
        }

        $find = [
            '{firstname}',
            '{lastname}',
            '{company}',
            '{address_1}',
            '{address_2}',
            '{city}',
            '{postcode}',
            '{zone}',
            '{zone_code}',
            '{country}',
        ];

        $replace = [
            'firstname' => $order_info['payment_firstname'],
            'lastname' => $order_info['payment_lastname'],
            'company' => $order_info['payment_company'],
            'address_1' => $order_info['payment_address_1'],
            'address_2' => $order_info['payment_address_2'],
            'city' => $order_info['payment_city'],
            'postcode' => $order_info['payment_postcode'],
            'zone' => $order_info['payment_zone'],
            'zone_code' => $order_info['payment_zone_code'],
            'country' => $order_info['payment_country']
        ];

        $data['payment_address'] = str_replace(
            ["\r\n", "\r", "\n"],
            '<br />',
            preg_replace(["/\s\s+/", "/\r\r+/", "/\n\n+/"], '<br />', trim(str_replace($find, $replace, $format)))
        );

        if ($order_info['shipping_address_format']) {
            $format = $order_info['shipping_address_format'];
        } else {
            $format = '{firstname} {lastname}' . "\n" .
                '{company}' . "\n" .
                '{address_1}' . "\n" .
                '{address_2}' . "\n" .
                '{city} {postcode}' . "\n" .
                '{zone}' . "\n" .
                '{country}';
        }

        $find = [
            '{firstname}',
            '{lastname}',
            '{company}',
            '{address_1}',
            '{address_2}',
            '{city}',
            '{postcode}',
            '{zone}',
            '{zone_code}',
            '{country}',
        ];

        $replace = [
            'firstname' => $order_info['shipping_firstname'],
            'lastname' => $order_info['shipping_lastname'],
            'company' => $order_info['shipping_company'],
            'address_1' => $order_info['shipping_address_1'],
            'address_2' => $order_info['shipping_address_2'],
            'city' => $order_info['shipping_city'],
            'postcode' => $order_info['shipping_postcode'],
            'zone' => $order_info['shipping_zone'],
            'zone_code' => $order_info['shipping_zone_code'],
            'country' => $order_info['shipping_country'],
        ];

        $data['shipping_address'] = str_replace(
            ["\r\n", "\r", "\n"],
            '<br />',
            preg_replace(["/\s\s+/", "/\r\r+/", "/\n\n+/"], '<br />', trim(str_replace($find, $replace, $format)))
        );

        $this->load->model('tool/upload');

        // Products
        $data['products'] = [];

        // Stock subtraction
        $order_product_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = " . (int) $order_id);


        foreach ($order_product_query->rows as $order_product) {
            $this->db->query(
                "UPDATE `". DB_PREFIX . "product` "
                ."SET `quantity` = (`quantity` - " . (int) $order_product['quantity'] . ") "
                ."WHERE `product_id` = " . (int) $order_product['product_id'] . " "
                ."AND `subtract` = 1"
            );




            $order_option_query = $this->db->query(
                "SELECT * FROM `". DB_PREFIX . "order_option` "
                ."WHERE `order_id` = " . (int) $order_id . " "
                ."AND `order_product_id` = " . (int) $order_product['order_product_id']
            );




            foreach ($order_option_query->rows as $option) {
                $this->db->query(
                    "UPDATE `" . DB_PREFIX . "product_option_value` "
                    ."SET `quantity` = (`quantity` - " . (int) $order_product['quantity'] . ") "
                    ."WHERE `product_option_value_id` = " . (int) $option['product_option_value_id'] . " "
                    ."AND `subtract` = 1"
                );
            }
        }

        foreach ($order_product_query->rows as $product) {
            $option_data = [];

            $order_option_query = $this->db->query(
                "SELECT * FROM `" . DB_PREFIX . "order_option` "
                ."WHERE `order_id` = " . (int) $order_id . " "
                ."AND `order_product_id` = " . (int) $product['order_product_id']
            );

            foreach ($order_option_query->rows as $option) {
                if ($option['type'] != 'file') {
                    $value = $option['value'];
                } else {
                    $upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

                    if ($upload_info) {
                        $value = $upload_info['name'];
                    } else {
                        $value = '';
                    }
                }

                $option_data[] = [
                    'name' => $option['name'],
                    'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
                ];
            }

            $data['products'][] = [
                'name' => $product['name'],
                'model' => $product['model'],
                'option' => $option_data,
                'quantity' => $product['quantity'],
                'price' => $this->currency->format(
                    $product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0),
                    $order_info['currency_code'],
                    $order_info['currency_value']
                ),
                'total' => $this->currency->format(
                    $product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0),
                    $order_info['currency_code'],
                    $order_info['currency_value']
                ),
            ];
        }

        // Vouchers
        $data['vouchers'] = [];

        $order_voucher_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_voucher` WHERE `order_id` = " . (int) $order_id);

        foreach ($order_voucher_query->rows as $voucher) {
            $data['vouchers'][] = [
                'description' => $voucher['description'],
                'amount' => $this->currency->format($voucher['amount'], $order_info['currency_code'], $order_info['currency_value']),
            ];
        }

        // Order Totals
        $data['totals'] = [];

        $order_total_query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "order_total` "
            ."WHERE `order_id` = " . (int) $order_id . " "
            ."ORDER BY `sort_order` ASC"
        );

        foreach ($order_total_query->rows as $total) {
            $data['totals'][] = [
                'title' => $total['title'],
                'text' => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value']),
            ];
        }

        return $data;
    }

    public function getProducts($page)
    {
        $sql = "SELECT p.`product_id` "
            ."FROM `" . DB_PREFIX . "product` p "
            ."LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.`product_id` = pd.`product_id`) "
            ."LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.`product_id` = p2s.`product_id`) "
            ."LEFT JOIN `" . DB_PREFIX . "stock_status` ss ON (p.`stock_status_id` = ss.`stock_status_id`) "
            ."WHERE pd.`language_id` = " . (int) $this->config->get('config_language_id') . " "
            ."AND p.`status` = 1 "
            ."AND p.`date_available` <= NOW() "
            ."AND p2s.`store_id` = " . (int) $this->config->get('config_store_id') . " "
            ."GROUP BY p.`product_id` "
            ."ORDER BY p.`product_id` ASC "
            ."LIMIT 5 OFFSET " . (int) $page;
        $query = $this->db->query($sql);
        $this->load->model('catalog/product');
        foreach ($query->rows as $result) {
            $product_data[$result['product_id']] = $this->model_catalog_product->getProduct($result['product_id']);
        }
        return $product_data;
    }

    public function checkLogin($username, $password)
    {
        $query = $this->db->query(
            "SELECT * "
            ."FROM `" . DB_PREFIX . "user` "
            ."WHERE `username` = '" . $this->db->escape($username) . "' "
            ."AND ("
            ."`password` = SHA1(CONCAT(`salt`, SHA1(CONCAT(`salt`, SHA1('" . $this->db->escape($password) . "'))))) "
            ."OR `password` = '" . md5($password) . "'" // после md5 там нечего эскейпить
            .") "
            ."AND `status` = 1"
        );

        return $query->row;
    }

    public function setUserToken($id, $token)
    {
        $sql = "INSERT INTO `" . DB_PREFIX . "user_token_mob_api` ("
            ."`user_id`, `token`"
            .") VALUES ("
            .(int) $id . ", "
            ."'" . $this->db->escape($token) . "'"
            .")";
        $query = $this->db->query($sql);
        return $query;
    }

    public function getUserToken($id)
    {
        $query = $this->db->query("SELECT `token` FROM `" . DB_PREFIX . "user_token_mob_api` WHERE `user_id` = " . (int) $id);
        return $query->row;
    }

    public function getTokens()
    {
        $query = $this->db->query("SELECT `token` FROM `" . DB_PREFIX . "user_token_mob_api`");
        return $query->rows;
    }

    public function getOrderProducts($id)
    {
        $query = $this->db->query(
            "SELECT p.image, "
            ."p.product_id, "
            ."p.tax_class_id, "
            ."op.order_id, "
            ."op.model, "
            ."op.quantity, "
            ."op.price, "
            ."p.price AS old_price, "
            ."op.name, "
            ."o.store_url, "
            ."o.total, "
            ."o.currency_code, "
            ."ot.code, "
            ."ot.value, "
            ."("
            ."SELECT price "
            ."FROM " . DB_PREFIX . "product_discount pd2 "
            ."WHERE pd2.product_id = p.product_id "
            ."AND ("
            ."pd2.customer_group_id = " . (int) $this->config->get('config_customer_group_id') . " "
            ."OR pd2.customer_group_id = o.customer_group_id"
            .") "
            ."AND pd2.quantity <= op.quantity "
            ."AND ("
            ."(pd2.date_start = '0000-00-00' OR pd2.date_start < o.date_added) "
            ."AND (pd2.date_end = '0000-00-00' OR pd2.date_end > o.date_added)"
            .") "
            ."ORDER BY (pd2.customer_group_id = o.customer_group_id) DESC, pd2.priority ASC, pd2.price ASC "
            ."LIMIT 1"
            .") AS discount, "
            ."("
            ."SELECT price "
            ."FROM " . DB_PREFIX . "product_special ps "
            ."WHERE ps.product_id = p.product_id "
            ."AND ("
            ."ps.customer_group_id = " . (int) $this->config->get('config_customer_group_id') . " "
            ."OR ps.customer_group_id = o.customer_group_id"
            .") "
            ."AND ("
            ."(ps.date_start = '0000-00-00' OR ps.date_start < o.date_added) "
            ."AND (ps.date_end = '0000-00-00' OR ps.date_end > o.date_added)"
            .") "
            ."ORDER BY (ps.customer_group_id = o.customer_group_id) DESC, ps.priority ASC, ps.price ASC "
            ."LIMIT 1"
            .") AS special "
            ."FROM `" . DB_PREFIX . "order_product` AS op "
            ."INNER JOIN `" . DB_PREFIX . "product` AS p ON (p.product_id = op.product_id) "
            ."INNER JOIN `" . DB_PREFIX . "order` AS o ON (o.order_id = op.order_id) "
            ."INNER JOIN `" . DB_PREFIX . "order_total` AS ot ON (ot.order_id = op.order_id) "
            ."WHERE ot.code = 'shipping' "
            ."AND op.order_id = " . (int) $id
        );

//        var_dump($query);
//        die();
        return $query->rows;
    }

    public function getOrderHistory($id)
    {
        $query = $this->db->query(
            "SELECT * "
            ."FROM `" . DB_PREFIX . "order_history` h "
            ."LEFT JOIN `" . DB_PREFIX . "order_status` s ON h.`order_status_id` = s.`order_status_id` "
            ."WHERE h.`order_id` = " . (int) $id . " "
            ."AND s.`name` IS NOT NULL "
            ."GROUP BY h.`date_added` "
            ."ORDER BY h.`date_added` DESC "
        );
        return $query->rows;
    }

    public function OrderStatusList()
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "order_status` WHERE `language_id` = " . (int) $this->config->get('config_language_id')
        );
        return $query->rows;
    }

    public function ChangeOrderDelivery($address, $city, $order_id)
    {
        $sql = "UPDATE `" . DB_PREFIX . "order` "
            ."SET `shipping_address_1` = '" . $this->db->escape($address) . "'"
            .(!empty($city) ? ", `shipping_city` = '" . $this->db->escape($city) . "'" : "")
            ." WHERE `order_id` = " . (int) $order_id;
        return $this->db->query($sql);
    }

    public function getTotalSales($data = [])
    {
        $sql = "SELECT COALESCE(SUM(`total`), 0) AS total FROM `" . DB_PREFIX . "order` WHERE `order_status_id` > '0'";

        if (!empty($data['this_year'])) {
            $sql .= " AND DATE_FORMAT(`date_added`, '%Y') = DATE_FORMAT(NOW(), '%Y')";
        }

        $query = $this->db->query($sql);

        return $query->row['total'];
    }

    public function getTotalOrders($data = [])
    {
        if (isset($data['filter'])) {
            $sql = "SELECT `date_added` FROM `" . DB_PREFIX . "order` WHERE `order_status_id` > '0'";

            if ($data['filter'] == 'day') {
                $sql .= " AND DATE(`date_added`) = DATE(NOW())";
            } elseif ($data['filter'] == 'week') {
                $date_start = strtotime('-' . date('w') . ' days');
                $sql .= "AND DATE(`date_added`) >= DATE('" . $this->db->escape(date('Y-m-d', $date_start)) . "') ";
            } elseif ($data['filter'] == 'month') {
                $sql .= "AND DATE(`date_added`) >= '" . $this->db->escape(date('Y') . '-' . date('m') . '-1') . "' ";
            } elseif ($data['filter'] == 'year') {
                $sql .= "AND YEAR(`date_added`) = YEAR(NOW())";
            } else {
                return false;
            }
        } else {
            $sql = "SELECT COUNT(*) FROM `" . DB_PREFIX . "order` WHERE `order_status_id` > '0'";
        }
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getTotalCustomers($data = [])
    {
        if (isset($data['filter'])) {
            $sql = "SELECT `date_added` FROM `" . DB_PREFIX . "customer` ";
            if ($data['filter'] == 'day') {
                $sql .= " WHERE DATE(`date_added`) = DATE(NOW())";
            } elseif ($data['filter'] == 'week') {
                $date_start = strtotime('-' . date('w') . ' days');
                $sql .= "WHERE DATE(`date_added`) >= DATE('" . $this->db->escape(date('Y-m-d', $date_start)) . "') ";
            } elseif ($data['filter'] == 'month') {
                $sql .= "WHERE DATE(`date_added`) >= '" . $this->db->escape(date('Y') . '-' . date('m') . '-1') . "' ";
            } elseif ($data['filter'] == 'year') {
                $sql .= "WHERE YEAR(`date_added`) = YEAR(NOW()) ";
            } else {
                return false;
            }
        } else {
            $sql = "SELECT COUNT(*) FROM `" . DB_PREFIX . "customer` ";
        }
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getClients($data = [])
    {
        $sql = "SELECT COALESCE(SUM(o.`total`), 0) AS sum, COUNT(o.`total`) AS quantity, c.`firstname`, c.`lastname`, c.`date_added`, "
            ."c.`customer_id` "
            ."FROM `" . DB_PREFIX . "customer` AS c "
            ."LEFT JOIN `" . DB_PREFIX . "order` AS o ON c.`customer_id` = o.`customer_id` "
            ."WHERE c.`customer_id` != 0 ";

        if (!empty($data['fio'])) {
            $params = [];
            $newparam = explode(' ', $data['fio']);

            foreach ($newparam as $key => $value) {
                if ($value == '') {
                    unset($newparam[$key]);
                } else {
                    $params[] = $value;
                }
            }

            $sql .= " AND ("
                ."c.`firstname` LIKE '%" . $this->db->escape($params[0]) . "%' "
                ."OR c.`lastname` LIKE '%" . $this->db->escape($params[0]) . "%' ";

            foreach ($params as $param) {
                if ($param != $params[0]) {
                    $sql .= " OR c.`firstname` LIKE '%" . $this->db->escape($params[0]) . "%' "
                        ."OR c.`lastname` LIKE '%" . $this->db->escape($param) . "%' ";
                };
            }
            $sql .= ") ";
        }
        $sql .= " GROUP BY c.`customer_id`";

        if (!empty($data['order']) && in_array($data['order'], ['date_added', 'sum', 'quantity'])) {
            $sql .= " ORDER BY `" . $data['order'] . "` DESC";
        }

        $sql .= " LIMIT " . (int) $data['limit'] . " OFFSET " . (int) $data['page'];

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getClientInfo($id)
    {
        $sql = "SELECT COALESCE(SUM(o.`total`), 0) AS sum, COUNT(o.`total`) AS quantity, c.firstname, c.lastname, c.date_added, c.customer_id, "
            ."c.email, c.telephone "
            ."FROM `" . DB_PREFIX . "customer` AS c "
            ."LEFT JOIN `" . DB_PREFIX . "order` AS o ON c.customer_id = o.customer_id ";

        $sql .= " WHERE c.customer_id = " . (int) $id ;
        $sql .= " GROUP BY c.customer_id";

        $completed = $this->db->query(
            "SELECT COUNT(o.total) completed "
            ."FROM `" . DB_PREFIX . "order` AS o "
            ."LEFT JOIN `" . DB_PREFIX . "customer` AS c ON c.customer_id = o.customer_id "
            ."WHERE c.customer_id = " . (int) $id . " "
            ."AND o.order_status_id = 5 "
            ."GROUP BY c.customer_id"
        );

        $cancelled = $this->db->query(
            "SELECT COUNT(o.total) cancelled "
            ."FROM `" . DB_PREFIX . "order` AS o "
            ."LEFT JOIN `" . DB_PREFIX . "customer` AS c ON c.customer_id = o.customer_id "
            ."WHERE c.customer_id = " . (int) $id . " "
            ."AND o.order_status_id = 7 "
            ."GROUP BY c.customer_id"
        );

        $query = $this->db->query($sql);
        if (!empty($completed->row['completed'])) {
            $query->row['completed'] = $completed->row['completed'];
        } else {
            $query->row['completed'] = '0';
        }
        if (!empty($cancelled->row['cancelled'])) {
            $query->row['cancelled'] = $cancelled->row['cancelled'];
        } else {
            $query->row['cancelled'] = '0';
        }

        return $query->row;
    }

    public function getClientOrders($id, $sort)
    {
        if ($sort != 'cancelled' && $sort != 'completed') {
            $sql = "SELECT o.order_id, o.total, o.date_added, os.name "
                ."FROM `" . DB_PREFIX . "order` AS o "
                ."LEFT JOIN `" . DB_PREFIX . "order_status` AS os ON o.order_status_id = os.order_status_id "
                ."WHERE o.customer_id = " . (int) $id . " "
                ."GROUP BY o.order_id "
                ."ORDER BY " . $sort . " DESC"; //now it's safe
            $query = $this->db->query($sql);
        } elseif ($sort == 'cancelled') {
            $sql = "SELECT o.order_id, o.total, o.date_added, os.name "
                ."FROM `" . DB_PREFIX . "order` AS o "
                ."LEFT JOIN `" . DB_PREFIX . "order_status` AS os ON o.order_status_id = os.order_status_id "
                ."WHERE o.customer_id = " . (int) $id . " "
                ."AND os.order_status_id != 7 "
                ."GROUP BY o.order_id "
                ."ORDER BY o.date_added DESC";
            $query = $this->db->query(
                "SELECT o.order_id, o.total, o.date_added, os.name "
                ."FROM `" . DB_PREFIX . "order` AS o "
                ."LEFT JOIN `" . DB_PREFIX . "order_status` AS os ON o.order_status_id = os.order_status_id "
                ."WHERE o.customer_id = " . (int) $id . " "
                ."AND os.order_status_id = 7 "
                ."GROUP BY o.order_id "
                ."ORDER BY o.date_added DESC"
            );
            $cancelled = $this->db->query($sql);
            foreach ($cancelled->rows as $value) {
                $query->rows[] = $value;
            }
        } else { //completed
            $sql = "SELECT o.order_id, o.total, o.date_added, os.name "
                ."FROM `" . DB_PREFIX . "order` AS o "
                ."LEFT JOIN `" . DB_PREFIX . "order_status` AS os ON o.order_status_id = os.order_status_id "
                ."WHERE o.customer_id = " . (int) $id . " "
                ."AND os.order_status_id != 5 "
                ."GROUP BY o.order_id "
                ."ORDER BY o.date_added DESC";
            $query = $this->db->query(
                "SELECT o.order_id, o.total, o.date_added, os.name "
                ."FROM `" . DB_PREFIX . "order` AS o "
                ."LEFT JOIN `" . DB_PREFIX . "order_status` AS os ON o.order_status_id = os.order_status_id "
                ."WHERE o.customer_id = " . (int) $id . " "
                ."AND os.order_status_id = 5 "
                ."GROUP BY o.order_id "
                ."ORDER BY o.date_added DESC"
            );
            $cancelled = $this->db->query($sql);
            foreach ($cancelled->rows as $value) {
                $query->rows[] = $value;
            }
        }
        return $query->rows;
    }

    public function getProductsList($page, $limit, $name = '', $store_id)
    {
        $sql = "SELECT p.product_id, p.model, p.quantity, p.image, p.price, pd.name, p.tax_class_id "
            ."FROM `" . DB_PREFIX . "product` AS p "
            ."LEFT JOIN `" . DB_PREFIX . "product_description` pd ON p.product_id = pd.product_id "
            ."WHERE pd.language_id = " . (int) $this->config->get('config_language_id') . " ";
        if ($name != '') {
            $sql .= "AND (pd.name LIKE '%" . $this->db->escape($name) . "%' OR p.model LIKE '%" . $this->db->escape($name) . "%') ";
        }
        $sql .= "LIMIT " . (int) $limit . " OFFSET " . (int) $page;

        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getProductsByID($id)
    {
        $sql = "SELECT p.product_id, p.model, p.quantity, p.price, pd.name, p.tax_class_id, pd.description, p.sku, p.status, "
            ."ss.name stock_status_name "
            ."FROM `" . DB_PREFIX . "product` AS p "
            ."LEFT JOIN `" . DB_PREFIX . "product_description` pd ON p.product_id = pd.product_id "
            ."LEFT JOIN `" . DB_PREFIX . "stock_status` ss ON p.stock_status_id = ss.stock_status_id "
            ."WHERE pd.language_id = " . (int) $this->config->get('config_language_id') . " "
            ."AND p.product_id = " . (int) $id;
        $query = $this->db->query($sql);
        return $query->row;
    }

    public function getProductOptionsByID($id)
    {
        $sql = "SELECT  order_id, order_product_id, order_option_id, name, value, type "
            ."FROM `" . DB_PREFIX . "order_option`"
            ."WHERE order_id = " . (int) $id . " ";
        $query = $this->db->query($sql);

        return $query->rows;
    }


    public function getProductOptionsAddByID($id)
    {
        $sql = "SELECT pov.option_id option_id, pov.option_value_id option_value_id, ovd.name option_value_name, ovd.language_id language_id, "
            ."od.name option_name "
            ."FROM `" . DB_PREFIX . "product_option_value` AS pov "
            ."LEFT JOIN `" . DB_PREFIX . "option_value_description` AS ovd ON pov.option_value_id = ovd.option_value_id "
            ."LEFT JOIN `" . DB_PREFIX . "option_description` AS od ON pov.option_id = od.option_id "
            ."WHERE ovd.language_id = " . (int) $this->config->get('config_language_id') . " "
            ."AND od.language_id = " . (int) $this->config->get('config_language_id') . " "
            ."AND pov.product_id = " . (int) $id;
        $query = $this->db->query($sql);

//        print_r($query);
//        die();
        return $query->rows;
    }

    public function getProductImages($product_id)
    {
        $main_image = $this->db->query(
            "SELECT p.image, pd.description FROM `" . DB_PREFIX . "product` p "
            ."LEFT JOIN `" . DB_PREFIX . "product_description` pd ON p.product_id = pd.product_id "
            ."WHERE p.product_id = " . (int) $product_id . " "
            ."AND pd.language_id = " . (int) $this->config->get('config_language_id')
        );
        $all_images = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "product_image` WHERE product_id = " . (int) $product_id . " ORDER BY sort_order ASC"
        );

        $response['description'] = isset($main_image->row['description']) ? $main_image->row['description'] : '';
        $all_images->rows[] = ['product_image_id' => -1, 'image' => isset($main_image->row['image']) ? $main_image->row['image'] : ''];
        $response['images'] = array_reverse($all_images->rows);

        return $response;
    }

    public function getDefaultCurrency()
    {
        $sql = "SELECT c.value code FROM `" . DB_PREFIX . "setting` c WHERE c.key = 'config_currency'";
        $query = $this->db->query($sql);
        return $query->row['code'];
    }

    public function getUserCurrency()
    {
        $sql = "SELECT c.value code FROM `" . DB_PREFIX . "setting` c WHERE c.key = 'config_currency'";
        $query = $this->db->query($sql);
        return $query->row['code'];
    }

    // public function setUserDeviceToken($user_id, $token, $os_type, $device_name = null)
    // {
    //     $now = date("Y-m-d H:i:s");
    //     $sql = "INSERT INTO `" . DB_PREFIX . "user_device_mob_api` ("
    //         ."user_id, device_token, os_type, device_name, last_login, created_at"
    //         .") VALUES ("
    //         .(int) $user_id . ", "
    //         ."'" . $this->db->escape($token) . "', "
    //         ."'" . $this->db->escape($os_type) . "', "
    //         ."'" . $this->db->escape($device_name) . "', "
    //         ."'" . $now . "', "
    //         ."'" . $now . "'"
    //         .")";
    //     $this->db->query($sql);
    //     return;
    // }

    // public function updateUserDeviceTokenLastLogin($token)
    // {
    //     $now = date("Y-m-d H:i:s");
    //     $sql = "UPDATE `" . DB_PREFIX . "user_device_mob_api` "
    //         ."SET `last_login`='" . $now . "' "
    //         ."WHERE `device_token` = '" . $this->db->escape($token) . "'";
    //     $this->db->query($sql);
    //     return;
    // }

    // public function getUserDevices()
    // {
    //     $sql = "SELECT device_token, os_type FROM `" . DB_PREFIX . "user_device_mob_api`";
    //     $query = $this->db->query($sql);
    //     return $query->rows;
    // }

    // public function deleteUserDeviceToken($token)
    // {
    //     $sql = "DELETE FROM `" . DB_PREFIX . "user_device_mob_api` WHERE device_token = '" . $this->db->escape($token) . "'";
    //     $query = $this->db->query($sql);
    //     $sql = "SELECT * FROM `" . DB_PREFIX . "user_device_mob_api` WHERE device_token = '" . $this->db->escape($token) . "'";
    //     $query = $this->db->query($sql);
    //     return $query->rows;
    // }

    // public function findUserToken($token)
    // {
    //     $sql = "SELECT * FROM `" . DB_PREFIX . "user_device_mob_api` WHERE device_token = '" . $this->db->escape($token) . "'";
    //     $query = $this->db->query($sql);
    //     return $query->rows;
    // }

    // public function updateUserDeviceToken($old, $new)
    // {
    //     $sql = "UPDATE `" . DB_PREFIX . "user_device_mob_api` "
    //         ."SET device_token = '" . $this->db->escape($new) . "' "
    //         ."WHERE device_token = '" . $this->db->escape($old) . "';";

    //     $this->db->query($sql);

    //     $sql = "SELECT * FROM `" . DB_PREFIX . "user_device_mob_api` WHERE device_token = '" . $this->db->escape($new) . "';";
    //     $query = $this->db->query($sql);
    //     return $query->rows;
    // }

    public function setProductQuantity($quantity, $product_id)
    {
        $sql = "UPDATE `" . DB_PREFIX . "product` SET quantity = " . (int) $quantity . " WHERE product_id = " . (int) $product_id;

        $this->db->query($sql);

        $sql = "SELECT quantity FROM `" . DB_PREFIX . "product` WHERE product_id = " . (int) $product_id;
        $query = $this->db->query($sql);
        return $query->row['quantity'];
    }

    public function addProduct($data)
    {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "product` ("
            ."`model`, `sku`, `stock_status_id`, `quantity`, `status`, `price`, `date_added`"
            .") VALUES ("
            ."'" . $this->db->escape($data['model']) . "', "
            ."'" . $this->db->escape($data['sku']) . "', "
            .(int) $data['stock_status_id'] . ", "
            .(int) $data['quantity'] . ", "
            .(int) $data['status'] . ", "
            .(float) $data['price'] . ", "
            ."NOW()"
            .")"
        );

        $product_id = $this->db->getLastId();

        if (isset($data['image'])) {
            $this->db->query(
                "UPDATE `" . DB_PREFIX . "product` "
                ."SET image = '" . $this->db->escape($data['image']) . "' "
                ."WHERE product_id = " . (int) $product_id
            );
        }

        foreach ($data['product_description'] as $language_id => $value) {
            $this->db->query(
                "INSERT INTO `" . DB_PREFIX . "product_description` ("
                ."`product_id`, `language_id`, `name`, `description`"
                .") VALUES ("
                .(int) $product_id . ", "
                .(int) $language_id . ", "
                ."'" . $this->db->escape($value['name']) . "', "
                ."'" . $this->db->escape($value['description']) . "'"
                .")"
            );
        }

        if (isset($data['product_store'])) {
            $siteIds = array_diff($data['product_store'], ['']);
            foreach ($siteIds as $store_id) {
                $this->db->query(
                    "INSERT INTO `" . DB_PREFIX . "product_to_store` SET product_id = " . (int) $product_id . ", store_id = " . (int) $store_id
                );
            }
        }

        if (isset($data['product_attribute'])) {
            foreach ($data['product_attribute'] as $product_attribute) {
                if ($product_attribute['attribute_id']) {
                    // Removes duplicates
                    $this->db->query(
                        "DELETE FROM `" . DB_PREFIX . "product_attribute` "
                        ."WHERE product_id = " . (int) $product_id . " "
                        ."AND attribute_id = " . (int) $product_attribute['attribute_id']
                    );

                    foreach ($product_attribute['product_attribute_description'] as $language_id => $product_attribute_description) {
                        $this->db->query(
                            "DELETE FROM `" . DB_PREFIX . "product_attribute` "
                            ."WHERE product_id = " . (int) $product_id . " "
                            ."AND attribute_id = " . (int) $product_attribute['attribute_id'] . " "
                            ."AND language_id = " . (int) $this->config->get('config_language_id')
                        );
                        $this->db->query(
                            "INSERT INTO `" . DB_PREFIX . "product_attribute` ("
                            ."`product_id`, `attribute_id`, `language_id`, `text`"
                            .") VALUES ("
                            .(int) $product_id . ", "
                            .(int) $product_attribute['attribute_id'] . ", "
                            .(int) $this->config->get('config_language_id') . ", "
                            ."'" . $this->db->escape($product_attribute_description['text']) . "'"
                            .")"
                        );
                    }
                }
            }
        }

        /*
         * Add product options.
         */
        if (isset($data['product_option'])) {
            foreach ($data['product_option'] as $option_id => $option_value_ids) {
                /*
                 * Verify that the option id is present in the database.
                 */
                $option_id_is_correct_query = $this->db->query(
                    "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "option` WHERE option_id = " . (int) $option_id
                );
                $option_id_is_correct = $option_id_is_correct_query->row['total'];

                if ($option_id_is_correct) {
                    $this->db->query(
                        "INSERT INTO `" . DB_PREFIX . "product_option` ("
                        ."product_id, option_id"
                        .") VALUES ("
                        .(int) $product_id . ", "
                        .(int) $option_id
                        .")"
                    );
                    $product_option_id = $this->db->getLastId();

                    foreach ($option_value_ids as $option_value_id) {
                        /*
                         * Check if the given option id is allowed to be associated with the given option value id
                         */
                        $option_value_id_is_correct_query = $this->db->query(
                            "SELECT COUNT(*) AS total "
                            ."FROM `" . DB_PREFIX . "option_value` "
                            ."WHERE option_id = " . (int) $option_id . " "
                            ."AND option_value_id = " . (int) $option_value_id
                        );
                        $option_value_id_is_correct = $option_value_id_is_correct_query->row['total'];

                        if ($option_value_id_is_correct) {
                            /*
                             * Register the option id, option value id and the product id in the database.
                             */

                            $this->db->query(
                                "INSERT INTO `" . DB_PREFIX . "product_option_value` ("
                                ."`product_option_id`, `product_id`, `option_id`, `option_value_id`"
                                .") VALUES ("
                                .(int) $product_option_id . ", "
                                .(int) $product_id . ", "
                                .(int) $option_id . ", "
                                .(int) $option_value_id
                                .")"
                            );
                        }
                    }
                }
            }
        }

        if (isset($data['product_discount'])) {
            foreach ($data['product_discount'] as $product_discount) {
                $this->db->query(
                    "INSERT INTO `" . DB_PREFIX . "product_discount` ("
                    ."`product_id`, `customer_group_id`, `quantity`, `priority`, `price`, `date_start`, `date_end`"
                    .") VALUES ("
                    .(int) $product_id . ", "
                    .(int) $product_discount['customer_group_id'] . ", "
                    .(int) $product_discount['quantity'] . ", "
                    .(int) $product_discount['priority'] . ", "
                    .(float) $product_discount['price'] . ", "
                    ."'" . $this->db->escape($product_discount['date_start']) . "', "
                    ."'" . $this->db->escape($product_discount['date_end']) . "'"
                    .")"
                );
            }
        }

        if (isset($data['product_special'])) {
            foreach ($data['product_special'] as $product_special) {
                $this->db->query(
                    "INSERT INTO `" . DB_PREFIX . "product_special` ("
                    ."`product_id`, `customer_group_id`, `priority`, `price`, `date_start`, `date_end`"
                    .") VALUES ("
                    .(int) $product_id . ", "
                    .(int) $product_special['customer_group_id'] . ", "
                    .(int) $product_special['priority'] . ", "
                    .(float) $product_special['price'] . ", "
                    ."'" . $this->db->escape($product_special['date_start']) . "', "
                    ."'" . $this->db->escape($product_special['date_end']) . "'"
                    .")"
                );
            }
        }

        if (isset($data['product_image'])) {
            foreach ($data['product_image'] as $product_image) {
                $this->db->query(
                    "INSERT INTO `" . DB_PREFIX . "product_image` ("
                    ."`product_id`, `image`, `sort_order`"
                    .") VALUES ("
                    .(int) $product_id . ", "
                    ."'" . $this->db->escape($product_image) . "', "
                    ."0"
                    .")"
                );
            }
        }

        if (isset($data['product_download'])) {
            foreach ($data['product_download'] as $download_id) {
                $this->db->query(
                    "INSERT INTO `" . DB_PREFIX . "product_to_download` ("
                    ."`product_id`, `download_id`"
                    .") VALUES ("
                    .(int) $product_id . ", "
                    .(int) $download_id
                    .")"
                );
            }
        }

        if (isset($data['product_category'])) {
            foreach ($data['product_category'] as $category_id) {
                $this->db->query(
                    "INSERT INTO `" . DB_PREFIX . "product_to_category` ("
                    ."`product_id`, `category_id`"
                    .") VALUES ("
                    .(int) $product_id . ", "
                    .(int) $category_id
                    .")"
                );
            }
        }

        if (isset($data['product_filter'])) {
            foreach ($data['product_filter'] as $filter_id) {
                $this->db->query(
                    "INSERT INTO `" . DB_PREFIX . "product_filter` ("
                    ."`product_id`, `filter_id`"
                    .") VALUES ("
                    .(int) $product_id . ", "
                    .(int) $filter_id
                    .")"
                );
            }
        }

        if (isset($data['product_related'])) {
            foreach ($data['product_related'] as $related_id) {
                $this->db->query(
                    "DELETE FROM `" . DB_PREFIX . "product_related` "
                    ."WHERE product_id = " . (int) $product_id . " "
                    ."AND related_id = " . (int) $related_id
                );
                $this->db->query(
                    "INSERT INTO `" . DB_PREFIX . "product_related` ("
                    ."`product_id`, `related_id`"
                    .") VALUES ("
                    .(int) $product_id . ", "
                    .(int) $related_id
                    .")"
                );
                $this->db->query(
                    "DELETE FROM `" . DB_PREFIX . "product_related` "
                    ."WHERE product_id = " . (int) $related_id . " "
                    ."AND related_id = " . (int) $product_id
                );
                $this->db->query(
                    "INSERT INTO `" . DB_PREFIX . "product_related` ("
                    ."`product_id`, `related_id`"
                    .") VALUES ("
                    .(int) $related_id . ", "
                    .(int) $product_id
                    .")"
                );
            }
        }

        if (isset($data['product_reward'])) {
            foreach ($data['product_reward'] as $customer_group_id => $product_reward) {
                if ((int) $product_reward['points'] > 0) {
                    $this->db->query(
                        "INSERT INTO `" . DB_PREFIX . "product_reward` ("
                        ."`product_id`, `customer_group_id`, `points`"
                        .") VALUES ("
                        .(int) $product_id . ", "
                        .(int) $customer_group_id . ", "
                        .(int) $product_reward['points']
                        .")"
                    );
                }
            }
        }

        if (isset($data['product_layout'])) {
            foreach ($data['product_layout'] as $store_id => $layout_id) {
                $this->db->query(
                    "INSERT INTO `" . DB_PREFIX . "product_to_layout` ("
                    ."`product_id`, `store_id`, `layout_id`"
                    .") VALUES ("
                    .(int) $product_id . ", "
                    .(int) $store_id . ", "
                    .(int) $layout_id
                    .")"
                );
            }
        }
        /*
                if ($data['keyword']) {
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "url_alias` SET
                    query = 'product_id=" . (int) $product_id . "',
                    keyword = '" . $this->db->escape($data['keyword']) . "'");
                }
        */
        if (isset($data['product_recurring'])) {
            foreach ($data['product_recurring'] as $recurring) {
                $this->db->query(
                    "INSERT INTO `" . DB_PREFIX . "product_recurring` ("
                    ."`product_id`, `customer_group_id`, `recurring_id`"
                    .") VALUES ("
                    .(int) $product_id . ", "
                    .(int) $recurring['customer_group_id'] . ", "
                    .(int) $recurring['recurring_id']
                    .")"
                );
            }
        }

        $this->cache->delete('product');

        return $product_id;
    }

    public function editProduct($product_id, $data)
    {
        // Обновление основной таблицы product
        if (!isset($data['price_old'])) {
            $this->db->query(
                "UPDATE `" . DB_PREFIX . "product` "
                . "SET model = '" . $this->db->escape($data['model']) . "', "
                . "sku = '" . $this->db->escape($data['sku']) . "', "
                . "quantity = " . (int) $data['quantity'] . ", "
                . "price = " . (float) $data['price'] . ", "
                . "status = " . (int) $data['status'] . ", "
                . "stock_status_id = " . (int) $data['stock_status_id'] . ", "
                . "date_modified = NOW() "
                . "WHERE product_id = " . (int) $product_id
            );
        } else {
            $this->db->query(
                "UPDATE `" . DB_PREFIX . "product` "
                . "SET model = '" . $this->db->escape($data['model']) . "', "
                . "sku = '" . $this->db->escape($data['sku']) . "', "
                . "quantity = " . (int) $data['quantity'] . ", "
                . "status = " . (int) $data['status'] . ", "
                . "stock_status_id = " . (int) $data['stock_status_id'] . ", "
                . "date_modified = NOW() "
                . "WHERE product_id = " . (int) $product_id
            );
        }

        if (isset($data['image'])) {
            $this->db->query(
                "UPDATE `" . DB_PREFIX . "product` "
                . "SET image = '" . $this->db->escape($data['image']) . "' "
                . "WHERE product_id = " . (int) $product_id
            );
        }

        // Обновление таблицы product_description
        foreach ($data['product_description'] as $language_id => $value) {
            $sql = "UPDATE `" . DB_PREFIX . "product_description` SET ";
            $fields = array();

            if (isset($value['name'])) {
                $fields[] = "name = '" . $this->db->escape($value['name']) . "'";
            }
            if (isset($value['description'])) {
                $fields[] = "description = '" . $this->db->escape($value['description']) . "'";
            }
            if (isset($value['meta_description'])) {
                $fields[] = "meta_description = '" . $this->db->escape($value['meta_description']) . "'";
            }
            if (isset($value['meta_keyword'])) {
                $fields[] = "meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'";
            }
            if (isset($value['meta_title'])) {
                $fields[] = "meta_title = '" . $this->db->escape($value['meta_title']) . "'";
            }
            if (isset($value['tag'])) {
                $fields[] = "tag = '" . $this->db->escape($value['tag']) . "'";
            }

            if ($fields) {
                $sql .= implode(", ", $fields);
                $sql .= " WHERE product_id = " . (int) $product_id . " AND language_id = " . (int) $language_id;
                $this->db->query($sql);
            }
        }

        // Обновление других связанных таблиц (пример обновления product_to_store)
        if (isset($data['product_store'])) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "product_to_store` WHERE product_id = " . (int) $product_id);

            foreach ($data['product_store'] as $store_id) {
                $this->db->query(
                    "INSERT INTO `" . DB_PREFIX . "product_to_store` ("
                    . "`product_id`, `store_id`"
                    . ") VALUES ("
                    . (int) $product_id . ", "
                    . (int) $store_id
                    . ")"
                );
            }
        }

        // Повторите аналогично для других таблиц

        // Очистка кэша
        $this->cache->delete('product');
    }

    public function updateProduct($data = [])
    {
        // print_r($data);die;
        foreach ($data as $table => $fields_data) {
            if ($table != 'categories') {
                if (!empty($fields_data) && is_array($fields_data)) {
                    $update = '';
                    $values = '';
                    $fields = '';
                    if (!empty($data['product_id'])) {
                        $values = (int) $data['product_id'] . ', ';
                        $fields = 'product_id, ';
                    }
                    if ($table == 'product_description') {
                        $values .= (int) $data['language_id'] . ', ';
                        $fields .= 'language_id, ';
                    }
                    foreach ($fields_data as $key => $value) {
                        $values .= "'" . $this->db->escape($value) . "', ";
                        $fields .= $key . ', ';
                        $update .= $key . " = '" . $this->db->escape($value) . "', ";
                    }
                    if ($table == 'product') {
                        if (empty($data['product_id'])) {
                            $values .= 'NOW(), ';
                            $fields .= 'date_added, ';
                        }
                        $values .= 'NOW(), ';
                        $fields .= 'date_modified, ';
                        $update .= 'date_modified = NOW(), ';
                    }
                    if (strlen($fields)) {
                        $fields = substr($fields, 0, strlen($fields) - 2);
                    }
                    if (strlen($values)) {
                        $values = substr($values, 0, strlen($values) - 2);
                    }
                    if (strlen($update)) {
                        $update = substr($update, 0, strlen($update) - 2);
                    }
                    $sql = "INSERT INTO `" . DB_PREFIX . $table . "` ("
                        .$fields
                        .") VALUES ("
                        .$values
                        .") ON DUPLICATE KEY UPDATE " . $update;
                    // print_r($sql);die;
                    $this->db->query($sql);
                }
            }
        }

        if (!empty($data['categories'])) {
            // $sql = "DELETE FROM `" . DB_PREFIX . "product_to_category` WHERE product_id =" . (int) $data['product_id'];
            $this->db->query($sql);
            foreach ($data['categories'] as $fd) {
                $sql = "INSERT INTO `" . DB_PREFIX . "product_to_category` ("
                    ."`product_id`, `category_id`"
                    .") VALUES ("
                    .(int) $data['product_id'] . ", "
                    .(int) $fd
                    .")";
                $this->db->query($sql);
            }
        }
    }

    public function addProductImages($new_images, $product_id)
    {
        $images = [];
        foreach ($new_images as $image) {
            $this->db->query(
                "INSERT INTO `" . DB_PREFIX . "product_image` ("
                ."`product_id`, `image`"
                .") VALUES ("
                .(int) $product_id . ", "
                ."'" . $this->db->escape($image) . "'"
                .")"
            );
            $val = [];
            $val['image_id'] = $this->db->getLastId();
            $val['image'] = $image;
            $images[] = $val;
        }
        return $images;
    }

    public function removeProductImages($removed_image, $product_id)
    {
        $this->db->query(
            "DELETE FROM `" . DB_PREFIX . "product_image` "
            ."WHERE product_id = " . (int) $product_id . " "
            ."AND image = '" . $this->db->escape($removed_image) . "'"
        );
    }

    public function removeProductImageById($image_id, $product_id)
    {
        $this->db->query(
            "DELETE FROM `" . DB_PREFIX . "product_image` "
            ."WHERE product_id = " . (int) $product_id . " "
            ."AND product_image_id = " . (int) $image_id
        );
    }

    public function removeProductMainImage($product_id)
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "product` SET image = '' WHERE product_id = " . (int) $product_id);
        $sql = "SELECT image FROM `" . DB_PREFIX . "product` WHERE product_id = " . (int) $product_id;
        $query = $this->db->query($sql);
        return $query->row['image'];
    }

    public function getStockStatuses()
    {
        $query = $this->db->query(
            "SELECT stock_status_id status_id, name "
            ."FROM `" . DB_PREFIX . "stock_status` "
            ."WHERE language_id = " . (int) $this->config->get('config_language_id')
        );
        return $query->rows;
    }

    public function setMainImage($main_image, $product_id)
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "product` "
            ."SET image = '" . $this->db->escape($main_image) . "' "
            ."WHERE product_id = " . (int) $product_id
        );

        $sql = "SELECT image FROM `" . DB_PREFIX . "product` "
            ."WHERE product_id = " . (int) $product_id;
        $query = $this->db->query($sql);
        return $query->row['image'];
    }

    public function setMainImageByImageId($image_id, $product_id)
    {
        $new_main_image = $this->db->query(
            "SELECT image FROM `" . DB_PREFIX . "product_image` "
            ."WHERE product_id = " . (int) $product_id . " "
            ."AND product_image_id = " . (int) $image_id
        )->row['image'];

        $old_main_image = $this->db->query(
            "SELECT image FROM `" . DB_PREFIX . "product` "
            ."WHERE product_id = " . (int) $product_id
        )->row['image'];

        $this->db->query(
            "UPDATE `" . DB_PREFIX . "product` "
            ."SET image = '" . $this->db->escape($new_main_image) . "' "
            ."WHERE product_id = " . (int) $product_id
        );

        if (trim($old_main_image) != "") {
            $sql = "UPDATE `" . DB_PREFIX . "product_image` "
                ."SET image = '" . $this->db->escape($old_main_image) . "' "
                ."WHERE product_id = " . (int) $product_id . " "
                ."AND product_image_id = " . (int) $image_id;
            $this->db->query($sql);
        } else {
            $sql = "DELETE FROM `" . DB_PREFIX . "product_image` WHERE product_image_id = " . (int) $image_id;
            $this->db->query($sql);
        }
    }

    public function getProductCategoriesMain($product_id)
    {
        $query = $this->db->query(
            "SELECT cd.name, cd.category_id, c.parent_id "
            ."FROM `" . DB_PREFIX . "product_to_category` ptc "
            ."LEFT JOIN `" . DB_PREFIX . "category` c ON ptc.category_id = c.category_id "
            ."LEFT JOIN `" . DB_PREFIX . "category_description` cd ON c.category_id = cd.category_id "
            ."WHERE cd.language_id = " . (int) $this->config->get('config_language_id') . " "
            ."AND ptc.product_id = " . (int) $product_id . " "
            ."LIMIT 0, 1"
        );
        $return = $query->rows;

        return $return;
    }

    public $ar = [];
    public $categories = [];
    public function getProductCategories($product_id)
    {
        $query = $this->db->query(
            "SELECT cd.name, cd.category_id, c.parent_id "
            ."FROM `" . DB_PREFIX . "product_to_category` ptc "
            ."LEFT JOIN `" . DB_PREFIX . "category` c ON ptc.category_id = c.category_id "
            ."LEFT JOIN `" . DB_PREFIX . "category_description` cd ON c.category_id = cd.category_id "
            ."WHERE cd.language_id = " . (int) $this->config->get('config_language_id') . " "
            ."AND ptc.product_id = " . (int) $product_id
        );
        $cats = $query->rows;

        $query = $this->db->query(
            "SELECT cd.name, cd.category_id category_id,c.parent_id "
            ."FROM `" . DB_PREFIX . "category` c "
            ."LEFT JOIN `" . DB_PREFIX . "category_description` cd ON c.category_id = cd.category_id "
            ."WHERE cd.language_id = " . (int) $this->config->get('config_language_id'). " "
            ."ORDER BY cd.category_id ASC "
        );

        $categories = $query->rows;

        foreach ($categories as $cat) {
            $this->categories[$cat['category_id']] = $cat;
        }

        $return = [];
        foreach ($cats as $one) {
            $this->ar = [];
            $category = [];
            $category['category_id'] = $one['category_id'];
            $category['name'] = $this->categoryTree($one['category_id']);
            $return[] = $category;
        }

        foreach ($return as $k => $one) {
            $name = implode(' - ', array_reverse($one['name']));
            $return[$k]['name'] = $name;
        }
        sort($return);
        return $return;
    }

    public function categoryTree($id)
    {
        if ($this->categories[$id]['parent_id'] != 0) {
            $this->ar[] = $this->categories[$id]['name'];
            $this->categoryTree($this->categories[$id]['parent_id']);
        } else {
            $this->ar[] = $this->categories[$id]['name'];
        }
        return $this->ar;
    }

    public function getCategories()
    {
        $query = $this->db->query(
            "SELECT cd.name, cd.category_id "
            ."FROM `" . DB_PREFIX . "category` c "
            ."INNER JOIN `" . DB_PREFIX . "category_description` cd ON c.category_id = cd.category_id "
            ."WHERE c.top = 1 "
            ."AND cd.language_id = " . (int) $this->config->get('config_language_id')
        );

        $categories = $query->rows;

        if (empty($categories)) {
            $query = $this->db->query(
                "SELECT cd.name, cd.category_id "
                ."FROM `" . DB_PREFIX . "category` c "
                ."INNER JOIN `" . DB_PREFIX . "category_description` cd ON c.category_id = cd.category_id "
                ."WHERE cd.language_id = " . (int) $this->config->get('config_language_id')
            );

            $categories = $query->rows;
        }

        $query = $this->db->query("SELECT DISTINCT parent_id FROM `" . DB_PREFIX . "category`");
        $parents = $query->rows;
        $response = $this->getParentsCategories($categories, $parents);
        return $response;
    }

    public function getParentsCategories($categories, $parents)
    {
        $array = array_map(function ($v) {
            return $v['parent_id'];
        }, $parents);

        return array_map(function ($one) use ($array) {
            return [
                'name' => $one['name'],
                'category_id' => $one['category_id'],
                'parent' => in_array($one['category_id'], $array),
            ];
        }, $categories);
    }

    public function getCategoriesById($id)
    {
        $query = $this->db->query(
            "SELECT cd.name, cd.category_id category_id "
            ."FROM `" . DB_PREFIX . "category` c "
            ."LEFT JOIN `" . DB_PREFIX . "category_description` cd ON c.category_id = cd.category_id "
            ."WHERE c.parent_id = " . (int) $id . " "
            ."AND cd.language_id = " . (int) $this->config->get('config_language_id')
        );

        $categories = $query->rows;

        $query = $this->db->query("SELECT DISTINCT parent_id FROM `" . DB_PREFIX . "category`");
        $parents = $query->rows;
        $response = $this->getParentsCategories($categories, $parents);
        return $response;
    }

    public function getSubstatus()
    {
        $query = $this->db->query(
            "SELECT name , stock_status_id as status_id "
            ."FROM `" . DB_PREFIX . "stock_status` "
            ."WHERE language_id = " . (int) $this->config->get('config_language_id')
        );
        return $query->rows;
    }

    public function getOrder($order_id)
    {
        $order_query = $this->db->query(
            "SELECT *, ("
            ."SELECT os.name "
            ."FROM `" . DB_PREFIX . "order_status` os "
            ."WHERE os.order_status_id = o.order_status_id "
            ."AND os.language_id = o.language_id"
            .") AS order_status "
            ."FROM `" . DB_PREFIX . "order` o "
            ."WHERE o.order_id = " . (int) $order_id
        );

        if ($order_query->num_rows) {
            $country_query = $this->db->query(
                "SELECT * "
                ."FROM `" . DB_PREFIX . "country` "
                ."WHERE country_id = " . (int) $order_query->row['payment_country_id']
            );

            if ($country_query->num_rows) {
                $payment_iso_code_2 = $country_query->row['iso_code_2'];
                $payment_iso_code_3 = $country_query->row['iso_code_3'];
            } else {
                $payment_iso_code_2 = '';
                $payment_iso_code_3 = '';
            }

            $zone_query = $this->db->query(
                "SELECT * FROM `" . DB_PREFIX . "zone` WHERE zone_id = " . (int) $order_query->row['payment_zone_id']
            );

            if ($zone_query->num_rows) {
                $payment_zone_code = $zone_query->row['code'];
            } else {
                $payment_zone_code = '';
            }

            $country_query = $this->db->query(
                "SELECT * FROM `" . DB_PREFIX . "country` WHERE country_id = " . (int) $order_query->row['shipping_country_id']
            );

            if ($country_query->num_rows) {
                $shipping_iso_code_2 = $country_query->row['iso_code_2'];
                $shipping_iso_code_3 = $country_query->row['iso_code_3'];
            } else {
                $shipping_iso_code_2 = '';
                $shipping_iso_code_3 = '';
            }

            $zone_query = $this->db->query(
                "SELECT * FROM `" . DB_PREFIX . "zone` WHERE zone_id = " . (int) $order_query->row['shipping_zone_id']
            );

            if ($zone_query->num_rows) {
                $shipping_zone_code = $zone_query->row['code'];
            } else {
                $shipping_zone_code = '';
            }

            $this->load->model('localisation/language');

            $language_info = $this->model_localisation_language->getLanguage($order_query->row['language_id']);

            if ($language_info) {
                $language_code = $language_info['code'];
            } else {
                $language_code = $this->config->get('config_language');
            }

            return [
                'order_id' => $order_query->row['order_id'],
                'invoice_no' => $order_query->row['invoice_no'],
                'invoice_prefix' => $order_query->row['invoice_prefix'],
                'store_id' => $order_query->row['store_id'],
                'store_name' => $order_query->row['store_name'],
                'store_url' => $order_query->row['store_url'],
                'customer_id' => $order_query->row['customer_id'],
                'firstname' => $order_query->row['firstname'],
                'lastname' => $order_query->row['lastname'],
                'email' => $order_query->row['email'],
                'telephone' => $order_query->row['telephone'],
                'custom_field' => json_decode($order_query->row['custom_field'], true),
                'payment_firstname' => $order_query->row['payment_firstname'],
                'payment_lastname' => $order_query->row['payment_lastname'],
                'payment_company' => $order_query->row['payment_company'],
                'payment_address_1' => $order_query->row['payment_address_1'],
                'payment_address_2' => $order_query->row['payment_address_2'],
                'payment_postcode' => $order_query->row['payment_postcode'],
                'payment_city' => $order_query->row['payment_city'],
                'payment_zone_id' => $order_query->row['payment_zone_id'],
                'payment_zone' => $order_query->row['payment_zone'],
                'payment_zone_code' => $payment_zone_code,
                'payment_country_id' => $order_query->row['payment_country_id'],
                'payment_country' => $order_query->row['payment_country'],
                'payment_iso_code_2' => $payment_iso_code_2,
                'payment_iso_code_3' => $payment_iso_code_3,
                'payment_address_format' => $order_query->row['payment_address_format'],
                'payment_custom_field' => json_decode($order_query->row['payment_custom_field'], true),
                'payment_method' => $order_query->row['payment_method'],
                'payment_code' => $order_query->row['payment_code'],
                'shipping_firstname' => $order_query->row['shipping_firstname'],
                'shipping_lastname' => $order_query->row['shipping_lastname'],
                'shipping_company' => $order_query->row['shipping_company'],
                'shipping_address_1' => $order_query->row['shipping_address_1'],
                'shipping_address_2' => $order_query->row['shipping_address_2'],
                'shipping_postcode' => $order_query->row['shipping_postcode'],
                'shipping_city' => $order_query->row['shipping_city'],
                'shipping_zone_id' => $order_query->row['shipping_zone_id'],
                'shipping_zone' => $order_query->row['shipping_zone'],
                'shipping_zone_code' => $shipping_zone_code,
                'shipping_country_id' => $order_query->row['shipping_country_id'],
                'shipping_country' => $order_query->row['shipping_country'],
                'shipping_iso_code_2' => $shipping_iso_code_2,
                'shipping_iso_code_3' => $shipping_iso_code_3,
                'shipping_address_format' => $order_query->row['shipping_address_format'],
                'shipping_custom_field' => json_decode($order_query->row['shipping_custom_field'], true),
                'shipping_method' => $order_query->row['shipping_method'],
                'shipping_code' => $order_query->row['shipping_code'],
                'comment' => $order_query->row['comment'],
                'total' => $order_query->row['total'],
                'order_status_id' => $order_query->row['order_status_id'],
                'order_status' => $order_query->row['order_status'],
                'affiliate_id' => $order_query->row['affiliate_id'],
                'commission' => $order_query->row['commission'],
                'language_id' => $order_query->row['language_id'],
                'language_code' => $language_code,
                'currency_id' => $order_query->row['currency_id'],
                'currency_code' => $order_query->row['currency_code'],
                'currency_value' => $order_query->row['currency_value'],
                'ip' => $order_query->row['ip'],
                'forwarded_ip' => $order_query->row['forwarded_ip'],
                'user_agent' => $order_query->row['user_agent'],
                'accept_language' => $order_query->row['accept_language'],
                'date_added' => $order_query->row['date_added'],
                'date_modified' => $order_query->row['date_modified']
            ];
        } else {
            return false;
        }
    }

    public function getOrderTotals($order_id)
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "order_total` WHERE order_id = " . (int) $order_id . " ORDER BY sort_order ASC"
        );
        return $query->rows;
    }

    public function getOrderOptions($order_id, $order_product_id)
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "order_option` "
            ."WHERE order_id = " . (int) $order_id . " "
            ."AND order_product_id = " . (int) $order_product_id
        );
        return $query->rows;
    }

    public function getOrderProductsNew($order_id)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE order_id = " . (int) $order_id);
        return $query->rows;
    }

    public function addOrderHistory($order_id, $order_status_id, $comment = '', $notify = false, $override = false)
    {
        $order_info = $this->getOrder($order_id);
        if ($order_info) {
            // Fraud Detection
            $this->load->model('account/customer');

            $customer_info = $this->model_account_customer->getCustomer($order_info['customer_id']);

            if ($customer_info && $customer_info['safe']) {
                $safe = true;
            } else {
                $safe = false;
            }

            // Only do the fraud check if the customer is not on the safe list and the order status is changing into the complete or
            // process order status
            if (
                !$safe
                && !$override
                && in_array(
                    $order_status_id,
                    array_merge($this->config->get('config_processing_status'),
                        $this->config->get('config_complete_status'))
                )
            ) {
                // Anti-Fraud
                $this->load->model('setting/extension');

                $extensions = $this->model_setting_extension->getExtensions('fraud');

                foreach ($extensions as $extension) {
                    if ($this->config->get('fraud_' . $extension['code'] . '_status')) {
                        $this->load->model('extension/fraud/' . $extension['code']);

                        if (property_exists($this->{'model_extension_fraud_' . $extension['code']}, 'check')) {
                            $fraud_status_id = $this->{'model_extension_fraud_' . $extension['code']}->check($order_info);

                            if ($fraud_status_id) {
                                $order_status_id = $fraud_status_id;
                            }
                        }
                    }
                }
            }

            // If current order status is not processing or complete but new status is processing or complete then commence completing the order
            if (
                !in_array(
                    $order_info['order_status_id'],
                    array_merge($this->config->get('config_processing_status'),
                        $this->config->get('config_complete_status'))
                )
                && in_array(
                    $order_status_id,
                    array_merge($this->config->get('config_processing_status'), $this->config->get('config_complete_status'))
                )
            ) {
                // Redeem coupon, vouchers and reward points
                $order_totals = $this->getOrderTotals($order_id);

                foreach ($order_totals as $order_total) {
                    $this->load->model('extension/total/' . $order_total['code']);

                    if (property_exists($this->{'model_extension_total_' . $order_total['code']}, 'confirm')) {
                        // Confirm coupon, vouchers and reward points
                        $fraud_status_id = $this->{'model_extension_total_' . $order_total['code']}->confirm($order_info, $order_total);

                        // If the balance on the coupon, vouchers and reward points is not enough to cover the transaction or has already been used
                        // then the fraud order status is returned.
                        if ($fraud_status_id) {
                            $order_status_id = $fraud_status_id;
                        }
                    }
                }

                // Stock subtraction
                $order_products = $this->getOrderProductsNew($order_id);

                foreach ($order_products as $order_product) {
                    $this->db->query(
                        "UPDATE `" . DB_PREFIX . "product` "
                        ."SET quantity = (quantity - " . (int) $order_product['quantity'] . ") "
                        ."WHERE product_id = " . (int) $order_product['product_id'] . " "
                        ."AND subtract = 1"
                    );

                    $order_options = $this->getOrderOptions($order_id, $order_product['order_product_id']);

                    foreach ($order_options as $order_option) {
                        $this->db->query(
                            "UPDATE `" . DB_PREFIX . "product_option_value` "
                            ."SET quantity = (quantity - " . (int) $order_product['quantity'] . ") "
                            ."WHERE product_option_value_id = " . (int) $order_option['product_option_value_id'] . " "
                            ."AND subtract = 1"
                        );
                    }
                }

                // Add commission if sale is linked to affiliate referral.
                if ($order_info['affiliate_id'] && $this->config->get('config_affiliate_auto')) {
                    $this->load->model('account/customer');

                    if (!$this->model_account_customer->getTotalTransactionsByOrderId($order_id)) {
                        $this->model_account_customer->addTransaction(
                            $order_info['affiliate_id'],
                            $this->language->get('text_order_id') . ' #' . $order_id,
                            $order_info['commission'],
                            $order_id
                        );
                    }
                }
            }

            // Update the DB with the new statuses
            $this->db->query(
                "UPDATE `" . DB_PREFIX . "order` "
                ."SET order_status_id = " . (int) $order_status_id . ", "
                ."date_modified = NOW() "
                ."WHERE order_id = " . (int) $order_id
            );

            $this->db->query(
                "INSERT INTO `" . DB_PREFIX . "order_history` "
                ."SET order_id = " . (int) $order_id . ", "
                ."order_status_id = " . (int) $order_status_id . ", "
                ."notify = " . (int) $notify . ", "
                ."comment = '" . $this->db->escape($comment) . "', "
                ."date_added = NOW()"
            );

            // If old order status is the processing or complete status but new status is not then commence restock, and remove coupon,
            // voucher and reward history
            if (
                in_array(
                    $order_info['order_status_id'],
                    array_merge($this->config->get('config_processing_status'), $this->config->get('config_complete_status'))
                )
                && !in_array(
                    $order_status_id,
                    array_merge($this->config->get('config_processing_status'), $this->config->get('config_complete_status'))
                )
            ) {
                // Restock
                $order_products = $this->getOrderProductsNew($order_id);

                foreach($order_products as $order_product) {
                    $this->db->query(
                        "UPDATE `" . DB_PREFIX . "product` "
                        ."SET quantity = (quantity + " . (int) $order_product['quantity'] . ") "
                        ."WHERE product_id = " . (int) $order_product['product_id'] . " "
                        ."AND subtract = 1"
                    );

                    $order_options = $this->getOrderOptions($order_id, $order_product['order_product_id']);

                    foreach ($order_options as $order_option) {
                        $this->db->query(
                            "UPDATE `" . DB_PREFIX . "product_option_value` "
                            ."SET quantity = (quantity + " . (int) $order_product['quantity'] . ") "
                            ."WHERE product_option_value_id = " . (int) $order_option['product_option_value_id'] . " "
                            ."AND subtract = 1"
                        );
                    }
                }

                // Remove coupon, vouchers and reward points history
                $order_totals = $this->getOrderTotals($order_id);

                foreach ($order_totals as $order_total) {
                    $this->load->model('extension/total/' . $order_total['code']);

                    if (property_exists($this->{'model_extension_total_' . $order_total['code']}, 'unconfirm')) {
                        $this->{'model_extension_total_' . $order_total['code']}->unconfirm($order_id);
                    }
                }

                // Remove commission if sale is linked to affiliate referral.
                if ($order_info['affiliate_id']) {
                    $this->load->model('account/customer');

                    $this->model_account_customer->deleteTransactionByOrderId($order_id);
                }
            }

            $this->cache->delete('product');
        }
    }

    public function getLanguages()
    {
        $query = $this->db->query("SELECT c.language_id as id, c.code, c.name FROM `" . DB_PREFIX . "language` c WHERE c.status = 1");
        return $query->rows;
    }


    public function getDefaultProductAttributes()
    {
        $query = $this->db->query(
            "SELECT od.attribute_id as attribute_id, od.name as attribute, ogd.name as category "
            ."FROM `" . DB_PREFIX . "attribute` oa "
            ."JOIN `" . DB_PREFIX . "attribute_description` od ON oa.`attribute_id` = od.`attribute_id` "
            ."JOIN `" . DB_PREFIX . "attribute_group_description` ogd ON oa.`attribute_group_id` = ogd.`attribute_group_id` "
            ."WHERE od.`language_id` = " . (int) $this->config->get('config_language_id') . " "
            ."AND ogd.`language_id` = " . (int) $this->config->get('config_language_id')
        );

        return $query->rows;
    }

    public function getReviewComment($product_id)
    {
        if(isset($product_id) && !empty($product_id)) {

            $query = $this->db->query("SELECT review_id, author, status, rating, text, date_added FROM " . DB_PREFIX . "review WHERE product_id = ". (int)$product_id);

        } else {
            $limit = 33;
            //$query = $this->db->query("SELECT product_id, review_id, author, status, rating, text, date_added FROM " . DB_PREFIX . "review ORDER BY date_added DESC LIMIT " . (int)$limit);
            $query = $this->db->query(
                "SELECT od.product_id as product_id, od.name, oa.review_id, oa.author, oa.status, oa.rating, oa.text, oa.date_added"
                ." FROM `" . DB_PREFIX . "review` oa "
                ."JOIN `" . DB_PREFIX . "product_description` od ON oa.`product_id` = od.`product_id`"
                ."WHERE od.`language_id` = " . (int) $this->config->get('config_language_id') . " "
                ."ORDER BY date_added DESC LIMIT " . (int)$limit
            );
        }
//        echo(
//            "SELECT od.product_id as product_id, oa.review_id, oa.author, oa.status, oa.rating, oa.text, oa.date_added"
//            ."FROM `" . DB_PREFIX . "review` oa "
//            ."JOIN `" . DB_PREFIX . "product_description` od ON oa.`product_id` = od.`product_id` "
//            ."WHERE od.`language_id` = " . (int) $this->config->get('config_language_id') . " "
//            ."ORDER BY date_added DESC LIMIT " . (int)$limit
//        );


        return $query->rows;
    }

    public function getNameProduct($product_id)
    {
        $query = $this->db->query("SELECT name FROM " . DB_PREFIX . "product_description WHERE product_id = ". (int)$product_id);

        return $query->rows;
    }

    public function getOption($value, $option_id)
    {



        if(isset($option_id)) {

            $query = $this->db->query("SELECT option_id, language_id, name FROM " . DB_PREFIX . "option_value_description WHERE language_id =". (int)$this->config->get('config_language_id')." AND option_value_id = ". (int)$option_id);

            return $query->row;
        }


        if(isset($value) && !empty($value)) {

//            $query = $this->db->query(
//                "SELECT d.product_option_value_id, d.product_id, d.price, d.option_id, d.quantity, d.product_option_id, d.option_value_id, c.name FROM `"
//                . DB_PREFIX ."product_option_value` d JOIN `"
//                .DB_PREFIX."option_value_description` c ON d.`option_value_id` = c.`option_value_id` WHERE product_id = ". (int)$value." AND language_id = ". (int) $this->config->get('config_language_id'));

            $product_option_data = array();

            $product_option_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_option` po LEFT JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id) LEFT JOIN `" . DB_PREFIX . "option_description` od ON (o.option_id = od.option_id) WHERE po.product_id = '" . (int)$value . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'");

            foreach ($product_option_query->rows as $product_option) {
                $product_option_value_data = array();

                $product_option_value_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value ov ON(pov.option_value_id = ov.option_value_id) WHERE pov.product_option_id = '" . (int)$product_option['product_option_id'] . "' ORDER BY ov.sort_order ASC");

                foreach ($product_option_value_query->rows as $product_option_value) {

                    $query = $this->db->query("SELECT name FROM " . DB_PREFIX . "option_value_description WHERE language_id =". (int)$this->config->get('config_language_id')." AND option_value_id = ". (int)$product_option_value['option_value_id']);


                    $product_option_value_data[] = array(
                        'product_option_value_id' => $product_option_value['product_option_value_id'],
                        'option_value_id'         => $product_option_value['option_value_id'],
                        'quantity'                => $product_option_value['quantity'],
                        'subtract'                => $product_option_value['subtract'],
                        'price'                   => $product_option_value['price'],
                        'name'                    => $query->row['name'],
                        'price_prefix'            => $product_option_value['price_prefix'],
                        'points'                  => $product_option_value['points'],
                        'points_prefix'           => $product_option_value['points_prefix'],
                        'weight'                  => $product_option_value['weight'],
                        'weight_prefix'           => $product_option_value['weight_prefix']
                    );
                }

                $product_option_data[] = array(
                    'product_option_id'    => $product_option['product_option_id'],
                    'product_option_value' => $product_option_value_data,
                    'option_id'            => $product_option['option_id'],
                    'name'                 => $product_option['name'],
                    'type'                 => $product_option['type'],
                    'value'                => $product_option['value'],
                    'required'             => $product_option['required']
                );
            }

            return $product_option_data;

        } else {

            $query = $this->db->query("SELECT option_id, language_id, name FROM " . DB_PREFIX . "option_description WHERE language_id =". (int)$this->config->get('config_language_id'));
        }

        return $query->rows;
    }

    public function getClientStore($id_store)
    {
        $query = $this->db->query("SELECT customer_id,firstname,lastname,email,telephone FROM " . DB_PREFIX . "customer WHERE store_id = ". (int)$id_store);

        return $query->rows;

    }

    public function getCustomerGroups()
    {
        $query = $this->db->query("SELECT customer_group_id,name FROM " . DB_PREFIX . "customer_group_description WHERE language_id = ". (int)$this->config->get('config_language_id'));

        return $query->rows;
    }

    public function getStores()
    {
        $query = $this->db->query("SELECT store_id,name,url FROM " . DB_PREFIX . "store");

        return $query->rows;
    }

    public function getCountries()
    {
        $query = $this->db->query("SELECT country_id,name,iso_code_2 FROM " . DB_PREFIX . "country WHERE status = ". (int)1);

        return $query->rows;
    }

    public function getZone($id_country)
    {
        $query = $this->db->query("SELECT zone_id,name,code FROM " . DB_PREFIX
            . "zone WHERE country_id = ". (int)$id_country);

        return $query->rows;
    }

    public function getCurrency()
    {
        $query = $this->db->query("SELECT currency_id,code,title,symbol_left,symbol_right,status FROM " . DB_PREFIX . "currency");

        return $query->rows;
    }



    public function addReview($data) {

        $this->db->query("INSERT INTO " . DB_PREFIX . "review SET author = '" . $this->db->escape($data['author']) . "', product_id = '" . (int)$data['product_id'] . "', text = '" . $this->db->escape(strip_tags($data['text'])) . "', rating = '" . (int)$data['rating'] . "', status = '" . (int)$data['status'] . "', date_added = '" . $this->db->escape($data['date_added']) . "'");

        $review_id = $this->db->getLastId();

        $this->cache->delete('product');

        return $review_id;
    }

    public function editReview($review_id, $data) {
        $request = $this->db->query("UPDATE " . DB_PREFIX . "review SET author = '" . $this->db->escape($data['author']) . "', product_id = '" . (int)$data['product_id'] . "', text = '" . $this->db->escape(strip_tags($data['text'])) . "', rating = '" . (int)$data['rating'] . "', status = '" . (int)$data['status'] . "', date_added = '" . $this->db->escape($data['date_added']) . "', date_modified = NOW() WHERE review_id = '" . (int)$review_id . "'");

        $this->cache->delete('product');
        return $request;
    }



    public function addOrder($data) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "order` SET invoice_prefix = '" . $this->db->escape($data['invoice_prefix']) . "', order_status_id = '" . $this->db->escape($data['order_status_id']) . "', store_id = '" . (int)$data['store_id'] . "', store_name = '" . $this->db->escape($data['store_name']) . "', store_url = '" . $this->db->escape($data['store_url']) . "', customer_id = '" . (int)$data['customer_id'] . "', customer_group_id = '" . (int)$data['customer_group_id'] . "', firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname']) . "', email = '" . $this->db->escape($data['email']) . "', telephone = '" . $this->db->escape($data['telephone']) . "', custom_field = '" . $this->db->escape(isset($data['custom_field']) ? json_encode($data['custom_field']) : '') . "', payment_firstname = '" . $this->db->escape($data['payment_firstname']) . "', payment_lastname = '" . $this->db->escape($data['payment_lastname']) . "', payment_company = '" . $this->db->escape($data['payment_company']) . "', payment_address_1 = '" . $this->db->escape($data['payment_address_1']) . "', payment_address_2 = '" . $this->db->escape($data['payment_address_2']) . "', payment_city = '" . $this->db->escape($data['payment_city']) . "', payment_postcode = '" . $this->db->escape($data['payment_postcode']) . "', payment_country = '" . $this->db->escape($data['payment_country']) . "', payment_country_id = '" . (int)$data['payment_country_id'] . "', payment_zone = '" . $this->db->escape($data['payment_zone']) . "', payment_zone_id = '" . (int)$data['payment_zone_id'] . "', payment_address_format = '" . $this->db->escape($data['payment_address_format']) . "', payment_custom_field = '" . $this->db->escape(isset($data['payment_custom_field']) ? json_encode($data['payment_custom_field']) : '') . "', payment_method = '" . $this->db->escape($data['payment_method']) . "', payment_code = '" . $this->db->escape($data['payment_code']) . "', shipping_firstname = '" . $this->db->escape($data['shipping_firstname']) . "', shipping_lastname = '" . $this->db->escape($data['shipping_lastname']) . "', shipping_company = '" . $this->db->escape($data['shipping_company']) . "', shipping_address_1 = '" . $this->db->escape($data['shipping_address_1']) . "', shipping_address_2 = '" . $this->db->escape($data['shipping_address_2']) . "', shipping_city = '" . $this->db->escape($data['shipping_city']) . "', shipping_postcode = '" . $this->db->escape($data['shipping_postcode']) . "', shipping_country = '" . $this->db->escape($data['shipping_country']) . "', shipping_country_id = '" . (int)$data['shipping_country_id'] . "', shipping_zone = '" . $this->db->escape($data['shipping_zone']) . "', shipping_zone_id = '" . (int)$data['shipping_zone_id'] . "', shipping_address_format = '" . $this->db->escape($data['shipping_address_format']) . "', shipping_custom_field = '" . $this->db->escape(isset($data['shipping_custom_field']) ? json_encode($data['shipping_custom_field']) : '') . "', shipping_method = '" . $this->db->escape($data['shipping_method']) . "', shipping_code = '" . $this->db->escape($data['shipping_code']) . "', comment = '" . $this->db->escape($data['comment']) . "', total = '" . (float)$data['total'] . "', affiliate_id = '" . (int)$data['affiliate_id'] . "', commission = '" . (float)$data['commission'] . "', marketing_id = '" . (int)$data['marketing_id'] . "', tracking = '" . $this->db->escape($data['tracking']) . "', language_id = '" . (int)$data['language_id'] . "', currency_id = '" . (int)$data['currency_id'] . "', currency_code = '" . $this->db->escape($data['currency_code']) . "', currency_value = '" . (float)$data['currency_value'] . "', ip = '" . $this->db->escape($data['ip']) . "', forwarded_ip = '" .  $this->db->escape($data['forwarded_ip']) . "', user_agent = '" . $this->db->escape($data['user_agent']) . "', accept_language = '" . $this->db->escape($data['accept_language']) . "', date_added = NOW(), date_modified = NOW()");

        $order_id = $this->db->getLastId();


//        var_dump($data['products']);
//        die();

        // Products
        if (isset($data['products'])) {
            foreach ($data['products'] as $product) {

                $product['price'] = str_replace(",", "", $product['price']);

                $this->db->query("INSERT INTO " . DB_PREFIX . "order_product SET order_id = '" . (int)$order_id . "', product_id = '" . (int)$product['product_id'] . "', name = '" . $this->db->escape($product['name']) . "', model = '" . $this->db->escape($product['model']) . "', quantity = '" . (int)$product['quantity'] . "', price = '" . $product['price'] . "', total = '" . (float)$product['total'] . "', tax = '" . (float)$product['tax'] . "', reward = '" . (int)$product['reward'] . "'");

                $order_product_id = $this->db->getLastId();

                foreach ($product['option'] as $option) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "order_option SET order_id = '" . (int)$order_id . "', order_product_id = '" . (int)$order_product_id . "', product_option_id = '" . (int)$option['product_option_id'] . "', product_option_value_id = '" . (int)$option['product_option_value_id'] . "', name = '" . $this->db->escape($option['name']) . "', `value` = '" . $this->db->escape($option['value']) . "', `type` = '" . $this->db->escape($option['type']) . "'");
                }
            }
        }

        // Gift Voucher
        $this->load->model('extension/total/voucher');

        // Vouchers
        if (isset($data['vouchers'])) {
            foreach ($data['vouchers'] as $voucher) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "order_voucher SET order_id = '" . (int)$order_id . "', description = '" . $this->db->escape($voucher['description']) . "', code = '" . $this->db->escape($voucher['code']) . "', from_name = '" . $this->db->escape($voucher['from_name']) . "', from_email = '" . $this->db->escape($voucher['from_email']) . "', to_name = '" . $this->db->escape($voucher['to_name']) . "', to_email = '" . $this->db->escape($voucher['to_email']) . "', voucher_theme_id = '" . (int)$voucher['voucher_theme_id'] . "', message = '" . $this->db->escape($voucher['message']) . "', amount = '" . (float)$voucher['amount'] . "'");

                $order_voucher_id = $this->db->getLastId();

                $voucher_id = $this->model_extension_total_voucher->addVoucher($order_id, $voucher);

                $this->db->query("UPDATE " . DB_PREFIX . "order_voucher SET voucher_id = '" . (int)$voucher_id . "' WHERE order_voucher_id = '" . (int)$order_voucher_id . "'");
            }
        }

        // Totals
        if (isset($data['totals'])) {
            foreach ($data['totals'] as $total) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "order_total SET order_id = '" . (int)$order_id . "', code = '" . $this->db->escape($total['code']) . "', title = '" . $this->db->escape($total['title']) . "', `value` = '" . (float)$total['value'] . "', sort_order = '" . (int)$total['sort_order'] . "'");
            }
        }

        return $order_id;
    }

    public function historyorder($data)
    {
        $order_history_id = $this->db->getLastId();

        $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_history_id = '" . (int)$order_history_id . "', order_id = '" . $this->db->escape($data['order_id']) . "', order_status_id = '" . $this->db->escape($data['order_status_id']) . "', `notify` = '" . (float)$data['notify'] . "', comment = '" . $data['comment'] . "', date_added = '" . $data['date_added'] ."'");

        return $order_history_id;
    }




//    public function getProd($store_id)
//    {
//        $query = $this->db->query("SELECT zone_id,name,code FROM " . DB_PREFIX
//            . "product_to_store WHERE country_id = ". (int)$store_id);
//
//        return $query->rows;
//    }


}

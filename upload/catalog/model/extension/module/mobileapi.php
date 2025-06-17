<?php
/**
 * Mobile API – Catalog Model helpers
 * PHP 5.6 compatible
 */
class ModelExtensionModuleMobileapi extends Model {
    /**
     * Build nested category tree
     * @return array
     */
    public function getCategoryTree() {
        $categories = $this->getCategoriesByParent(0);
        return $categories;
    }

    /* -------------------- CART -------------------- */
    public function getCartItems($customer_id) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "cart WHERE customer_id = " . (int)$customer_id);
        return $query->rows;
    }

    public function addToCart($customer_id, $product_id, $qty) {
        // Check if exists
        $query = $this->db->query("SELECT cart_id, quantity FROM " . DB_PREFIX . "cart WHERE customer_id=" . (int)$customer_id . " AND product_id=" . (int)$product_id);
        if ($query->num_rows) {
            $new_qty = (int)$query->row['quantity'] + $qty;
            $this->db->query("UPDATE " . DB_PREFIX . "cart SET quantity=" . (int)$new_qty . " WHERE cart_id=" . (int)$query->row['cart_id']);
        } else {
            $this->db->query("INSERT INTO " . DB_PREFIX . "cart SET customer_id=" . (int)$customer_id . ", session_id='', product_id=" . (int)$product_id . ", quantity=" . (int)$qty . ", date_added=NOW()");
        }
    }

    public function updateCartItem($customer_id, $product_id, $qty) {
        $this->db->query("UPDATE " . DB_PREFIX . "cart SET quantity=" . (int)$qty . " WHERE customer_id=" . (int)$customer_id . " AND product_id=" . (int)$product_id);
    }

    public function removeCartItem($customer_id, $product_id) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "cart WHERE customer_id=" . (int)$customer_id . " AND product_id=" . (int)$product_id);
    }

    /* -------------------- WISHLIST -------------------- */
    public function getWishlist($customer_id) {
        $query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "customer_wishlist WHERE customer_id=" . (int)$customer_id);
        $ids = array();
        foreach ($query->rows as $row) { $ids[] = (int)$row['product_id']; }
        return $ids;
    }
    public function addWishlistItem($customer_id, $product_id) {
        $exists = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer_wishlist WHERE customer_id=" . (int)$customer_id . " AND product_id=" . (int)$product_id);
        if (!$exists->num_rows) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "customer_wishlist SET customer_id=" . (int)$customer_id . ", product_id=" . (int)$product_id . ", date_added=NOW()");
        }
    }
    public function removeWishlistItem($customer_id, $product_id) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "customer_wishlist WHERE customer_id=" . (int)$customer_id . " AND product_id=" . (int)$product_id);
    }

    private function getCategoriesByParent($parent_id) {
        $sql = "SELECT c.category_id, cd.name FROM " . DB_PREFIX . "category c "
             . "LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) "
             . "WHERE c.parent_id = " . (int)$parent_id . " AND c.status = 1 AND cd.language_id = " . (int)$this->config->get('config_language_id') . " "
             . "ORDER BY c.sort_order, cd.name";
        $query = $this->db->query($sql);
        $result = array();
        foreach ($query->rows as $row) {
            $children = $this->getCategoriesByParent($row['category_id']);
            $result[] = array(
                'category_id' => (int)$row['category_id'],
                'name'        => $row['name'],
                'children'    => $children,
            );
        }
        return $result;
    }
}

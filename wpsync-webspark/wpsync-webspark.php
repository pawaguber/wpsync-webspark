<?php
/*
Plugin Name: wpsync-webspark
Description: WooCommerce Products Synchronization
Version: 1.0
*/

function wpsync_sync_products() {
    $api_url = 'https://wp.webspark.dev/wp-api/products';

    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        error_log('Error syncing products: ' . $response->get_error_message());
        return;
    }

    $products = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($products)) {
        foreach ($products as $product) {
            wpsync_create_or_update_product($product);
        }
    }
}

function wpsync_schedule_sync() {
    if (!wp_next_scheduled('wpsync_hourly_event')) {
        wp_schedule_event(time(), 'hourly', 'wpsync_hourly_event');
    }
}

function wpsync_sync_products_hourly() {
    wpsync_sync_products();
}

function wpsync_create_or_update_product($product_data) {
    $product_sku = sanitize_text_field($product_data['sku']);

    $existing_product = get_page_by_title($product_sku, OBJECT, 'product');

    if ($existing_product) {
        $product_id = $existing_product->ID;
        update_post_meta($product_id, '_price', $product_data['price']);
    } else {
        $new_product = array(
            'post_title' => $product_data['sku'],
            'post_status' => 'publish',
            'post_type' => 'product'
        );

        $product_id = wp_insert_post($new_product);

        if ($product_id) {
            update_post_meta($product_id, '_sku', $product_data['sku']);
            update_post_meta($product_id, '_price', $product_data['price']);
            update_post_meta($product_id, '_stock', intval($product_data['in_stock']));
        }
    }

}

register_activation_hook(__FILE__, 'wpsync_schedule_sync');

function wpsync_init() {
    if (class_exists('WooCommerce')) {
        add_action('wpsync_hourly_event', 'wpsync_sync_products_hourly');
    } else {
        add_action('admin_notices', 'wpsync_woocommerce_notice');
    }
}

function wpsync_woocommerce_notice() {
    echo '<div class="error"><p>';
    echo 'Для коректної роботи плагіну "wpsync-webspark" необхідно активувати WooCommerce.';
    echo '</p></div>';
}

add_action('plugins_loaded', 'wpsync_init');


?>
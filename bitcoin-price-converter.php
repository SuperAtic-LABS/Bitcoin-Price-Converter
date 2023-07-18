<?php
/*
Plugin Name: Bitcoin Price Converter
Description: Converts WooCommerce product prices to Bitcoin using exchange rates.
Version: 1.0.6
Author: Your Name
*/

// Add Bitcoin price conversion to WooCommerce product display
add_filter('woocommerce_get_price_html', 'convert_price_to_bitcoin', 10, 2);

function convert_price_to_bitcoin($price_html, $product) {
    // Get the current Bitcoin exchange rate
    $bitcoin_rate = get_bitcoin_exchange_rate();

    // Get plugin settings
    $bitcoin_denomination = get_option('bitcoin_denomination', 'BTC');
    $show_fiat_price = get_option('show_fiat_price', true);

    // Check if the product is on sale
    if ($product->is_on_sale()) {
        // Get the sale price
        $sale_price = floatval($product->get_sale_price());

        // Convert the sale price to Bitcoin
        $sale_price_in_bitcoin = $sale_price / $bitcoin_rate;

        // Format the converted sale price
        $formatted_sale_price = format_bitcoin_price($sale_price_in_bitcoin, $bitcoin_denomination);

        // Append the converted sale price to the original price HTML
        $converted_price_html = $formatted_sale_price;

        if ($show_fiat_price) {
            $converted_price_html .= ' <br/><small class="grey">' . $price_html . '</small>';
        }
    } else {
        // Convert the price to Bitcoin
        $price = floatval($product->get_price());
        $price_in_bitcoin = $price / $bitcoin_rate;

        // Format the converted price
        $formatted_price = format_bitcoin_price($price_in_bitcoin, $bitcoin_denomination);

        // Append the converted price to the original price HTML
        $converted_price_html = $formatted_price;

        if ($show_fiat_price) {
            $converted_price_html .= ' <br/><small class="grey">' . $price_html . '</small>';
        }
    }

    return $converted_price_html;
}

// Convert prices on the cart page
add_filter('woocommerce_cart_item_price', 'convert_cart_item_price', 10, 3);
function convert_cart_item_price($price_html, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];
    $price = floatval($product->get_price());
    $bitcoin_rate = get_bitcoin_exchange_rate();
    $bitcoin_denomination = get_option('bitcoin_denomination', 'BTC');
    $price_in_bitcoin = $price / $bitcoin_rate;
    $converted_price_html = format_bitcoin_price($price_in_bitcoin, $bitcoin_denomination);
    return $converted_price_html;
}

// Convert prices on the checkout page
add_filter('woocommerce_checkout_cart_item_quantity', 'convert_checkout_item_price', 10, 3);
function convert_checkout_item_price($item_qty_html, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];
    $price = floatval($product->get_price());
    $bitcoin_rate = get_bitcoin_exchange_rate();
    $bitcoin_denomination = get_option('bitcoin_denomination', 'BTC');
    $price_in_bitcoin = $price / $bitcoin_rate;
    $converted_price_html = format_bitcoin_price($price_in_bitcoin, $bitcoin_denomination);
    return $converted_price_html;
}

// Convert prices on the cart and checkout totals
add_filter('woocommerce_cart_item_subtotal', 'convert_cart_totals', 10, 3);
add_filter('woocommerce_checkout_cart_subtotal', 'convert_cart_totals', 10, 3);
function convert_cart_totals($subtotal_html, $cart_item, $cart_item_key) {
    $price = floatval($cart_item['line_total']);
    $bitcoin_rate = get_bitcoin_exchange_rate();
    $bitcoin_denomination = get_option('bitcoin_denomination', 'BTC');
    $price_in_bitcoin = $price / $bitcoin_rate;
    $converted_price_html = format_bitcoin_price($price_in_bitcoin, $bitcoin_denomination);
    return $converted_price_html;
}

// Get the current Bitcoin exchange rate from the selected source or custom URL
function get_bitcoin_exchange_rate() {
    $exchange_rate_source = get_option('exchange_rate_source', 'coindesk');

    // Check if exchange rate data is stored locally and not expired
    $stored_exchange_rate = get_option('bitcoin_exchange_rate');
    $stored_exchange_rate_timestamp = get_option('bitcoin_exchange_rate_timestamp');

    $current_timestamp = time();
    $ten_minutes_in_seconds = 10 * 60; // 10 minutes

    if ($stored_exchange_rate && ($current_timestamp - $stored_exchange_rate_timestamp) < $ten_minutes_in_seconds) {
        return $stored_exchange_rate;
    }

    switch ($exchange_rate_source) {
        case 'coindesk':
            $api_url = 'https://api.coindesk.com/v1/bpi/currentprice/BTC.json';
            break;
        case 'coingecko':
            $api_url = 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd';
            break;
        case 'custom':
            $api_url = get_option('custom_exchange_rate_url');
            break;
        default:
            $api_url = '';
            break;
    }

    if (empty($api_url)) {
        return false;
    }

    // Fetch the exchange rate data
    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        // Error handling if the API request fails
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!empty($data)) {
        switch ($exchange_rate_source) {
            case 'coindesk':
                if (isset($data['bpi']['USD']['rate_float'])) {
                    // Store the exchange rate and timestamp locally
                    update_option('bitcoin_exchange_rate', $data['bpi']['USD']['rate_float']);
                    update_option('bitcoin_exchange_rate_timestamp', $current_timestamp);

                    // Return the current Bitcoin exchange rate
                    return $data['bpi']['USD']['rate_float'];
                }
                break;
            case 'coingecko':
                if (isset($data['bitcoin']['usd'])) {
                    // Store the exchange rate and timestamp locally
                    update_option('bitcoin_exchange_rate', $data['bitcoin']['usd']);
                    update_option('bitcoin_exchange_rate_timestamp', $current_timestamp);

                    // Return the current Bitcoin exchange rate
                    return $data['bitcoin']['usd'];
                }
                break;
        }
    }

    return false;
}

// Format the Bitcoin price based on the selected denomination
function format_bitcoin_price($price, $denomination) {
    switch ($denomination) {
        case 'mBTC':
            $formatted_price = number_format($price * 1000, 4, '.', ',') . ' mBTC';
            break;
        case 'sats':
            $formatted_price = number_format($price * 100000000, 0, '.', ',') . ' sats';
            break;
        case 'BTC':
        default:
            $formatted_price = number_format($price, 8, '.', ',') . ' BTC';
            break;
    }

    return $formatted_price;
}

// Add plugin settings page to the admin menu
add_action('admin_menu', 'bitcoin_price_converter_settings_page');

function bitcoin_price_converter_settings_page() {
    add_submenu_page(
        'woocommerce',
        'Bitcoin Price Converter Settings',
        'Bitcoin Converter',
        'manage_options',
        'bitcoin_price_converter_settings',
        'bitcoin_price_converter_settings_callback'
    );
}

// Callback function to render the plugin settings page
function bitcoin_price_converter_settings_callback() {
    // Check if the user has permissions to access the settings
    if (!current_user_can('manage_options')) {
        return;
    }

    // Save the settings if the form is submitted
    if (isset($_POST['submit'])) {
        update_option('bitcoin_denomination', $_POST['bitcoin_denomination']);
        update_option('show_fiat_price', isset($_POST['show_fiat_price']));
        update_option('exchange_rate_source', $_POST['exchange_rate_source']);

        if ($_POST['exchange_rate_source'] === 'custom') {
            update_option('custom_exchange_rate_url', $_POST['custom_exchange_rate_url']);
        } else {
            delete_option('custom_exchange_rate_url');
        }

        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }

    // Retrieve the current settings
    $bitcoin_denomination = get_option('bitcoin_denomination', 'BTC');
    $show_fiat_price = get_option('show_fiat_price', true);
    $exchange_rate_source = get_option('exchange_rate_source', 'coindesk');
    $custom_exchange_rate_url = get_option('custom_exchange_rate_url');
    ?>
    <div class="wrap">
        <h1>Bitcoin Price Converter Settings</h1>

        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Bitcoin Denomination</th>
                    <td>
                        <select name="bitcoin_denomination">
                            <option value="BTC" <?php selected($bitcoin_denomination, 'BTC'); ?>>BTC</option>
                            <option value="mBTC" <?php selected($bitcoin_denomination, 'mBTC'); ?>>mBTC</option>
                            <option value="sats" <?php selected($bitcoin_denomination, 'sats'); ?>>sats</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Show Fiat Price</th>
                    <td>
                        <label>
                            <input type="checkbox" name="show_fiat_price" value="1" <?php checked($show_fiat_price); ?>>
                            Display fiat price alongside Bitcoin price
                        </label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Exchange Rate Source</th>
                    <td>
                        <select name="exchange_rate_source" id="exchange_rate_source">
                            <option value="coindesk" <?php selected($exchange_rate_source, 'coindesk'); ?>>CoinDesk</option>
                            <option value="coingecko" <?php selected($exchange_rate_source, 'coingecko'); ?>>CoinGecko</option>
                            <option value="custom" <?php selected($exchange_rate_source, 'custom'); ?>>Custom URL</option>
                        </select>
                        <?php if ($exchange_rate_source === 'custom') : ?>
                            <br>
                            <input type="text" name="custom_exchange_rate_url" value="<?php echo esc_attr($custom_exchange_rate_url); ?>" placeholder="Custom Exchange Rate URL">
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit" class="button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    <script>
        // Toggle custom URL field based on the selected exchange rate source
        (function ($) {
            $(document).ready(function () {
                var exchangeRateSource = $('#exchange_rate_source');

                exchangeRateSource.on('change', function () {
                    if (exchangeRateSource.val() === 'custom') {
                        $('[name="custom_exchange_rate_url"]').closest('tr').show();
                    } else {
                        $('[name="custom_exchange_rate_url"]').closest('tr').hide();
                    }
                });

                if (exchangeRateSource.val() === 'custom') {
                    $('[name="custom_exchange_rate_url"]').closest('tr').show();
                }
            });
        })(jQuery);
    </script>
    <?php
}

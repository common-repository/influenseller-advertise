<?php
/*
Plugin Name: Influenseller Advertise
Version: 1.0.3
Description: Tracking purchase of customers who are coming from influenseller
Plugin URI: https://www.influenseller.net/wp-influenseller.zip
Author: Influenseller Ltd
Author URI: https://www.influenseller.net
*/

function save_infad_cookie()
{
    if (isset($_GET['ads_id']) && !empty($_GET['ads_id'])) {
        $ads_id = sanitize_key($_GET['ads_id']);
        $api_url = "https://f7o60o6g06.execute-api.us-east-1.amazonaws.com/V0";
        $api_key = get_option('admin_wc_infad_api_key');
        $body = [
            'ads_id'  => $ads_id,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'api_key' => $api_key
        ];

        for ($i = 1; $i > 0; $i++) {
            $response = wc_infad_id_request($api_url, $body);
            if (!is_wp_error($response)) {
                $response = wp_remote_retrieve_body($response);
                $response = json_decode($response);
                if ($response->statusCode == 200 && !empty($response->session_id)) {
                    setcookie("inf_session_id", $response->session_id, time() + 259800, '/');
                }
                break;
            }
        }
    }
}

add_action('init', 'save_infad_cookie');
add_action('woocommerce_payment_complete', 'wc_infad_id_payment_complete');
add_action('woocommerce_thankyou', 'wc_infad_id_payment_complete');

function wc_infad_id_payment_complete($order_id)
{
    $isConfirm = get_post_meta($order_id, 'inf_session_id', true);
    if ($isConfirm || !isset($_COOKIE['inf_session_id'])) {
        return;
    }
    $api_key = get_option('admin_wc_infad_api_key');
    $order = wc_get_order($order_id);
    $inf_session_id = sanitize_key($_COOKIE['inf_session_id']);
    $qty = 0;
    foreach ($order->get_items() as $item_id => $item) {
        $qty = $qty + $item->get_quantity();
    }


    $body = [
        'inf_session_id'  => $inf_session_id,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'total_price' => $order->get_total(),
        'price_unit' => get_woocommerce_currency_symbol($order->get_currency()),
        'no_items' => $qty,
        'order_id' => $order_id,
        'api_key' => $api_key
    ];
    $api_url = "https://w00lrzu6o1.execute-api.us-east-1.amazonaws.com/V0";
    for ($i = 1; $i > 0; $i++) {
        $response = wc_infad_id_request($api_url, $body);
        if (!is_wp_error($response)) {
            $response = wp_remote_retrieve_body($response);
            $response = json_decode($response);
            if ($response->statusCode == 200 && $response->body == 'OK') {
                update_post_meta($order_id, 'inf_session_id', $inf_session_id);
            }
            break;
        }
    }
}


function wc_infad_id_request($api_url, $body = [])
{
    $body = wp_json_encode($body);
    $options = [
        'body'        => $body,
        'headers'     => [
            'Content-Type' => 'application/json',
        ]
    ];
    return wp_remote_post($api_url, $options);
}

// Register Admin Panel Menu
function wc_infad_Admin_Menu()
{
    add_menu_page('Settings', 'Influenseller', 'administrator', 'wc_infad_admin', 'wc_infad_AdminSettings');
}
add_action('admin_menu', 'wc_infad_Admin_Menu');

function wc_infad_AdminSettings()
{
?>
    <style>
        input,
        textarea {
            width: 100%;
        }
    </style>
    <div class="wrap">
        <h2>Settings</h2>
        <?php

        if (isset($_POST['admin_wc_infad_api_key']) && !empty($_POST['admin_wc_infad_api_key'])) {
            $admin_wc_infad_api_key = $_POST['admin_wc_infad_api_key'];            
            $admin_wc_infad_api_key_num = update_option('admin_wc_infad_api_key', $admin_wc_infad_api_key);           
            if ($admin_wc_infad_api_key_num ) {
                echo '<div id="message" class="updated">ApiKey Saved</div>';
            } else {
                echo '<div id="message" class="error">Error!</div>';
            }
        }

        $admin_wc_infad_api_key = get_option('admin_wc_infad_api_key');
        ?>
        <form action="" method="post">
            <table class="form-table">                
                <tr>
                    <th scope="row"><label for="cstyle">Api Key</label></th>
                    <td>
                        <input name="admin_wc_infad_api_key" value="<?php echo $admin_wc_infad_api_key ? esc_attr($admin_wc_infad_api_key) : '' ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <input type="submit" value="Save" name="wc_infad_save" class="button-primary" />
                    </th>
                    <td>
                    </td>
                </tr>
            </table>
        </form>
    </div>
<?php
}

<?php
/**
 * This file is part of ImageX.
 *
 * @author   沈唁
 * @link     https://qq52o.me
 * @document https://github.com/sy-records/volcengine-imagex-wordpress
 * @license  https://github.com/sy-records/volcengine-imagex-wordpress/blob/master/LICENSE
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

$imagex_options = get_option('imagex_options', true);
$upload_url_path = get_option('upload_url_path');
$imagex_upload_url_path = esc_attr($imagex_options['upload_url_path']);

if ($upload_url_path == $imagex_upload_url_path) {
    update_option('upload_url_path', '');
}

delete_option('imagex_options');
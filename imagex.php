<?php
/*
Plugin Name: ImageX
Plugin URI: https://github.com/sy-records/volcengine-imagex-wordpress
Description: 使用火山引擎图片服务（ImageX）作为附件存储空间。（This is a plugin that uses VolcEngine ImageX for attachments remote saving.）
Version: 1.1.2
Author: 沈唁
Author URI: https://qq52o.me
License: Apache 2.0
*/

require_once 'sdk/vendor/autoload.php';

use Volc\Service\ImageX;

define('IMAGEX_VERSION', '1.1.2');
define('IMAGEX_BASEFOLDER', plugin_basename(dirname(__FILE__)));

register_activation_hook(__FILE__, 'imagex_set_options');
function imagex_set_options()
{
    $options = array(
        'service_id' => '',
        'region' => 'cn-north-1',
        'access_key' => '',
        'secret_key' => '',
        'nothumb' => 'false', // 是否上传缩略图
        'nolocalsaving' => 'false', // 是否保留本地备份
        'upload_url_path' => '', // URL前缀
        'image_template' => '', // 图片处理模板
        'update_file_name' => 'false', // 是否重命名文件名
    );
    add_option('imagex_options', $options, '', 'yes');
}

/**
 * @return mixed|ImageX
 */
function imagex_get_client()
{
    $imagex_option = get_option('imagex_options', true);
    $imagex_client = ImageX::getInstance(esc_attr($imagex_option['region']));
    $imagex_client->setAccessKey(esc_attr($imagex_option['access_key']));
    $imagex_client->setSecretKey(esc_attr($imagex_option['secret_key']));

    return $imagex_client;
}

/**
 * @param $object
 * @param $filename
 * @param false $no_local_file
 */
function imagex_file_upload($object, $filename, $no_local_file = false)
{
    //如果文件不存在，直接返回false
    if (!file_exists($filename)) {
        return false;
    }
    $client = imagex_get_client();
    $imagex_option = get_option('imagex_options', true);
    $params = array();
    $params['ServiceId'] = esc_attr($imagex_option['service_id']);
    $params['UploadNum'] = 1;
    $object = ltrim($object, '/');
    $params['StoreKeys'] = array($object);
    $filePaths = array($filename);
    $response = $client->uploadImages($params, $filePaths);

//    echo $response;

    if ($no_local_file) {
        imagex_delete_local_file($filename);
    }

    return true;
}

/**
 * 是否需要删除本地文件
 *
 * @return bool
 */
function imagex_is_delete_local_file()
{
    $imagex_option = get_option('imagex_options', true);
    return (esc_attr($imagex_option['nolocalsaving']) == 'true');
}

/**
 * 删除本地文件
 *
 * @param  $file
 * @return bool
 */
function imagex_delete_local_file($file)
{
    try {
        //文件不存在
        if (!file_exists($file)) {
            return true;
        }

        //删除文件
        if (!unlink($file)) {
            return false;
        }

        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * @param $region
 * @param $service_id
 * @return string
 */
function imagex_build_delete_uri_prefix($region, $service_id)
{
    $prefix = '';
    switch ($region) {
        case 'cn-north-1':
            $prefix = 'tos-cn-i-';
            break;
        case 'us-east-1':
            $prefix = 'tos-us-i-';
            break;
        case 'ap-singapore-1':
            $prefix = 'tos-ap-i-';
            break;
    }
    return $prefix . $service_id;
}

/**
 * @param array $files
 */
function imagex_delete_files(array $files)
{
    $client = imagex_get_client();
    $imagex_option = get_option('imagex_options', true);
    $client->deleteImages(esc_attr($imagex_option['service_id']), $files);
}

/**
 * 上传附件（包括图片的原图）
 *
 * @param  $metadata
 * @return array()
 */
function imagex_upload_attachments($metadata)
{
    $mime_types = get_allowed_mime_types();
    $image_mime_types = array(
        $mime_types['jpg|jpeg|jpe'],
        $mime_types['gif'],
        $mime_types['png'],
        $mime_types['bmp'],
        $mime_types['tiff|tif'],
        $mime_types['ico'],
    );
    // 例如mp4等格式 上传后根据配置选择是否删除 删除后媒体库会显示默认图片 点开内容是正常的
    // 图片在缩略图处理
    if (!in_array($metadata['type'], $image_mime_types)) {
        //生成object在存储服务中的存储路径
        if (get_option('upload_path') == '.') {
            $metadata['file'] = str_replace("./", '', $metadata['file']);
        }
        $object = str_replace("\\", '/', $metadata['file']);
        $home_path = get_home_path();
        $object = str_replace($home_path, '', $object);

        //在本地的存储路径
        $file = $home_path . $object; //向上兼容，较早的WordPress版本上$metadata['file']存放的是相对路径
        //执行上传操作
        imagex_file_upload('/' . $object, $file, imagex_is_delete_local_file());
    }

    return $metadata;
}

//避免上传插件/主题时出现同步到imagex的情况
if (substr_count($_SERVER['REQUEST_URI'], '/update.php') <= 0) {
    add_filter('wp_handle_upload', 'imagex_upload_attachments', 50);
}

/**
 * 上传图片的缩略图
 * 
 * @param $metadata
 * @return mixed
 */
function imagex_upload_thumbs($metadata)
{
    //获取上传路径
    $wp_uploads = wp_upload_dir();
    $basedir = $wp_uploads['basedir'];
    if (isset($metadata['file'])) {
        // Maybe there is a problem with the old version
        $file = $basedir . '/' . $metadata['file'];
        $upload_path = get_option('upload_path');
        if ($upload_path != '.') {
            $path_array = explode($upload_path, $file);
            if (isset($path_array[1]) && !empty($path_array[1])) {
                $object = '/' . $upload_path . $path_array[1];
            }
        } else {
            $object = '/' . $metadata['file'];
            $file = str_replace('./', '', $file);
        }

        imagex_file_upload($object, $file, imagex_is_delete_local_file());
    }
    //上传所有缩略图
    if (isset($metadata['sizes']) && count($metadata['sizes']) > 0) {
        //获取插件的配置信息
        $imagex_options = get_option('imagex_options', true);
        //是否需要上传缩略图
        $nothumb = (esc_attr($imagex_options['nothumb']) == 'true');
        //如果禁止上传缩略图，就不用继续执行了
        if ($nothumb) {
            return $metadata;
        }
        //得到本地文件夹和远端文件夹
        $file_path = $basedir . '/' . dirname($metadata['file']) . '/';
        $file_path = str_replace("\\", '/', $file_path);
        if ($upload_path == '.') {
            $file_path = str_replace('./', '', $file_path);
        }

        $object_path = str_replace(get_home_path(), '', $file_path);

        //there may be duplicated filenames,so ....
        foreach ($metadata['sizes'] as $val) {
            //生成object在存储服务中的存储路径
            $object = '/' . $object_path . $val['file'];
            //生成本地存储路径
            $file = $file_path . $val['file'];

            //执行上传操作
            imagex_file_upload($object, $file, (esc_attr($imagex_options['nolocalsaving']) == 'true'));
        }
    }
    return $metadata;
}

//避免上传插件/主题时出现同步到imagex的情况
if (substr_count($_SERVER['REQUEST_URI'], '/update.php') <= 0) {
    add_filter('wp_generate_attachment_metadata', 'imagex_upload_thumbs', 100);
}

/**
 * 删除远端文件，删除文件时触发
 * @param $post_id
 */
function imagex_delete_remote_attachment($post_id)
{
    $meta = wp_get_attachment_metadata( $post_id );

    if (isset($meta['file'])) {

        $deleteObjects = [];

        // meta['file']的格式为 "2020/01/wp-bg.png"
        $upload_path = get_option('upload_path');
        if ($upload_path == '') {
            $upload_path = WP_CONTENT_DIR . '/uploads';
        }
        $file_path = $upload_path . '/' . $meta['file'];

        $imagex_options = get_option('imagex_options', true);
        $uri_prefix = imagex_build_delete_uri_prefix(esc_attr($imagex_options['region']), esc_attr($imagex_options['service_id']));

        $deleteObjects[] = $uri_prefix . '/' . str_replace("\\", '/', $file_path);

//        $is_nothumb = (esc_attr($imagex_options['nothumb']) == 'false');
//        if ($is_nothumb) {
            // 删除缩略图
            if (isset($meta['sizes']) && count($meta['sizes']) > 0) {
                foreach ($meta['sizes'] as $val) {
                    $size_file = dirname($file_path) . '/' . $val['file'];

                    $deleteObjects[] = $uri_prefix . '/' . str_replace("\\", '/', $size_file);
                }
            }
//        }
        imagex_delete_files($deleteObjects);
    }
}
add_action('delete_attachment', 'imagex_delete_remote_attachment');

// 当upload_path为根目录时，需要移除URL中出现的“绝对路径”
function imagex_modefiy_img_url($url, $post_id)
{
    // 移除 ./ 和 项目根路径
    $url = str_replace(array('./', get_home_path()), array('', ''), $url);
    return $url;
}

if (get_option('upload_path') == '.') {
    add_filter('wp_get_attachment_url', 'imagex_modefiy_img_url', 30, 2);
}

function imagex_sanitize_file_name($filename)
{
    $imagex_options = get_option('imagex_options');
    switch ($imagex_options['update_file_name']) {
        case 'md5':
            return  md5($filename) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        case 'time':
            return date('YmdHis', current_time('timestamp'))  . mt_rand(100, 999) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        default:
            return $filename;
    }
}
add_filter( 'sanitize_file_name', 'imagex_sanitize_file_name', 10, 1 );

function imagex_function_each(&$array)
{
    $res = array();
    $key = key($array);
    if ($key !== null) {
        next($array);
        $res[1] = $res['value'] = $array[$key];
        $res[0] = $res['key'] = $key;
    } else {
        $res = false;
    }
    return $res;
}

/**
 * @param $dir
 * @return array
 */
function imagex_read_dir_queue($dir)
{
    $dd = [];
    if (isset($dir)) {
        $files = array();
        $queue = array($dir);
        while ($data = imagex_function_each($queue)) {
            $path = $data['value'];
            if (is_dir($path) && $handle = opendir($path)) {
                while ($file = readdir($handle)) {
                    if ($file == '.' || $file == '..') {
                        continue;
                    }
                    $files[] = $real_path = $path . '/' . $file;
                    if (is_dir($real_path)) {
                        $queue[] = $real_path;
                    }
                    //echo explode(get_option('upload_path'),$path)[1];
                }
            }
            closedir($handle);
        }
        $upload_path = get_option('upload_path');
        foreach ($files as $v) {
            if (!is_dir($v)) {
                $dd[] = ['filepath' => $v, 'key' =>  '/' . $upload_path . explode($upload_path, $v)[1]];
            }
        }
    }

    return $dd;
}

// 在插件列表页添加设置按钮
function imagex_plugin_action_links($links, $file)
{
    if ($file == IMAGEX_BASEFOLDER . '/imagex.php') {
        $links[] = '<a href="options-general.php?page=' . IMAGEX_BASEFOLDER . '/imagex.php">设置</a>';
    }
    return $links;
}
add_filter('plugin_action_links', 'imagex_plugin_action_links', 10, 2);

add_filter('the_content', 'imagex_setting_content_ci');
function imagex_setting_content_ci($content)
{
    $option = get_option('imagex_options');
    if (!empty($option['image_template'])) {
        preg_match_all('/<img.*?(?: |\\t|\\r|\\n)?src=[\'"]?(.+?)[\'"]?(?:(?: |\\t|\\r|\\n)+.*?)?>/sim', $content, $images);
        if (!empty($images) && isset($images[1])) {
            foreach ($images[1] as $item) {
                if(strpos($item, $option['upload_url_path']) !== false){
                    $content = str_replace($item, $item . $option['image_template'], $content);
                }
            }
        }
    }
    return $content;
}

add_filter('post_thumbnail_html', 'imagex_setting_post_thumbnail_ci', 10, 3);
function imagex_setting_post_thumbnail_ci( $html, $post_id, $post_image_id )
{
    $option = get_option('imagex_options');
    if (!empty($option['image_template']) && has_post_thumbnail()) {
        preg_match_all('/<img.*?(?: |\\t|\\r|\\n)?src=[\'"]?(.+?)[\'"]?(?:(?: |\\t|\\r|\\n)+.*?)?>/sim', $html, $images);
        if (!empty($images) && isset($images[1])) {
            foreach ($images[1] as $item) {
                if(strpos($item, $option['upload_url_path']) !== false){
                    $html = str_replace($item, $item . $option['image_template'], $html);
                }
            }
        }
    }
    return $html;
}

// 在导航栏“设置”中添加条目
function imagex_add_setting_page()
{
    add_options_page('火山引擎ImageX设置', '火山引擎ImageX设置', 'manage_options', __FILE__, 'imagex_setting_page');
}
add_action('admin_menu', 'imagex_add_setting_page');

function imagex_setting_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient privileges!');
    }
    $options = array();
    if (!empty($_POST) and $_POST['type'] == 'imagex_set') {
        $options['service_id'] = isset($_POST['service_id']) ? sanitize_text_field($_POST['service_id']) : '';
        $options['region'] = isset($_POST['region']) ? sanitize_text_field($_POST['region']) : '';
        $options['access_key'] = isset($_POST['access_key']) ? sanitize_text_field($_POST['access_key']) : '';
        $options['secret_key'] = isset($_POST['secret_key']) ? sanitize_text_field($_POST['secret_key']) : '';
        $options['nothumb'] = isset($_POST['nothumb']) ? 'true' : 'false';
        $options['nolocalsaving'] = isset($_POST['nolocalsaving']) ? 'true' : 'false';
        $options['upload_url_path'] = isset($_POST['upload_url_path']) ? sanitize_text_field(stripslashes($_POST['upload_url_path'])) : '';
        $options['image_template'] = isset($_POST['image_template']) ? sanitize_text_field($_POST['image_template']) : '';
        $options['update_file_name'] = isset($_POST['update_file_name']) ? sanitize_text_field($_POST['update_file_name']) : 'false';
    }

    if (!empty($_POST) and $_POST['type'] == 'imagex_all') {
        $sync = imagex_read_dir_queue(get_home_path() . get_option('upload_path'));
        foreach ($sync as $k) {
            imagex_file_upload($k['key'], $k['filepath']);
        }
        echo '<div class="updated"><p><strong>本次操作成功同步' . count($sync) . '个文件</strong></p></div>';
    }

    // 替换数据库链接
    if(!empty($_POST) and $_POST['type'] == 'imagex_replace') {
        $old_url = esc_url_raw($_POST['old_url']);
        $new_url = esc_url_raw($_POST['new_url']);

        global $wpdb;
        $posts_name = $wpdb->prefix .'posts';
        // 文章内容
        $posts_result = $wpdb->query("UPDATE $posts_name SET post_content = REPLACE( post_content, '$old_url', '$new_url') ");

        // 修改题图之类的
        $postmeta_name = $wpdb->prefix .'postmeta';
        $postmeta_result = $wpdb->query("UPDATE $postmeta_name SET meta_value = REPLACE( meta_value, '$old_url', '$new_url') ");

        echo '<div class="updated"><p><strong>替换成功！共替换文章内链'.$posts_result.'条、题图链接'.$postmeta_result.'条！</strong></p></div>';
    }

    // 若$options不为空数组，则更新数据
    if ($options !== array()) {
        update_option('imagex_options', $options);

        $upload_path = sanitize_text_field(trim(stripslashes($_POST['upload_path']), '/'));
        if ($upload_path === '') {
            $upload_path = WP_CONTENT_DIR . '/uploads';
        }
        update_option('upload_path', $upload_path);

        $upload_url_path = sanitize_text_field(trim(stripslashes($_POST['upload_url_path']), '/'));
        update_option('upload_url_path', $upload_url_path);

        echo '<div class="updated"><p><strong>设置已保存！</strong></p></div>';
    }

    $imagex_options = get_option('imagex_options', true);
    $upload_path = get_option('upload_path');
    $upload_url_path = get_option('upload_url_path');

    $imagex_service_id = esc_attr($imagex_options['service_id']);
    $imagex_region = esc_attr($imagex_options['region']);
    $imagex_access_key = esc_attr($imagex_options['access_key']);
    $imagex_secret_key = esc_attr($imagex_options['secret_key']);

    $imagex_nothumb = esc_attr($imagex_options['nothumb']);
    $imagex_nothumb = ($imagex_nothumb == 'true');

    $imagex_nolocalsaving = esc_attr($imagex_options['nolocalsaving']);
    $imagex_nolocalsaving = ($imagex_nolocalsaving == 'true');

    $imagex_update_file_name = esc_attr($imagex_options['update_file_name']);

    $imagex_image_template = esc_attr($imagex_options['image_template']);

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    ?>
    <div class="wrap" style="margin: 10px;">
        <h1>火山引擎 ImageX 设置 <span style="font-size: 13px;">当前版本：<?php echo IMAGEX_VERSION; ?></span></h1>
        <p>如果觉得此插件对你有所帮助，不妨到 <a href="https://github.com/sy-records/volcengine-imagex-wordpress" target="_blank">GitHub</a> 上点个<code>Star</code>，<code>Watch</code>关注更新；</p>
        <hr/>
        <form name="form" method="post">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>服务ID</legend>
                    </th>
                    <td>
                        <input type="text" name="service_id" value="<?php echo $imagex_service_id; ?>" size="50" placeholder="请填写服务ID"/>

                        <p>请先访问 <a href="https://console.volcengine.cn/imagex/overview/" target="_blank">图片服务管理</a> 新建服务，再填写以上内容。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>地区</legend>
                    </th>
                    <td>
                        <select name="region">
                            <option value="cn-north-1" <?php if ($imagex_region == 'cn-north-1') {echo ' selected="selected"';}?>>国内</option>
                            <option value="us-east-1" <?php if ($imagex_region == 'us-east-1') {echo ' selected="selected"';}?>>美东</option>
                            <option value="ap-singapore-1" <?php if ($imagex_region == 'ap-singapore-1') {echo ' selected="selected"';}?>>新加坡</option>
                        </select>
                        <p>请选择您新建的服务所在地区</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>AccessKey ID</legend>
                    </th>
                    <td>
                        <input type="text" name="access_key" value="<?php echo $imagex_access_key; ?>" size="50" placeholder="AccessKey ID"/>

                        <p>请先访问 <a href="https://console.volcengine.cn/iam/keymanage/" target="_blank">密钥管理</a> 获取 <code>AccessKey ID、AccessKey Secret</code></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>AccessKey Secret</legend>
                    </th>
                    <td>
                        <input type="text" name="secret_key" value="<?php echo $imagex_secret_key; ?>" size="50" placeholder="AccessKey Secret"/>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>不上传缩略图</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="nothumb" <?php if ($imagex_nothumb) { echo 'checked="checked"'; } ?> />

                        <p>建议不勾选</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>不在本地保留备份</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="nolocalsaving" <?php if ($imagex_nolocalsaving) { echo 'checked="checked"'; } ?> />

                        <p>建议不勾选</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>本地文件夹</legend>
                    </th>
                    <td>
                        <input type="text" name="upload_path" value="<?php echo $upload_path; ?>" size="50" placeholder="请输入上传文件夹"/>

                        <p>附件在服务器上的存储位置，例如： <code>wp-content/uploads</code> （注意不要以“/”开头和结尾），根目录请输入<code>.</code>。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>URL前缀</legend>
                    </th>
                    <td>
                        <input type="text" name="upload_url_path" value="<?php echo $upload_url_path; ?>" size="50" placeholder="请输入URL前缀"/>

                        <p><b>注意：</b></p>

                        <p>URL前缀的格式为 <code><?php echo $protocol;?>{已绑定域名}/{本地文件夹}</code> ，“本地文件夹”务必与上面保持一致（结尾无 <code>/</code> ），或者“本地文件夹”为 <code>.</code> 时 <code><?php echo $protocol;?>{已绑定域名}</code> 。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>自动重命名文件</legend>
                    </th>
                    <td>
                        <select name="update_file_name">
                            <option <?php if ($imagex_update_file_name == 'false') {echo 'selected="selected"';} ?> value="false">不处理</option>
                            <option <?php if ($imagex_update_file_name == 'md5') {echo 'selected="selected"';} ?> value="md5">MD5</option>
                            <option <?php if ($imagex_update_file_name == 'time') {echo 'selected="selected"';} ?> value="time">时间戳+随机数</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>图片处理模板</legend>
                    </th>
                    <td>
                        <input type="text" name="image_template" value="<?php echo $imagex_image_template; ?>" size="50" placeholder="请输入图片处理模板"/>

                        <p><b>获取模板：</b></p>

                        <p>1）在 <a href="https://console.volcengine.cn/imagex/image_template/" target="_blank">图片处理配置</a> 中新建模板。</p>

                        <p>2）将<code>模板配置</code>填写到此处。</p>
                    </td>
                </tr>
                <tr>
                    <th><legend>保存/更新选项</legend></th>
                    <td><input type="submit" name="submit" class="button button-primary" value="保存更改"/></td>
                </tr>
            </table>
            <input type="hidden" name="type" value="imagex_set">
        </form>
        <form name="form" method="post">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>同步历史附件</legend>
                    </th>
                    <input type="hidden" name="type" value="imagex_all">
                    <td>
                        <input type="submit" name="submit" class="button button-secondary" value="开始同步"/>
                        <p><b>注意：如果是首次同步，执行时间将会非常长（根据你的历史附件数量），有可能会因为执行时间过长，导致页面显示超时或者报错。</b></p>
                    </td>
                </tr>
            </table>
        </form>
        <hr>
        <form name="form" method="post">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>数据库原链接替换</legend>
                    </th>
                    <td>
                        <input type="text" name="old_url" size="50" placeholder="请输入要替换的旧域名"/>
                        <p>如：<code>https://qq52o.me</code></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend></legend>
                    </th>
                    <td>
                        <input type="text" name="new_url" size="50" placeholder="请输入要替换的新域名"/>
                        <p>如：<code>https://resources.qq52o.me</code></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend></legend>
                    </th>
                    <input type="hidden" name="type" value="imagex_replace">
                    <td>
                        <input type="submit" name="submit"  class="button button-secondary" value="开始替换"/>
                        <p><b>注意：如果是首次替换，请注意备份！此功能会替换文章以及设置的特色图片（题图）等使用的资源链接</b></p>
                    </td>
                </tr>
            </table>
        </form>
    </div>
<?php
}
?>
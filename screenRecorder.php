<?php
/*
Plugin Name: Record Screen
Plugin URI: https://www.spyform.com
description: Record and playback all actions of an user that visits your website. Can be set on pages and/or posts.
Version: 1.03
Author: proxymis
Author URI: https://www.proxymis.com
License: GPL2
*/


class ScreenRecorderSettings
{
    private $settings_options;
    public static $debug = FALSE;


    public function __construct()
    {
        add_action('admin_menu', array($this, 'settings_add_plugin_page'));
        add_action('admin_init', array($this, 'settings_page_init'));
        add_action('admin_print_styles', 'add_my_stylesheet');
    }

    public function settings_add_plugin_page()
    {
        add_menu_page(
            'Record Settings',
            'Record Settings',
            'manage_options',
            'screenRecorder',
            array($this, 'settings_create_admin_page'),
            'dashicons-visibility',
            66 // position
        );
    }

    public function settings_create_admin_page() {
        $cache = (ScreenRecorderSettings::$debug)?'?cache='.time():'';
        wp_enqueue_style('record.css', plugins_url( '/css/record.css'.$cache, __FILE__ ) );
        wp_enqueue_style('replay.css', plugins_url( '/css/replay.css'.$cache, __FILE__ ) );
        wp_enqueue_style('thickbox.css', '/'.WPINC.'/js/thickbox/thickbox.css', null, '1.0');

        wp_enqueue_script('recordAdmin', plugins_url('js/recordAdmin.js'.$cache, __FILE__), array('jquery'), '', false);
        wp_enqueue_script('rrwebPlayer', plugins_url('js/rrwebPlayer.min.js'.$cache, __FILE__), array('jquery'), '', false);
        wp_enqueue_script('lzutf8.min.js', plugins_url('js/lzutf8.min.js', __FILE__), false, '', false);
        wp_enqueue_script('thickbox', null, array('jquery'));

        add_thickbox();
        $params = array(
            'ajaxurl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('spy_nonce'),
        );
        wp_localize_script('recordAdmin', 'params', $params);
        $this->settings_options = get_option('settings_option_name'); ?>
        <div id="spyAdminContainer" class="wrap">

            <!--<h2>Spy Settings</h2>-->
            <?php settings_errors(); ?>

            <input type="radio" name="tabs" id="tab1" checked />
            <label for="tab1">Recorded screens</label>

            <input type="radio" name="tabs" id="tab2" />
            <label for="tab2">Settings</label>

            <div class="tab content1">
                <div id="spyTableContainer"></div>
                <div>
                    <button id="spyDeleteAllrecords">Delete all records</button>
                </div>
            </div>
            <div class="tab content2">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('settings_option_group');
                    do_settings_sections('settings-admin');
                    submit_button();
                    ?>
                </form>
            </div>
        </div>
    <?php }

    public function settings_page_init()
    {
        register_setting(
            'settings_option_group',
            'settings_option_name',
            array($this, 'settings_sanitize')
        );

        add_settings_section(
            'settings_setting_section',
            'Settings', // title
            array($this, 'settings_section_info'),
            'settings-admin' // page
        );

        add_settings_field(
            'spy_all_pages',
            'Record all pages',
            array($this, 'spy_all_pages_callback'),
            'settings-admin',
            'settings_setting_section'
        );

        add_settings_field(
            'spy_all_posts',
            'Record all posts',
            array($this, 'spy_all_posts_callback'),
            'settings-admin',
            'settings_setting_section'
        );
    }

    public function settings_sanitize($input)
    {
        $sanitary_values = array();
        if (isset($input['spy_all_pages'])) {
            $sanitary_values['spy_all_pages'] = $input['spy_all_pages'];
        }

        if (isset($input['spy_all_posts'])) {
            $sanitary_values['spy_all_posts'] = $input['spy_all_posts'];
        }

        return $sanitary_values;
    }

    public function settings_section_info()
    {
    }

    public function spy_all_pages_callback()
    {
        printf(
            '<input type="checkbox" name="settings_option_name[spy_all_pages]" id="spy_all_pages" value="spy_all_pages" %s> <label for="spy_all_pages">Record all pages</label>',
            (isset($this->settings_options['spy_all_pages']) && $this->settings_options['spy_all_pages'] === 'spy_all_pages') ? 'checked' : ''
        );
    }


    public function spy_all_posts_callback()
    {
        printf(
            '<input type="checkbox" name="settings_option_name[spy_all_posts]" id="spy_all_posts" value="spy_all_posts" %s> <label for="spy_all_posts">Record all posts</label>',
            (isset($this->settings_options['spy_all_posts']) && $this->settings_options['spy_all_posts'] === 'spy_all_posts') ? 'checked' : ''
        );
    }
}
define('SCREENRECORDER_TABLE_NAME', 'screenRecorder');
define('SCREENRECORDER_ROWS_PER_PAGE', '10');

register_activation_hook(__FILE__, 'screenRecorder_plugin_activate');
register_deactivation_hook(__FILE__, 'screenRecorder_plugin_deactivate');
add_action('add_meta_boxes', 'screenRecorder_add_meta_box');
add_action('save_post', 'screenRecorder_save');
add_action('wp_enqueue_scripts', 'screenRecorder_load_js_scripts');

add_action("wp_ajax_screenRecorder_insert_data","screenRecorder_insert_data");
add_action("wp_ajax_screenRecorder_delete_record","screenRecorder_delete_record");
add_action("wp_ajax_screenRecorder_delete_all_records", "screenRecorder_delete_all_records");
add_action("wp_ajax_screenRecorder_get_records","screenRecorder_get_records");
add_action("wp_ajax_screenRecorder_play_record","screenRecorder_play_record");


if (is_admin()) {
    $settings = new ScreenRecorderSettings();
}

function screenRecorder_getBrowser()
{
    $u_agent = $_SERVER['HTTP_USER_AGENT'];
    $bname = 'Unknown';
    $platform = 'Unknown';
    $version= "";

//First get the platform?
    if (preg_match('/linux/i', $u_agent)) {
        $platform = 'linux';
    }
    elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
        $platform = 'mac';
    }
    elseif (preg_match('/windows|win32/i', $u_agent)) {
        $platform = 'windows';
    }

// Next get the name of the useragent yes seperately and for good reason
    if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent))
    {
        $bname = 'Internet Explorer';
        $ub = "MSIE";
    }
    elseif(preg_match('/Firefox/i',$u_agent))
    {
        $bname = 'Mozilla Firefox';
        $ub = "Firefox";
    }
    elseif(preg_match('/Chrome/i',$u_agent))
    {
        $bname = 'Google Chrome';
        $ub = "Chrome";
    }
    elseif(preg_match('/Safari/i',$u_agent))
    {
        $bname = 'Apple Safari';
        $ub = "Safari";
    }
    elseif(preg_match('/Opera/i',$u_agent))
    {
        $bname = 'Opera';
        $ub = "Opera";
    }
    elseif(preg_match('/Netscape/i',$u_agent))
    {
        $bname = 'Netscape';
        $ub = "Netscape";
    }

// finally get the correct version number
    $known = array('Version', $ub, 'other');
    $pattern = '#(?<browser>' . join('|', $known) .
        ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
    if (!preg_match_all($pattern, $u_agent, $matches)) {
        // we have no matching number just continue
    }
    $i = count($matches['browser']);
    if ($i != 1) {
        if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
            $version= $matches['version'][0];
        }
        else {
            $version= $matches['version'][1];
        }
    }
    else {
        $version= $matches['version'][0];
    }
    if ($version==null || $version=="") {$version="?";}
    return array(
        'userAgent' => $u_agent,
        'name'      => $bname,
        'version'   => $version,
        'platform'  => $platform,
        'pattern'    => $pattern
    );
}

function screenRecorder_plugin_deactivate()
{
    global $wpdb;
    $db_table_name = $wpdb->prefix . SCREENRECORDER_TABLE_NAME;
    $sql = "DROP TABLE IF EXISTS $db_table_name";
    $wpdb->query($sql);
}

function screenRecorder_plugin_activate()
{
    global $wpdb;
    $db_table_name = $wpdb->prefix . SCREENRECORDER_TABLE_NAME;
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $db_table_name (
                `id` int(11) NOT NULL auto_increment,
                `uid` int(11),
                `post_id` int(11),
                `agent` text,
                `session`  VARCHAR(100) NOT NULL,
                `url` VARCHAR(200) NOT NULL,
                `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `ip` varchar(15) NOT NULL,
                `country` varchar(3) NOT NULL,
                `name` varchar(60) NOT NULL,
                `data` longtext NOT NULL,
                `seconds` int(10)  DEFAULT '0',
                UNIQUE KEY id (id),
                INDEX (`session`),
                INDEX (`uid`)
        ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function screenRecorder_get_meta($value)
{
    global $post;

    $field = get_post_meta($post->ID, $value, true);
    if (!empty($field)) {
        return is_array($field) ? stripslashes_deep($field) : stripslashes(wp_kses_decode_entities($field));
    } else {
        return false;
    }
}

function screenRecorder_add_meta_box()
{
    add_meta_box(
        'spy-spy',
        __('Record', 'Record'),
        'screenRecorder_html',
        'post',
        'side',
        'default'
    );
    add_meta_box(
        'spy-spy',
        __('Record', 'Record'),
        'screenRecorder_html',
        'page',
        'side',
        'default'
    );
}

function screenRecorder_html($post)
{
    wp_nonce_field('_spy_nonce', 'spy_nonce');
    $checked = (screenRecorder_get_meta('spy_spy') === 'spy') ? 'checked' : '';
    $settings_options = get_option('settings_option_name'); // Array of All Options
    $spy_all_pages = $settings_options['spy_all_pages']; // Record all pages
    $spy_all_posts = $settings_options['spy_all_posts']; // Record all posts
    ?>

    <p>Should be recorded ?</p>
    <?php if ($post->post_type === 'post' && $spy_all_posts === 'spy_all_posts' || $post->post_type === 'page' && $spy_all_pages === 'spy_all_pages'): ?>
    <p><b>Will be recorded</b> because of <a href="<?= admin_url('?page=screenRecorder') ?>">global settings</a></p>
<?php else: ?>
    <input type="checkbox" name="spy_spy" id="spy_spy" value="spy" <?php echo $checked ?>>
    <label for="spy_spy"><?php _e('Record', 'Record'); ?></label>
<?php endif; ?>
    <?php
}

function screenRecorder_save($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['spy_nonce']) || !wp_verify_nonce($_POST['spy_nonce'], '_spy_nonce')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['spy_spy']))
        update_post_meta($post_id, 'spy_spy', sanitize_text_field($_POST['spy_spy']));
    else
        update_post_meta($post_id, 'spy_spy', null);
}

function screenRecorder_get_the_user_ip()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
//check ip from share internet
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
//to check ip is pass from proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return apply_filters('wpb_get_ip', $ip);
}

function screenRecorder_load_js_scripts()
{
    $cache = (ScreenRecorderSettings::$debug)?'?cache='.time():'';
    global $post;
    if (is_page() || is_single()) {
        wp_enqueue_script('rrweb', plugins_url('js/rrweb.min.js', __FILE__), array('jquery'), '', false);
        wp_enqueue_script('spy', plugins_url('js/record.js'.$cache, __FILE__), array('jquery'), '', false);
        wp_enqueue_script('lzutf8.min.js', plugins_url('js/lzutf8.min.js', __FILE__), false, '', false);
        $browser  = screenRecorder_getBrowser();
        $agent = "{$browser['name']} {$browser['version']} {$browser['platform']} ";

        $translation_array = array(
            'uid'       => time(),
            'ajaxurl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('spy_nonce'),
            'url'       => get_permalink($post),
            'session'   => wp_get_session_token(),
            'agent'     => $agent,
            'ip'        => screenRecorder_get_the_user_ip(),
            'post'      => $post,
        );
        wp_localize_script('spy', 'spyParameters', $translation_array);
    }
}

function screenRecorder_play_record() {
    $nonce  = $_POST['nonce'];
    if (!wp_verify_nonce($nonce, 'spy_nonce')) {
        die('Nonce value cannot be verified.');
    }
    global $wpdb;
    $table = $wpdb->prefix . SCREENRECORDER_TABLE_NAME;
    $uid    = intval($_POST['uid']);
    $sql = "SELECT data FROM $table WHERE uid = $uid";
    $results = $wpdb->get_results(
        $wpdb->prepare($sql)
    );
    echo json_encode($results);
    exit();
}

function screenRecorder_get_records() {
    $nonce = $_POST['nonce'];
    if (!wp_verify_nonce($nonce, 'spy_nonce')) {
        die('Nonce value cannot be verified.');
    }
    global $wpdb;
    $table = $wpdb->prefix . SCREENRECORDER_TABLE_NAME;
    $page = intval($_POST['page']);
    $start = intval(($page) * SCREENRECORDER_ROWS_PER_PAGE);
    $sql = esc_sql("SELECT SQL_CALC_FOUND_ROWS sum(seconds) as duration, id, uid, agent, post_id, session, url, date, ip, country, name FROM $table GROUP by uid order by id DESC LIMIT $start, ".SCREENRECORDER_ROWS_PER_PAGE);
    $rows = $wpdb->get_results($sql);
    $numberRows = $wpdb->get_var('SELECT FOUND_ROWS()');
    $res = array(
            'rows'          =>$rows,
            'numberRows'    =>$numberRows,
            'SCREENRECORDER_ROWS_PER_PAGE' =>SCREENRECORDER_ROWS_PER_PAGE,
        );
    echo json_encode($res);
    exit();
}

function screenRecorder_delete_all_records() {
    $nonce = $_POST['nonce'];
    if (!wp_verify_nonce($nonce, 'spy_nonce')) {
        die('Nonce value cannot be verified.');
    }
    global $wpdb;
    $table = $wpdb->prefix . SCREENRECORDER_TABLE_NAME;
    $wpdb->query("TRUNCATE TABLE $table");
}

function screenRecorder_delete_record() {
    $nonce = $_POST['nonce'];
    if (!wp_verify_nonce($nonce, 'spy_nonce')) {
        die('Nonce value cannot be verified.');
    }
    global $wpdb;
    $table = $wpdb->prefix . SCREENRECORDER_TABLE_NAME;
    $id = intval($_POST['id']);
    $wpdb->delete($table, array( 'id' => $id ) );
}

function screenRecorder_insert_data() {
    $nonce = $_POST['nonce'];
    if (!wp_verify_nonce($nonce, 'spy_nonce')) {
        die('Nonce value cannot be verified.');
    }
    global $wpdb;
    $table = $wpdb->prefix . SCREENRECORDER_TABLE_NAME;
    $data = (array(
        'uid'       => intval($_POST['uid']),
        'post_id'   => intval($_POST['post_id']),
        'agent'     => sanitize_text_field($_POST['agent']),
        'session'   => sanitize_text_field($_POST['session']),
        'url'       => sanitize_text_field($_POST['url']),
        'data'      => sanitize_text_field($_POST['data']),
        'ip'        => sanitize_text_field($_POST['ip']),
        'seconds'   => intval($_POST['seconds']),
    ));
    $format = array('%d', '%d', '%s', '%s', '%s', '%s', '%s');
    $wpdb->insert($table, $data, $format);
}
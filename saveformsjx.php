<?php
/*
 * Plugin Name: Save & Export Forms for Jupiter X
 * Description: Save your forms to the database and export them to CSV.
 * Version: 1.0.1
 * Author: Pukkas
 * Author URI: https://pukkas.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

function saveformsjx_init()
{
    try {
        require_once(get_template_directory() . '/lib/init.php');
        require_once __DIR__ . '/inc/localsave.php';
    } catch (Error $e) {
        throw new Error("You don't have Jupiter X theme.", esc_html(E_NOTICE));
    }
}

add_action('init', 'saveformsjx_init');

//Add custom css
function saveformsjx_custom_css()
{
    wp_enqueue_style('saveformsjx_custom_css', plugins_url('css/admin.css', __FILE__), array(), '1.0.0');
}
add_action('admin_enqueue_scripts', 'saveformsjx_custom_css');

function saveformsjx_jx_form_submission_post_type()
{

    register_post_type(
        'jx_form_submission',
        array(
            'labels' => array(
                'name' => esc_html__('Save & Export Forms', "saveformsjx"),
                'singular_name' => esc_html__('Save & Export Form', "saveformsjx")
            ),
            'public' => false,
            'has_archive' => false,
            'show_in_rest' => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_nav_menus'   => true,
            'capabilities' => array(
                'create_posts' => false,
            ),
            'map_meta_cap' => true,
            'menu_icon' => 'dashicons-media-spreadsheet'

        )
    );
}
add_action('init', 'saveformsjx_jx_form_submission_post_type');

function saveformsjx_menu()
{

    add_submenu_page(
        'edit.php?post_type=jx_form_submission',
        'More info',
        'More info',
        'manage_options',
        'saveformsjx',
        'saveformsjx_page'
    );
}
add_action('admin_menu', 'saveformsjx_menu');

function saveformsjx_page()
{
    echo "<div class='wrap saveformsjx'>";
    $logo = '/wp-content/plugins/saveformsjx/img/logo.png';
    echo "<img class='logo' src='" . esc_html($logo) . "' />";
    echo "<h1>Save & Export Forms for Jupiter X</h1>";
    echo "<p>This plugin allows you to save your forms to the database and export them to CSV.</p>";
    echo "<p>You'll see a new action on Forms of Jupiter X with the name of 'Local Save'.<br/>Just apply it and the form will save on the list, and you will be able to see each submission and export them to CSV.</p>";

    $example = '/wp-content/plugins/saveformsjx/img/example.png';
    echo "<img class='example' src='" . esc_html($example) . "' />";

    echo "</div>";
}


function saveformsjx_add_before_editor($post)
{
    if ('jx_form_submission' == $post->post_type) {
        remove_post_type_support('jx_form_submission', 'editor');
        do_meta_boxes('jx_form_submission', 'read_only_content', $post);
    }
}
add_action('edit_form_after_title', 'saveformsjx_add_before_editor');


function saveformsjx_read_only_content_box()
{
    $screen = get_current_screen();

    if ($screen->id === 'jx_form_submission') {

        add_meta_box(
            'saveformsjx_read_only_content_box',
            esc_html__('Datos del formulario', "saveformsjx"),
            'saveformsjx_read_only_cb',
            'jx_form_submission',
            'read_only_content',
            'low'
        );
    }
}
function saveformsjx_read_only_cb($post)
{
    $content = json_decode(base64_decode($post->post_content));

    $keys = array();
    foreach ($content as $entry) {
        $keys = array_merge($keys, array_keys((array)$entry));
    }

    $keys = array_unique($keys);

    $current_url  = esc_url(add_query_arg(array()));

    $nonce = wp_create_nonce('exportcsvaction');

    echo "<a href='" . esc_url($current_url) . "&exportcsv=", esc_attr($nonce) . "' class='button' target='_blank' style='margin-bottom:10px;margin-top:4px' >Export to CSV</a>";
    echo "<table style='text-align:left;width:100%' class='wp-list-table widefat fixed striped table-view-list ' style='width:100%'>
               <thead>
                   <tr>";
    foreach ($keys as $key) {
        echo "<th>" . esc_html(ucfirst($key)) . "</th>";
    }
    echo "</tr>
           </thead>
           <tbody>";
    foreach ($content as $entry) {
        echo "<tr>";
        foreach ($keys as $key) {
            echo "<td>" . esc_html($entry->$key) . "</td>";
        }
        echo "</tr>";
    }
    echo "</tbody>
           </table>";
}
add_action('add_meta_boxes', 'saveformsjx_read_only_content_box');

function saveformsjx_exportforms()
{
    $nonce = isset($_GET['exportcsv']) ? sanitize_text_field(wp_unslash($_GET['exportcsv'])) : null;
    if (wp_verify_nonce($nonce, 'exportcsvaction')) {
        $post = get_post(isset($_GET['post']) ? sanitize_text_field(wp_unslash($_GET['post'])) : null);

        $content = json_decode(base64_decode($post->post_content));

        $keys = array();
        foreach ($content as $entry) {
            $keys = array_merge($keys, array_keys((array)$entry));
        }

        $keys = array_unique($keys);

        $csv = "";
        foreach ($keys as $key) {
            $csv .= '"' . $key . '",';
        }
        $csv = rtrim($csv, ",");
        $csv .= "\n";

        foreach ($content as $entry) {
            foreach ($keys as $key) {
                $csv .= '"' . $entry->$key . '",';
            }
            $csv = rtrim($csv, ",");
            $csv .= "\n";
        }

        global $wp_filesystem;

        // Initialize the WP_Filesystem
        if (!function_exists('WP_Filesystem')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        WP_Filesystem();

        // Define the file path (you can adjust the directory as needed)
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . "export-saveformsjx.csv";

        // Write the CSV content to the file
        if (!$wp_filesystem->put_contents($file_path, $csv, FS_CHMOD_FILE)) {
            // Handle error
            wp_die('Failed to write the CSV file.');
        }

        header("Location: " . $upload_dir['url'] . '/' . "export-saveformsjx.csv");

        die();
    }
}
add_action("admin_init", "saveformsjx_exportforms");

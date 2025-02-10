<?php
/*
Plugin Name: Image Format Converter
Description: Converts images from one format to another.
Version: 1.0
Author: Amanuel
*/

defined('ABSPATH') || exit;

// Add admin menu
add_action('admin_menu', 'ic_add_admin_menu');
function ic_add_admin_menu()
{
    add_menu_page(
        'Image Converter',
        'Image Converter',
        'manage_options',
        'image-converter',
        'ic_admin_page',
        'dashicons-format-image'
    );
}

// Admin page content
function ic_admin_page()
{
    if (!current_user_can('manage_options')) return;
    $supported_formats = ic_get_supported_formats();
    include plugin_dir_path(__FILE__) . 'admin-page.php';
}

// Get supported target formats
function ic_get_supported_formats()
{
    $formats = [];
    $mime_types = [
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    ];

    foreach ($mime_types as $format => $mime) {
        if (wp_image_editor_supports(['mime_type' => $mime])) {
            $formats[$format] = strtoupper($format);
        }
    }

    return $formats;
}

// Enqueue admin scripts
add_action('admin_enqueue_scripts', 'ic_enqueue_admin_scripts');
function ic_enqueue_admin_scripts($hook)
{
    if ($hook !== 'toplevel_page_image-converter') return;

    wp_enqueue_media();
    wp_enqueue_script(
        'ic-admin-js',
        plugins_url('admin.js', __FILE__),
        ['jquery', 'media-views'],
        '1.0',
        true
    );

    wp_localize_script('ic-admin-js', 'ic_vars', [
        'formats' => ic_get_allowed_source_mimes(),
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ic-nonce'),
    ]);
}

// AJAX handler for image conversion
add_action('wp_ajax_ic_convert_images', 'ic_ajax_convert_images');
function ic_ajax_convert_images()
{
    check_ajax_referer('ic-nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $attachments = $_POST['attachment_ids'] ?? [];
    $target_format = sanitize_text_field($_POST['target_format'] ?? '');
    $supported = ic_get_supported_formats();

    if (!isset($supported[$target_format])) {
        wp_send_json_error('Unsupported format');
    }

    foreach ($attachments as $attachment_id) {
        ic_convert_image(absint($attachment_id), $target_format);
    }

    wp_send_json_success();
}

// Convert individual image
function ic_convert_image($attachment_id, $target_format)
{
    $mime_map = [
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    ];
    $target_mime = $mime_map[$target_format] ?? '';

    $file_path = get_attached_file($attachment_id);
    if (!$file_path) return new WP_Error('file_not_found', 'File not found');

    $source_mime = get_post_mime_type($attachment_id);
    $allowed_mimes = ic_get_allowed_source_mimes()[$target_format] ?? [];
    if (!in_array($source_mime, $allowed_mimes)) {
        return new WP_Error('invalid_source', 'Invalid source type');
    }

    $editor = wp_get_image_editor($file_path);
    if (is_wp_error($editor)) return $editor;

    $new_file = preg_replace('/\.[^.]+$/', ".$target_format", $file_path);
    $result = $editor->save($new_file, $target_mime);
    if (is_wp_error($result)) return $result;

    // Update attachment
    update_attached_file($attachment_id, $new_file);
    wp_update_post([
        'ID' => $attachment_id,
        'post_mime_type' => $target_mime,
        'guid' => wp_get_attachment_url($attachment_id),
    ]);

    // Regenerate metadata
    $meta = wp_generate_attachment_metadata($attachment_id, $new_file);
    wp_update_attachment_metadata($attachment_id, $meta);

    // Delete old files
    $old_dir = dirname($file_path);
    array_map('unlink', glob("$old_dir/*." . pathinfo($file_path, PATHINFO_EXTENSION)));

    return true;
}

// Get allowed source MIME types
function ic_get_allowed_source_mimes()
{
    return [
        'webp' => ['image/jpeg', 'image/png'],
        'avif' => ['image/jpeg', 'image/png'],
        'jpeg' => ['image/png', 'image/webp'],
        'png'  => ['image/jpeg', 'image/webp'],
    ];
}

// Filter media library query
add_filter('ajax_query_attachments_args', 'ic_filter_media_library');
function ic_filter_media_library($args)
{
    if (!empty($_POST['query']['ic_target_format'])) {
        $target = $_POST['query']['ic_target_format'];
        $args['post_mime_type'] = ic_get_allowed_source_mimes()[$target] ?? [];
    }
    return $args;
}

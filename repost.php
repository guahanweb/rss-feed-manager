<?php
/**
 * This endpoint will allow for creating a new post on behalf of
 * a contributor feed.
 */

function reply($data) {
    header('Content-type: application/json');
    echo json_encode($data);
    exit;
}

function get_wp_base() {
    $dir = dirname(__FILE__);
    do {
        if (file_exists($dir . '/wp-config.php')) {
            return $dir;
        }
    } while ($dir !== '/' && $dir = realpath("$dir/.."));
    return null;
}

define('BASE_PATH', get_wp_base());
if (is_null(BASE_PATH)) {
    reply(array(
        'success' => false,
        'errmsg' => 'Script only available from within WordPress context'
    ));
}

// Make the WP base function usable
define('WP_USE_THEMES', false);
global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
require(BASE_PATH . 'wp-load.php');

$supported_actions = array('repost');
$action = isset($_POST['action']) ? trim($_POST['action']) : null;

// restrict access to only known actions
if (is_null($action) || !in_array($action, $supported_actions)) {
    reply(array(
        'success' => false,
        'errmsg' => 'Unsupported action requested'
    ));
}

// make sure we lock down to user capabilities
if (!current_user_can('edit_posts')) {
    reply(array(
        'success' => false,
        'errmsg' => 'User has insufficient permissions'
    ));
}

$post = array(
    'post_author' => isset($_POST['author']) ? intval($_POST['author']) : null,
    'post_title' => isset($_POST['title']) ? trim($_POST['title']) : null,
    'post_content' => isset($_POST['content']) ? trim($_POST['content']) : null,
    'post_status' => 'draft',
    'post_type' => 'post'
);

// validation
if (is_null($post['post_title']) || is_null($post['post_author'])) {
    reply(array(
        'success' => false,
        'errmsg' => 'At least [title] and [author] are required'
    ));
}

// insert the post
$id = wp_insert_post($post);
if ($id == 0) {
    reply(array(
        'success' => false,
        'errmsg' => 'Could not create new post'
    ));
}

// any additional adjustments or post meta here

$edit_link = get_edit_post_link($id, '');
reply(array(
    'success' => true,
    'post' => array(
        'id' => $id,
        // intent is to immediately redirect to edit and publish
        'edit_link' => $edit_link
    )
));

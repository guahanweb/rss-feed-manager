<?php
function get_wp_base() {
	$dir = dirname(__FILE__);
	do {
		if (file_exists($dir . '/wp-config.php')) {
			return $dir;
		}
	} while ($dir = realpath("$dir/.."));
	return null;
}

define('BASE_PATH', get_wp_base().'/');

// Make the WP base function usable
define('WP_USE_THEMES', false);
global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
require(BASE_PATH . 'wp-load.php');

require_once('lib/SimplePie/autoloader.php');
$rss = new SimplePie();
$rss->set_cache_duration(3600); // 1 hour cache
$rss->set_cache_location(dirname(__FILE__) . '/lib/SimplePie/cache/');
$display_limit = 3;
$highlight_offset = time() - (60 * 60 * 2); // 2 hours ago

$TIME_OFFSET = 4 * 60 * 60; // 4 hours

// Load Feeds by Category
$data = array(
	'success' => true,
	'errmsg'  => ''
);

function sendResponse($data) {
	header('Content-type: application/json');
	echo json_encode($data);
	exit;
}

$category = isset($_GET['cat']) ? trim($_GET['cat']) : NULL;
if (NULL === $category) {
	$data['success'] = false;
	$data['errmsg'] = 'No category provided';
	sendResponse($data);
}

/** START CONTRIBUTOR SECTION **/

function getAuthorEditUrl($id) {
	return sprintf('%s?user_id=%d', admin_url('user-edit.php'), $id);
}

function sortUsers($users) {
	$with = array();
	$without = array();
	
	foreach ($users as $user) {
		$feed = get_usermeta($user->ID, 'profilewebsiteRSS');
		if (empty($feed)) {
			$without[] = $user;
		} else {
			$with[] = $user;
		}
	}
	
	return array_merge($with, $without);
}

if ($category === 'contributors') {
	$users = sortUsers(get_users(array('role' => 'contributor')));
	
	$feeds = array();
    $full_list = array();
	foreach ($users as $user) {
		$author = array(
			'website' => get_usermeta($user->ID, 'profilewebsitename'),
			'websiteurl' => get_userdata($user->ID)->user_url,
			'handling' => get_usermeta($user->ID, 'profilehandlinginstructions'),
			'author' => get_userdata($user->ID)->display_name,
			'authorurl' => get_author_posts_url($user->ID),
			'editurl' => getAuthorEditUrl($user->ID),
			'feedurl' => get_usermeta($user->ID, 'profilewebsiteRSS')
		);
		
		$items = array();
		if (!empty($author['feedurl'])) {
			$items = get_usermeta($user->ID, 'feed_current_items');
			if (is_array($items)) {
				foreach ($items as $k => $item) {
					$items[$k]['highlighted'] = (strtotime($item['postDate']) > $highlight_offset) ? TRUE : FALSE;
					$items[$k]['new'] = (time() - $TIME_OFFSET) < strtotime($item['postDate']) ? TRUE : FALSE;
				}
			} else {
				$items = array();
			}
		}

        $full_list = array_merge($full_list, $items);
		$feeds[] = array(
			'author' => $author,
			'items' => $items
		);
	}
	
	$data['category'] = $category;
	$data['feeds'] = $feeds;
    $data['list'] = $full_list;
	sendResponse($data);
}

/** END CONTRIBUTOR SECTION **/

// Fall back to standard cats
$feeds = array();
$params = array(
	'post_type' => 'rss_manager_post',
	'rss_feed_category' => $category,
	'post_status' => 'publish',
	'posts_per_page' => -1,
	'caller_get_post' => 1
);

$posts = get_posts($params);
foreach ($posts as $post) {
	$feeds[$post->ID] = array(
		'title' => $post->post_title,
		'items' => array()
	);
	
	$items = get_post_meta($post->ID, 'current_records');
	if (empty($items)) {
		$items = array(array());
	}
	
	foreach ($items[0] as $item) {
		$ts = strtotime($item['postDate']);
		$item['new'] = (time() - $TIME_OFFSET) < $ts ? TRUE : FALSE;
		$desc = substr(strip_tags($item['description']), 0, 200);
		$feeds[$post->ID]['items'][] = array(
			'title' => $item['title'],
			'postDate' => $item['pubDate'],
			'link' => $item['link'],
			'new' => $item['new'],
			'description' => $desc,
			'highlighted' => (strtotime($item['pubDate']) > $highlight_offset) ? TRUE : FALSE
		);
	}
}

$data['category'] = $category;
$data['feeds'] = $feeds;
sendResponse($data);
?>

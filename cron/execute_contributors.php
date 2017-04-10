<?php
// Cache all contributor feeds
$users = array();
$feeds = array();
$tmp = get_users(array('role' => 'contributor'));
foreach ($tmp as $t) {
	$feed = get_usermeta($t->ID, 'profilewebsiteRSS');
	if (!empty($feed)) {
		$users[] = $t;
		$feeds[$t->ID] = $feed;
	}
}

$fetch_limit = 3;
$logger->info(sprintf("Fetching %d contributor feeds", count($users)));
foreach ($users as $user) {
	$data = array();
	$feed = $feeds[$user->ID];

	$rss->set_feed_url($feed);
	$rss->init();
	$rss->handle_content_type();
	$items = $rss->get_items(0, $fetch_limit);
	for ($i = 0; $i < count($items); $i++) {
		$data[] = array(
			'title' => $items[$i]->get_title(),
			'link' => $items[$i]->get_permalink(),
			'postDate' => $items[$i]->get_date('j M Y, g:i a')
		);
	}
	
	if (count($data) > 0) {
		update_usermeta($user->ID, 'feed_current_items', $data);
		$logger->info(sprintf("Contributor Feed ID [%d] updated with %d items", $user->ID, count($data)));
	}

	update_usermeta($user->ID, 'feed_last_fetch_time', date('Y-m-d H:i:s'));		
}
?>

<?php
// Get all the News Feeds
$feeds = get_posts(array('post_type' => 'rss_manager_post', 'posts_per_page' => -1));
$logger->info(sprintf('Retrieved %d feeds from the WP database', count($feeds)));
foreach ($feeds as $feed) {
	$last_fetch_time = get_post_meta($feed->ID, 'last_fetch_time');
	$frequency = get_post_meta($feed->ID, 'rss_manager_post_frequency');
	$next_fetch_ts = strtotime($last_fetch_time[0]) + (intval($frequency[0]) * 60);

	if (time() >= $next_fetch_ts) {
		$logger->info(sprintf('News Feed ID [%d] out of date; starting fetch', $feed->ID));

		// We have exceeded the defined expiration, so retrieve the next X number
		$url   = get_post_meta($feed->ID, 'rss_manager_post_url');
		$url   = $url[0];
		
		$limit = get_post_meta($feed->ID, 'rss_manager_post_limit');
		$limit = $limit[0];
		
		// Purge current items from DB
		$wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE post_id = %d", $feed->ID));
		
		// Fetch next X items from RSS feed
		$rss->set_feed_url($url);
		$rss->init();
		header('Content-Type: text/plain');
		$items = $rss->get_items(0, $limit);
		
		// If we retrieved at least 1 record, update the DB
		if (count($items) > 0) {
			$data = array();
			$values = array();
			foreach ($items as $item) {
				$data[] = array(
					'title' => $item->get_title(),
					'link'  => $item->get_permalink(),
					'description' => $item->get_description(),
					'postDate'    => $item->get_date('Y-m-d H:i:s')
				);
				
				$values[] = sprintf("(%d, '%s', '%s', '%s', '%s')",
					$feed->ID,
					mysql_real_escape_string($item->get_title()),
					mysql_real_escape_string($item->get_permalink()),
					mysql_real_escape_string($item->get_description()),
					$item->get_date('Y-m-d H:i:s')
				);
			}
		
			$sql = sprintf("INSERT INTO $table_name (post_id, title, link, description, pubDate) VALUES %s", implode(', ', $values));
			if (FALSE !== ($sql = $wpdb->query($sql))) {
				update_post_meta($feed->ID, 'current_records', $data);
				update_post_meta($feed->ID, 'last_fetch_time', date('Y-m-d H:i:s'));
			} else {
				$logger->err(mysql_error());
			}
		} else {
			$logger->warn(sprintf('Failed to retrieve any posts for News Feed: [%d] "%s"', $feed->ID, $feed->post_title));
		}
	} else {
		$logger->info(sprintf('News Feed ID [%d] still current', $feed->ID));
	}
}
update_option('rss-feed-manager-updated', date('Y-m-d H:i:s'));
$logger->info('News Feed fetching complete');
?>

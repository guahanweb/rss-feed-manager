<?php
/* 
Plugin Name: RSS Feed Manager 
Plugin URI: http://www.guahanweb.com
Description: Manages your static RSS feeds
Version: 0.1
Author: Garth Henson
Author URI: http://www.guahanweb.com
*/

class RSSFeedManager {
	private $version = "0.1";
	private $options = 'rssmanager_data_1';
	private $taxonomy = 'rss_feed_category';
	private $post_type = 'rss_manager_post';
	
	private $fields;
 	private $table;

	public static function getTable() {
		return 'rssmanager_items';
	}

	public function __construct() {
		global $wpdb;
		$this->fields = array(
			'title' => $this->post_type . '_title',
			'url' => $this->post_type . '_url',
			'desc' => $this->post_type . '_desc',
			'notes' => $this->post_type . '_notes',
			'limit' => $this->post_type . '_limit',
			'frequency' => $this->post_type . '_frequency',
			'republish' => $this->post_type . '_republish',
			'contributor' => $this->post_type . '_contributor'
		);
		
		$this->table = $wpdb->prefix . self::getTable();
		$this->can_exec = function_exists('exec');
		$this->listen();
	}
	
	public function activate() {
		if (get_option($this->options) == '') {
			$this->install();
		}
		update_option('rssmanager_activated', time());
	}
	
	public function deactivate() {
		// Clean up options
		delete_option($this->options);
		delete_option('rssmanager_activated');
	}
	
	private function install() {
		global $wpdb;
		$sql = "CREATE TABLE {$this->table} (
			id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
			post_id INTEGER UNSIGNED NOT NULL,
			title TINYTEXT NOT NULL DEFAULT '',
			link TINYTEXT NOT NULL DEFAULT '',
			description TEXT NOT NULL DEFAULT '',
			pubDate DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00'
		);";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
		update_option($this->options, array(
			'version' => $this->version,
			'exec' => $this->can_exec
		));
	}
	
	public function createPostType() {
		register_post_type($this->post_type, array(
			'labels' => array(
				'name' => __('News Feeds'),
				'singular_name' => __('News Feed'),
				'menu_name' => __('News Feeds'),
				'all_items' => __('All News Feeds'),
				'add_new' => __('Add New'),
				'add_new_item' => __('Add News Feed'),
				'edit_item' => __('Edit News Feed'),
				'new_item' => __('New News Feed'),
				'view_item' => __('View News Feed')
			),
			'public' => TRUE,
			'menu_position' => 100,
			'menu_icon' => plugins_url() . '/rss-feed-manager/images/feed.png',
			'has_archive' => FALSE,
			'rewrite' => array('slug' => 'newsfeeds'),
			'supports' => FALSE // No default fields
		));
	}
	
	public function renderConnectionInfoForm($post) {
		$title = esc_html(get_post_meta($post->ID, $this->fields['title'], TRUE));
		$url   = esc_html(get_post_meta($post->ID, $this->fields['url'], TRUE));
		$desc  = esc_html(get_post_meta($post->ID, $this->fields['desc'], TRUE));
		$notes = esc_html(get_post_meta($post->ID, $this->fields['notes'], TRUE));
		
		echo <<<EOF
<table>
	<tbody>
		<tr>
			<td>Title</td>
			<td><input type="text" size="80" name="{$this->fields['title']}" value="$title" /></td>
		</tr>
		<tr>
			<td>URL</td>
			<td><input type="text" size="80" name="{$this->fields['url']}" value="$url" /></td>
		</tr>
		<tr>
			<td>Description</td>
			<td><textarea name="{$this->fields['desc']}" cols="80" rows="5">$desc</textarea></td>
		</tr>
		<tr>
			<td>Notes</td>
			<td><input type="text" size="80" name="{$this->fields['notes']}" value="$notes" /></td>
		</tr>
	</tbody>
</table>
EOF;
	}
	
	public function renderUpdateRulesForm($post) {
		$limit = intval(get_post_meta($post->ID, $this->fields['limit'], TRUE));
		$frequency = intval(get_post_meta($post->ID, $this->fields['frequency'], TRUE));
		$republish = intval(get_post_meta($post->ID, $this->fields['republish'], TRUE));
		$contributor = intval(get_post_meta($post->ID, $this->fields['contributor'], TRUE));
		
		$limit = (!$limit) ? 10 : $limit;
		$frequency = (!$frequency) ? 60 : $frequency;
		$republish = (!$republish) ? '' : ' checked="checked"';
		$contributor = (!$contributor) ? '' : ' checked="checked"';
		
		echo <<<EOF
<table>
	<tbody>
		<tr>
			<td>Record Limit<br /><small>(Number of recent records to hold)</small></td>
			<td><input type="text" name="{$this->fields['limit']}" value="$limit" size="4" maxlength="2" /></td>
		</tr>
		<tr>
			<td>Frequency<br /><small>(Minutes between updates from remote site)</small></td>
			<td><input type="text" name="{$this->fields['frequency']}" value="$frequency" size="4" maxlength="3" /></td>
		</tr>
		<tr>
			<td>Republishing Allowed<br /><small>(Indicates open publish policy)</small></td>
			<td><input type="checkbox" name="{$this->fields['republish']}" value="1" $republish /></td>
		</tr>
		<tr>
			<td>Verified Contributor</td>
			<td><input type="checkbox" name="{$this->fields['contributor']}" value="1" $contributor /></td>
		</tr>
	</tbody>
</table>
EOF;
	}
	
	public function saveFeed($post_id) {
		if ($_POST['post_type'] !== $this->post_type) {
			return;
		}
		
		$fields = array('title', 'url', 'desc', 'notes', 'limit', 'frequency', 'republish', 'contributor');
		foreach ($fields as $f) {
			$$f = isset($_POST[$this->fields[$f]]) ? $_POST[$this->fields[$f]] : '';
			update_post_meta($post_id, $this->fields[$f], $$f);
			
			if ($f == 'frequency') {
				update_post_meta($post_id, 'last_fetch_time', date('Y-m-d H:i:s', time() - (intval($$f) * 60)));
			}
		}
	}
	
	public function setTitle($title) {
		if (isset($_POST['post_type']) && $_POST['post_type'] == $this->post_type) {
			$title = trim($_POST[$this->fields['title']]);
		}
		return $title;
	}
	
	public function addMetaBoxes() {
		// Basic Info
		add_meta_box(
			$this->post_type . '_info_box',
			__('Basic Info'),
			array($this, 'renderConnectionInfoForm'),
			$this->post_type,
			'normal',
			'high'
		);
		
		// Update Rules
		add_meta_box(
			$this->post_type . '_update_rules',
			__('Update Rules'),
			array($this, 'renderUpdateRulesForm'),
			$this->post_type,
			'normal',
			'high'
		);
	}
	
	public function addOverviewColumns($columns) {
		$columns[$this->post_type . '_url'] = 'URL';
		$columns[$this->post_type . '_count'] = 'Items';
		$columns[$this->post_type . '_lastfetch'] = 'Last Fetch';
		$columns[$this->post_type . '_nextfetch'] = 'Next Fetch';
		unset($columns['date']);
		unset($columns['viewscolumn']);
		return $columns;
	}
	
	public function populateOverviewColumns($column) {
		if ($column == $this->post_type . '_url') {
			$text = get_post_meta(get_the_ID(), $this->post_type . '_url');
			printf('<a href="%s" target="_blank">%s</a>', $text[0], $text[0]);
		} elseif ($column == $this->post_type . '_count') {
			$items = get_post_meta(get_the_ID(), 'current_records');
			$count = count($items[0]);
			echo $count;
		} elseif ($column == $this->post_type . '_lastfetch') {
			$time = get_post_meta(get_the_ID(), 'last_fetch_time');
			echo date('F j, Y h:ia', strtotime($time[0]));
		} elseif ($column == $this->post_type . '_nextfetch') {
			$time = get_post_meta(get_the_ID(), 'last_fetch_time');
			$freq = get_post_meta(get_the_ID(), $this->post_type . '_frequency');
			echo date('F j, Y h:ia', strtotime($time[0]) + ($freq[0] * 60));
		}
	}
	
	public function createFeedCategories() {
		register_taxonomy($this->taxonomy, $this->post_type, array(
			'hierarchical' => true,
			'labels' => array(
				'name' => _x('RSS Categories', 'taxonomy general name'),
				'singular_name' => _x('RSS Category', 'taxonomy singular name'),
				'search_items' => __('Search RSS Categories'),
				'all_items' => __('All RSS Categories'),
				'parent_item' => __('Parent Category'),
				'parent_item_colon' => __('Parent Category:'),
				'edit_item' => __('Edit Category'),
				'update_item' => __('Update Category'),
				'add_new_item' => __('Add New RSS Category'),
				'new_item_name' => __('New RSS Category Name')
			)
		));
	}
	
	protected function listen() {
		// Activation hooks
		register_activation_hook(__FILE__, array($this, 'activate'));
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));
		
		// Taxonomy definition
		add_action('init', array($this, 'createFeedCategories'));
		
		// New post type
		add_action('init', array($this, 'createPostType'));
		add_action('add_meta_boxes', array($this, 'addMetaBoxes'));
		add_action('save_post', array($this, 'saveFeed'));
		add_filter('title_save_pre', array($this, 'setTitle'));
		
		// Set up admin columns
		add_filter('manage_edit-' . $this->post_type . '_columns', array($this, 'addOverviewColumns'));
		add_action('manage_posts_custom_column', array($this, 'populateOverviewColumns'));
	}
}

$rssmanager = new RSSFeedManager();
?>
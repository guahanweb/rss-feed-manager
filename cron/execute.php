<?php
class PHPLogger {
	protected $path = '/tmp';
	protected $filename;
	protected $fp;
	protected $verbose = FALSE;
	
	public function __construct($filename, $path = NULL) {
		if (NULL !== $path && file_exists($path)) {
			$this->path = $path;
		}
		
		if (preg_match('/^(.*)\\.([^.\\s]{3,4})$/', $filename, $match)) {
			$file = $match[1] . '.' . date('Y-m-d') . '.' . $match[2];
		}
		
		$this->filename = $this->path . '/' . $file;
	}
	
	public function setVerbose($bool) {
		$this->verbose = (bool) $bool;
	}
	
	public function info($msg) {
		if (TRUE === $this->verbose) {
			$this->write($msg, 'INFO');
		}
	}
	
	public function warn($msg) {
		$this->write($msg, 'WARN');
	}
	
	public function err($msg) {
		$this->write($msg, 'ERROR');
	}
	
	public function close() {
		if (is_resource($this->fp)) {			
			fclose($this->fp);
		}
	}
	
	protected function open() {
		if (!is_resource($this->fp)) {
			$this->fp = @fopen($this->filename, 'a+');
		}
	}
	
	protected function write($msg, $type) {
		$this->open();
		$script = pathinfo(__FILE__, PATHINFO_FILENAME);
		$time = date('Y-m-d H:i:s');
		@fwrite($this->fp, "$time [$type] ($script) $msg" . PHP_EOL);
	}
}

function get_wp_base() {
	$dir = dirname(__FILE__);
	do {
		if (file_exists($dir . '/wp-config.php')) {
			return $dir;
		}
	} while ($dir = realpath("$dir/.."));
	return null;
}

$MODE = 'all';
if (isset($_GET['mode']) || isset($argv[1])) {
	$val = isset($_GET['mode']) ? trim($_GET['mode']) : $argv[1];
	if ($val == 'news' || $val == 'contributors') {
		$MODE = $val;
	}
}

define('BASE_PATH', get_wp_base().'/');
$logger = new PHPLogger('FeedManager.txt', BASE_PATH . 'wp-content/plugins/rss-feed-manager/logs');
$logger->setVerbose(TRUE);

// Make the WP base function usable
define('WP_USE_THEMES', false);
$logger->info('Starting News Feed Fetcher');
global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
require(BASE_PATH . 'wp-load.php');

$table_name = $wpdb->prefix . 'rssmanager_items';
$logger->info('WordPress tie-in complete');

// Prep SimplePie reader
require_once(BASE_PATH . 'wp-content/plugins/rss-feed-manager/lib/SimplePie/autoloader.php');
$rss = new SimplePie();
$rss->enable_cache(FALSE);
$logger->info('SimplePie initialization complete');

switch ($MODE) {
	case 'news':
		require('execute_news.php');
		break;
		
	case 'contributors':
		require('execute_contributors.php');
		break;
		
	default:
		require('execute_news.php');
		require('execute_contributors.php');
}

$logger->close();
?>

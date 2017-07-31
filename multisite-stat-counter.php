<?php
/**
 * Plugin Name:       Multisite Stats
 * Plugin URI:        http://deeptiboddapati.com
 * Description:       This adds a widget that displays the number of posts and users per site in your Multisite install.
 * Version:           1.0
 * Author:           Deepti Boddapati
 * Author URI:        http://deeptiboddapati.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       multisite-stats
 */

namespace MULTISITE_STATS;
// If this file is called directly, abort.
// if ( ! defined( 'WPINC' ) ) {
// 	die;
// }

class UserFields {

	function __construct() {
		add_filter( 'rest_user_query',           [ $this, 'show_all_users' ] );
	}
	function show_all_users( $prepared_args ) {
		unset( $prepared_args[ 'has_published_posts' ] );
		return $prepared_args;
	}
}
new UserFields();
/**
 * Summary.
 *
 * Description.
 *
 * @since 1.0
 */
function get_network_stats() {
	$sites = get_sites();
	$stats = array();
	foreach ( $sites as $site ) {
		$domain = $site->domain;
		$site_users = get_site_user_count( $domain );
		$site_posts = get_site_post_count( $domain );
		$site_stats = array();
		$site_stats[ 'domain' ] = $domain;
		$site_stats[ 'site_users' ] = $site_users;
		$site_stats[ 'site_posts' ] = $site_posts;
		array_push( $stats, $site_stats );
	}
	set_site_transient( 'network_stats', $stats, 1 * HOUR_IN_SECONDS);
}
//smallest interval is hourly
if ( ! wp_next_scheduled( 'refresh_network_stats' ) ) {
  wp_schedule_event( time(), 'hourly', 'refresh_network_stats' );
}

add_action( 'refresh_network_stats', 'MULTISITE_STATS\get_network_stats' );


/**
 * Summary.
 *
 * Description.
 *
 * @since 1.0
 *
 * @param string $site_domain Description.
 * @return int $user_count Users in site.
 */
function get_site_user_count( $site_domain ) {
	$user_count = 0;
	$endpointurl = 'http://' . $site_domain . '/wp-json/wp/v2/users';
	$response = wp_remote_get( $endpointurl );
	$user_count = wp_remote_retrieve_header( $response, 'x-wp-total' );
	return $user_count;
}

/**
 * Summary.
 *
 * Description.
 *
 * @since 1.0
 *
 * @param string $site_domain Description.
 * @return int $post_count Posts in site.
 */
function get_site_post_count( $site_domain ) {
	$post_count = 0;
	$endpointurl = 'http://' . $site_domain . '/wp-json/wp/v2/posts';
	$response = wp_remote_get( $endpointurl );
	$post_count = wp_remote_retrieve_header( $response, 'x-wp-total' );
	return $post_count;
}

function network_stats_endpoint(){
	$stats = get_site_transient( 'network_stats' );
	if ( ! $stats ){
		get_network_stats();
		$stats = get_site_transient( 'network_stats' );
	}
	return $stats;
}
/**
 * Summary.
 *
 * Description.
 *
 * @since 1.0
 *
 * @param string $site_domain Description.
 * @return int $post_count Posts in site.
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'multisitestats/v1', '/stats/', array(
		'methods' => 'GET',
		'callback' => 'MULTISITE_STATS\network_stats_endpoint',
	) );
} );


class MultiSiteStats extends \WP_Widget {

	/**
	 * Sets up the widgets name etc
	 */
	public function __construct() {
		$widget_ops = array( 
			'classname' => 'my_widget',
			'description' => 'My Widget is awesome',
		);
		parent::__construct( 'my_widget', 'My Widget', $widget_ops );
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$endpointurl = get_site_url( ) . '/wp-json/multisitestats/v1/stats/';
		$response = wp_remote_get( $endpointurl );
		$body = wp_remote_retrieve_body( $response );
		$stats = json_decode( $body );
		?>
			<h2>Multisite Statistics</h2>
			<table>
				<tr>
					<th>Domain</th>
					<th>Users</th>
					<th>Posts</th>
				</tr>
			
		<?php foreach ( $stats as $stat ) { ?>
			<tr>
				<td><?php echo $stat->domain; ?> </td>
				<td> <?php echo $stat->site_users; ?></td>
				<td> <?php echo $stat->site_posts; ?></td>
			</tr>
			
		<?php } ?>
			</table>
		<?php
	}

}

add_action( 'widgets_init', function(){
	register_widget( 'Multisite_Stats\MultiSiteStats' );
});


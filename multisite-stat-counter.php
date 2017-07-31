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
 *
 * @package MULTISITE_STATS\
 */

namespace MULTISITE_STATS;
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Enables Users Endpoint to show all users.
 *
 * Filtered the users query so that the endpoint shows unpublished and published users.
 *
 * @since 1.0
 *
 * @link https://github.com/WP-API/WP-API/issues/2300
 */
class UserFields {
	/**
	 * Filters users endpoint.
	 *
	 * Adds filter to Rest API Users endpoint that will
	 * allow the endpoint to show all users not just published ones.
	 *
	 * @since 1.0
	 */
	function __construct() {
		add_filter( 'rest_user_query', [ $this, 'show_all_users' ] );
	}

	/**
	 * Removes the check for published posts.
	 *
	 * Unsets the 'has_published_posts' key fron the rest api endpoint args.
	 *
	 * @since 1.0
	 * @param  array $prepared_args Arguments passed to the user query.
	 */
	function show_all_users( $prepared_args ) {
		unset( $prepared_args['has_published_posts'] );
		return $prepared_args;
	}
}
new UserFields();

/**
 * Manages the network stats transient and endpoint.
 *
 * Adds and updates network stats to transients.
 * Registers custom endpoint to output the network stats.
 *
 * @since 1.0
 *
 * @see \WP_Widget
 */
class Multisite_Stats {
	/**
	 * Constructs the Multisite_Stats Class
	 *
	 * Registers the Cron and the rest route for Multisite stats.
	 *
	 * @since 1.0
	 */
	public function __construct() {
		if ( is_main_site() ) {
			// The smallest interval possible for WordPress cron is hourly.
			if ( ! wp_next_scheduled( 'refresh_network_stats' ) ) {
				wp_schedule_event( time(), 'hourly', 'refresh_network_stats' );
			}
			add_action( 'refresh_network_stats', array( $this, 'network_stats_endpoint' ) );
			add_action( 'rest_api_init', function () {
				register_rest_route( 'multisitestats/v1', '/stats/', array(
					'methods' => 'GET',
					'callback' => array( $this, 'network_stats_endpoint' ),
				) );
			} );
		}
	}
	/**
	 * Refreshes network wide statistics.
	 *
	 * Refreshes statistics for the whole network. Sets them as a transient that expires hourly.
	 *
	 * @since 1.0
	 */
	function refresh_network_stats() {
		$stats = get_site_transient( 'network_stats' );
		if ( ! $stats ) {
			$sites = get_sites();
			$stats = array();
			foreach ( $sites as $site ) {
				$domain = $site->domain;
				$site_users = $this->get_blog_user_count( $domain );
				$site_posts = $this->get_blog_post_count( $domain );
				$site_stats = array();
				$site_stats['domain'] = esc_url( $domain );
				$site_stats['site_users'] = intval( $site_users );
				$site_stats['site_posts'] = intval( $site_posts );
				array_push( $stats, $site_stats );
			}
			set_site_transient( 'network_stats', $stats, 1 * HOUR_IN_SECONDS );
		}
	}

	/**
	 * Gets the number of users on a blog.
	 *
	 * Gets the number of users on a single blog using the blog domain and the rest api.
	 *
	 * @since 1.0
	 *
	 * @param string $site_domain Description.
	 * @return int $user_count Users in blog.
	 */
	function get_blog_user_count( $site_domain ) {
		$user_count = 0;
		$endpointurl = 'http://' . $site_domain . '/wp-json/wp/v2/users';
		$response = wp_remote_get( $endpointurl );
		$user_count = wp_remote_retrieve_header( $response, 'x-wp-total' );
		return $user_count;
	}

	/**
	 * Gets the number of posts on a blog.
	 *
	 * Gets the number of posts on a single blog using the blog domain and the rest api.
	 *
	 * @since 1.0
	 *
	 * @param string $site_domain Description.
	 * @return int $post_count Posts in blog.
	 */
	function get_blog_post_count( $site_domain ) {
		$post_count = 0;
		$endpointurl = esc_url( $site_domain . '/wp-json/wp/v2/posts' );
		// The phpcs error/reccomendation to use vip_safe_remote_get() doesn't apply to my environment.
		$response = wp_remote_get( $endpointurl );
		$post_count = wp_remote_retrieve_header( $response, 'x-wp-total' );
		return $post_count;
	}
	/**
	 * Returns an array of network stats.
	 *
	 * Checks to see if the transient for network stats is set.
	 * If it's not set it refreshes the network stats.
	 *
	 * @since 1.0
	 *
	 * @return array $stats An array of network stats.
	 */
	function network_stats_endpoint() {
		$stats = get_site_transient( 'network_stats' );
		if ( ! $stats ) {
			$this->refresh_network_stats();
			$stats = get_site_transient( 'network_stats' );
		}
		return $stats;
	}

}
new Multisite_Stats();

/**
 * Adds Multisite Stats Widget.
 *
 * Declares the Multisite Stats widget. Fetches stats from the REST api
 * and outputs in any widget area.
 *
 * @since 1.0
 *
 * @see \WP_Widget
 */
class MultiSiteStats extends \WP_Widget {

	/**
	 * Sets up the widgets name etc
	 */
	public function __construct() {
		$widget_ops = array(
			'classname' => 'multisite_stats',
			'description' => 'Displays domain, number of users, and number of posts for each blog in the Multisite.',
		);
		parent::__construct( 'multisite_stats', 'Multisite Stats', $widget_ops );
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param  array  $args Widget arguments to match the base class.
	 * @param  object $instance Widget instance to match the base class.
	 */
	public function widget( $args, $instance ) {
		$endpointurl = get_site_url( ) . '/wp-json/multisitestats/v1/stats/';
		$response = wp_remote_get( $endpointurl );
		$body = wp_remote_retrieve_body( $response );
		$stats = json_decode( $body );
		if( 'array' === gettype( $stats ) ){
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
					<td><?php echo esc_url( $stat->domain ); ?> </td>
					<td> <?php echo intval( $stat->site_users ); ?></td>
					<td> <?php echo intval( $stat->site_posts ); ?></td>
				</tr>
				
			<?php } ?>
				</table>
			<?php
		}
	}

}

add_action( 'widgets_init', function() {
	register_widget( 'Multisite_Stats\MultiSiteStats' );
});


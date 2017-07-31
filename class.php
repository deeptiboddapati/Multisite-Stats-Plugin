<?php

/**
* 
*/
class Multisite_Stats {
	private function __construct() {
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
	/**
	 * Refreshes network wide statistics.
	 *
	 * Refreshes statistics for the whole network. Sets them as a transient that expires hourly.
	 *
	 * @since 1.0
	 */
	function refresh_network_stats() {
		$sites = get_sites();
		$stats = array();
		foreach ( $sites as $site ) {
			$domain = $site->domain;
			$site_users = $this->get_blog_user_count( $domain );
			$site_posts = $this->get_blog_post_count( $domain );
			$site_stats = array();
			$site_stats['domain'] = $domain;
			$site_stats['site_users'] = $site_users;
			$site_stats['site_posts'] = $site_posts;
			array_push( $stats, $site_stats );
		}
		set_site_transient( 'network_stats', $stats, 1 * HOUR_IN_SECONDS );
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
		$endpointurl = 'http://' . $site_domain . '/wp-json/wp/v2/posts';
		// The phpcs error/reccomendation to use vip_safe_remote_get() doesn't apply to my enviornment.
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

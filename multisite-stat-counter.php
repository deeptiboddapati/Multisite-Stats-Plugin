<?php
/**
 * Plugin Name:       Multisite Plugin Starter
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
// The class that contains the plugin info.
// require_once plugin_dir_path( __FILE__ ) . 'includes/class-info.php';
// /**
//  * The code that runs during plugin activation.
//  */
// function activation() {
// 	require_once plugin_dir_path( __FILE__ ) . 'includes/class-activator.php';
// 	Activator::activate();
// }
// register_activation_hook( __FILE__, __NAMESPACE__ . '\\activation' );

/**
 * Run the plugin.
 */
// function run() {
// 	require_once plugin_dir_path( __FILE__ ) . 'includes/class-plugin.php';
// 	$plugin = new Plugin();
// 	$plugin->run();
// }
// run();


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
function get_network_user_count() {
	$sites = get_sites();
	$total_users = 0;
	foreach ( $sites as $site ) {
		$domain = $site->domain;
		$site_users = get_site_user_count( $domain );
		echo $site_users;
		$total_users += $site_users;
	}
	echo $total_users;

}
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
	// echo $endpointurl;
	$response = wp_remote_get( $endpointurl );
	$user_count = wp_remote_retrieve_header( $response, 'x-wp-total' );
	// print_r( $response );
	return $user_count;
}

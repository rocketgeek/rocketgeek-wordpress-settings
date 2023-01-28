<?php
/*
Plugin Name: RocketGeek WordPress Settings Framework Example Plugin
Plugin URI:  https://github.com/rocketgeek/rocketgeek-wordpress-settings
Description: A demonstration of the RocketGeek WordPress Settings Framework.
Version:     1.0.0
Author:      Chad Butler
Author URI:  https://rocketgeek.com/
Text Domain: rgwps-example
Domain Path: /languages
*/

class RGWPS_Example_Plugin {
	
	// Settings object.
	private $rgwps;
	
	// Name of options.
	private $rgwps_option_group = 'rgwps_example';
	
	// Settings file.
	private $rgwps_settings_file = 'example_settings.php';

	// Textdomain (if translated)
	public $textdomain = 'rgwps-example';
	
	/**
	 * Class constructor.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_name = plugin_basename( $plugin_file );
		$this->plugin_path = plugin_dir_path( $plugin_file );
		$this->plugin_url  = plugin_dir_url ( $plugin_file );
		
		$this->load_dependencies();

		// Include and create a new RocketGeek_WP_Settings.
		$this->rgwps = new RocketGeek_WordPress_Settings( $this->plugin_path . $this->rgwps_settings_file, $this->rgwps_option_group );
		
		// Load dependent objects.
		$this->load_hooks();
		$this->load_settings();

		// Any other custom elements here...
	}
	
	/**
	 * Loads class dependencies.
	 */
	private function load_dependencies() {
		require_once $this->plugin_path . 'class-rocketgeek-wordpress-settings.php';
	}
	
	/**
	 * Loads hooks used in the class.
	 */
	private function load_hooks() {
		// Settings.
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ), 20 );
		add_filter( $this->rgwps_option_group . '_settings_validate', array( &$this, 'validate_settings' ) );
		
		// Functional hooks go here.

	}

	/**
	 * Loads RGWPS settings and additional settings.
	 */
	private function load_settings() {
		
		// Gets the rgwps settings.
		$this->settings = $this->rgwps->get_settings();

		// Get any other settings.
	}

	/**
	 * Loads translation files.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( $this->textdomain, FALSE, 'languages/' );
	}

	/**
	 * Add settings page.
	 */
	public function add_settings_page() {
		$this->rgwps->add_settings_page( array(
			'parent_slug' => 'woocommerce',
			'page_title'  => __( 'RGWPS Demo', $this->textdomain ),
			'menu_title'  => __( 'RGWPS Demo', $this->textdomain ),
			'capability'  => 'manage_options',
		) );
	}
	
	/**
	 * Validate settings.
	 * 
	 * @param $input
	 *
	 * @return mixed
	 */
	public function validate_settings( $input ) {
		// Do your settings validation here
		// Same as $sanitize_callback from http://codex.wordpress.org/Function_Reference/register_setting
		return $input;
	}


	// Custom functions here.
}

global $rgwps_example;
$rgwps_example = new RGWPS_Example_Plugin( __FILE__ );
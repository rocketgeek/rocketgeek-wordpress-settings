<?php
/**
 * RocketGeek WordPress Settings Framework
 *
 * An object class for quickly constructing WordPress settings for plugins and 
 * themes. Based on the WordPress settings framework by Gilbert Pellegram and 
 * James Kemp, framework version 1.6.9 served as a starting point for this 
 * library.  The original class was good for a starting point, but I needed 
 * additional elements - a more flexible hooks structure, and the ability to 
 * package it as a library in multiple plugins that all might be loaded 
 * together (so it needed to avoid naming collisions). The original version did
 * not escape untrusted output, so that was also added.
 *
 * Original WordPress Settings Framework project:
 * @author    Gilbert Pellegrom
 * @author    James Kemp
 * @link      https://github.com/jamesckemp/WordPress-Settings-Framework
 * @copyright Copyright (c) 2012 Dev7studios
 * @license   MIT
 *
 * RocketGeek WordPress Settings
 * @author    Chad Butler
 * @author    RocketGeek
 * @link      https://rocketgeek.com/plugins/wordpress-settings-framework/
 * @copyright Copyright (c) 2022 RocketGeek
 * @license   Apache 2
 *
 * @version 1.0.0
 * @version 1.1.0 Merged with updates from WPSF v1.6.11, built out filter/action hooks.
 */

if ( ! class_exists( 'RocketGeek_WordPress_Settings' ) ) {
	/**
	 * RocketGeek_WordPress_Settings class
	 */
	class RocketGeek_WordPress_Settings {

		/**
		 * Settings.
		 *
		 * @var array
		 */
		private $settings;

		/**
		 * Tabs.
		 *
		 * @var array
		 */
		private $tabs;

		/**
		 * Option group.
		 *
		 * @var string
		 */
		private $option_group;

		/**
		 * Settings page.
		 *
		 * @var array
		 */
		public $settings_page = array();

		/**
		 * Options path.
		 *
		 * @var string
		 */
		private $options_path;

		/**
		 * Options URL.
		 *
		 * @var string
		 */
		private $options_url;

		/**
		 * Handle for scripts/styles.
		 *
		 * @access public
		 * @var string
		 */
		public $handle;

		/**
		 * Textdomain for localization
		 * 
		 * @access public
		 * @var string
		 */
		public $textdomain;

		/**
		 * RocketGeek_WordPress_Settings constructor.
		 *
		 * @param null|string $settings_file Path to a settings file, or null if you pass the option_group manually and construct your settings with a filter.
		 * @param bool|string $option_group  Option group name, usually a short slug.
		 */
		public function __construct( $settings_file = null, $option_group = false ) {
			$this->option_group = $option_group;

			if ( $settings_file ) {
				if ( ! is_file( $settings_file ) ) {
					return;
				}

				require_once $settings_file;

				if ( ! $this->option_group ) {
					$this->option_group = preg_replace( '/[^a-z0-9]+/i', '', basename( $settings_file, '.php' ) );
				}
			}

			if ( empty( $this->option_group ) ) {
				return;
			}

			$this->load_api();

			$this->options_path = plugin_dir_path( __FILE__ );
			$this->options_url  = plugin_dir_url( __FILE__ );

			$this->handle = $this->textdomain = str_replace( '_', '-', $this->option_group );

			$this->construct_settings();

			$this->load_hooks();
		}

		/**
		 * Loads dependent files.
		 *
		 * @since 1.0.0
		 */
		private function load_api() {
			require_once 'rocketgeek-settings-api.php';
		}

		/**
		 * Loads action and filter hooks.
		 *
		 * @since 1.0.0
		 *
		 * @global string $pagenow
		 */
		private function load_hooks() {
			if ( is_admin() ) {
				global $pagenow;

				add_action( 'admin_init', array( $this, 'admin_init' ) );
				add_action( $this->option_group . '_do_settings_sections', array( $this, 'do_tabless_settings_sections' ), 10 );

				if ( filter_input( INPUT_GET, 'page' ) && filter_input( INPUT_GET, 'page' ) === $this->settings_page['slug'] ) {
					if ( 'options-general.php' !== $pagenow ) {
						add_action( 'admin_notices', array( $this, 'admin_notices' ) );
					}
					add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
				}

				if ( $this->has_tabs() ) {
					add_action( $this->option_group . '_before_settings', array( $this, 'tab_links' ) );

					remove_action( $this->option_group . '_do_settings_sections', array( $this, 'do_tabless_settings_sections' ), 10 );
					add_action( $this->option_group . '_do_settings_sections', array( $this, 'do_tabbed_settings_sections' ), 10 );
				}

				add_action( 'wp_ajax_' . $this->option_group . '_export_settings', array( $this, 'export_settings' ) );
				add_action( 'wp_ajax_' . $this->option_group . '_import_settings', array( $this, 'import_settings' ) );
			}
		}
		
		/**
		 * Construct Settings.
		 */
		private function construct_settings() {
			/**
			 * Filter: modify settings for a given option group.
			 *
			 * @filter <option_group>_register_settings
			 * @since 1.1.0
			 * @param array
			 */
			$settings_wrapper = apply_filters( $this->option_group . '_register_settings', array() );

			if ( ! is_array( $settings_wrapper ) ) {
				return new WP_Error( 'broke', esc_html__( 'WPSF settings must be an array', $this->textdomain ) );
			}

			// If "sections" is set, this settings group probably has tabs.
			if ( isset( $settings_wrapper['sections'] ) ) {
				$this->tabs     = ( isset( $settings_wrapper['tabs'] ) ) ? $settings_wrapper['tabs'] : array();
				$this->settings = $settings_wrapper['sections'];
				// If not, it's probably just an array of settings.
			} else {
				$this->settings = $settings_wrapper;
			}

			$this->settings_page['slug'] = sprintf( '%s-settings', str_replace( '_', '-', $this->option_group ) );
		}

		/**
		 * Get the option group for this instance
		 *
		 * @return string the "option_group"
		 */
		public function get_option_group() {
			return $this->option_group;
		}

		/**
		 * Registers the internal WordPress settings
		 */
		public function admin_init() {
			register_setting( $this->option_group, $this->option_group . '_settings', array( $this, 'settings_validate' ) );
			$this->process_settings();
		}

		/**
		 * Add Settings Page
		 *
		 * @param array $args Settings page arguments.
		 */
		public function add_settings_page( $args ) {
			$defaults = array(
				'parent_slug' => false,
				'page_slug'   => '',
				'page_title'  => '',
				'menu_title'  => '',
				'capability'  => 'manage_options',
			);

			$args = wp_parse_args( $args, $defaults );

			$this->settings_page['title']      = $args['page_title'];
			$this->settings_page['capability'] = $args['capability'];

			if ( $args['parent_slug'] ) {
				add_submenu_page(
					$args['parent_slug'],
					$this->settings_page['title'],
					$args['menu_title'],
					$args['capability'],
					$this->settings_page['slug'],
					array( $this, 'settings_page_content' )
				);
			} else {
				add_menu_page(
					$this->settings_page['title'],
					$args['menu_title'],
					$args['capability'],
					$this->settings_page['slug'],
					array( $this, 'settings_page_content' ),
					/**
					 * Filter: modify icon URL for a given option group.
					 *
					 * @filter <option_group>_menu_icon_url
					 * @since 1.1.0
					 * @param string
					 */
					apply_filters( $this->option_group . '_menu_icon_url', '' ),
					/**
					 * Filter: modify menu position for a given option group.
					 *
					 * @filter <option_group>_menu_position
					 * @since 1.1.0
					 * @param int|null
					 */
					apply_filters( $this->option_group . '_menu_position', null )
				);
			}
		}

		/**
		 * Settings Page Content
		 */
		public function settings_page_content() {
			if ( ! current_user_can( $this->settings_page['capability'] ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', $this->textdomain ) );
			}
			?>
			<div class="rgs-settings rgs-settings--<?php echo esc_attr( $this->option_group ); ?>">
				<?php $this->settings_header(); ?>
				<div class="rgs-settings__content">
					<?php $this->settings(); ?>
				</div>
			</div>
			<?php
		}

		/**
		 * Settings Header.
		 */
		public function settings_header() {
			?>
			<div class="rgs-settings__header">
				<h2>
					<?php
					global $allowedposttags;
					$protocols   = wp_allowed_protocols();
					$protocols[] = 'data';

					echo wp_kses(
						/**
						 * Filter: modify title for a given option group.
						 *
						 * @filter <option_group>_settings_page_title
						 * @since 1.1.0
						 * @param string $title Title for the group settings header.
						 */
						apply_filters( $this->option_group . '_settings_page_title', $this->settings_page['title'] ),
						$allowedposttags,
						$protocols
					);
					?>
				</h2>
				<?php
				/**
				 * Action: execute a callback after the option group title.
				 *
				 * @action <option_group>_settings_page_after_title
				 * @since 1.1.0
				 */
				do_action( $this->option_group . '_settings_page_after_title' );
				?>
			</div>
			<?php
		}

		/**
		 * Displays any errors from the WordPress settings API
		 */
		public function admin_notices() {
			settings_errors();
		}

		/**
		 * Enqueue scripts and styles
		 */
		public function admin_enqueue_scripts() {
			// Scripts.
			$jqtimepicker_js_path = 'assets/vendor/jquery-timepicker/jquery.ui.timepicker.js';
			wp_register_script(
				'jquery-ui-timepicker',
				$this->options_url . $jqtimepicker_js_path,
				array( 'jquery', 'jquery-ui-core' ),
				filemtime( $this->options_path . $jqtimepicker_js_path ),
				true
			);

			$js_path = 'assets/js/main' . $this->get_minified() . '.js';
			wp_register_script(
				$this->handle,
				$this->options_url . $js_path,
				array( 'jquery' ),
				filemtime( $this->options_path . $js_path ),
				true
			);

			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'farbtastic' );
			wp_enqueue_script( 'media-upload' );
			wp_enqueue_script( 'thickbox' );
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( 'jquery-ui-timepicker' );
			wp_enqueue_script( $this->handle );

			$data = array(
				'select_file'          => esc_html__( 'Please select a file to import', $this->textdomain ),
				'invalid_file'         => esc_html__( 'Invalid file', $this->textdomain ),
				'something_went_wrong' => esc_html__( 'Something went wrong', $this->textdomain ),
			);
			wp_localize_script( $this->handle, $this->option_group . '_vars', $data );

			// Styles.
			$jqtimepicker_css_path = 'assets/vendor/jquery-timepicker/jquery.ui.timepicker.css';
			wp_register_style(
				'jquery-ui-timepicker',
				$this->options_url . $jqtimepicker_css_path,
				array(),
				filemtime( $this->options_path . $jqtimepicker_css_path )
			);

			$css_path = 'assets/css/main' . $this->get_minified() . '.css';
			wp_register_style(
				$this->handle,
				$this->options_url . $css_path,
				array(),
				filemtime( $this->options_path . $css_path )
			);

			$jqui_css_path = 'assets/vendor/jquery-ui/jquery-ui.css';
			wp_register_style(
				'jquery-ui-css',
				$this->options_url . $jqui_css_path,
				array(),
				filemtime( $this->options_path . $jqui_css_path )
			);

			wp_enqueue_style( 'farbtastic' );
			wp_enqueue_style( 'thickbox' );
			wp_enqueue_style( 'jquery-ui-timepicker' );
			wp_enqueue_style( 'jquery-ui-css' );
			wp_enqueue_style( $this->handle );
		}

		/**
		 * Adds a filter for settings validation.
		 *
		 * @param mixed $input Input data.
		 *
		 * @return array
		 */
		public function settings_validate( $input ) {
			/**
			 * Filter: validate field input for a given option group.
			 *
			 * @filter <option_group>_settings_validate
			 * @since 1.1.0
			 * @param mixed
			 */
			return apply_filters( $this->option_group . '_settings_validate', $input );
		}

		/**
		 * Displays the "section_description" if specified in $this->settings
		 *
		 * @param array $args callback args from add_settings_section().
		 */
		public function section_intro( $args ) {
			if ( ! empty( $this->settings ) ) {
				foreach ( $this->settings as $section ) {
					if ( $section['section_id'] === $args['id'] ) {
						$render_class = '';

						$render_class .= self::add_show_hide_classes( $section );

						if ( $render_class ) {
							echo '<span class="' . esc_attr( $render_class ) . '"></span>';
						}
						if ( isset( $section['section_description'] ) && $section['section_description'] ) {
							echo '<div class="rgs-section-description rgs-section-description--' . esc_attr( $section['section_id'] ) . '">' . wp_kses_post( $section['section_description'] ) . '</div>';
						}
						break;
					}
				}
			}
		}

		/**
		 * Processes $this->settings and adds the sections and fields via the WordPress settings API
		 */
		private function process_settings() {
			if ( ! empty( $this->settings ) ) {
				usort( $this->settings, array( $this, 'sort_array' ) );

				foreach ( $this->settings as $section ) {
					if ( isset( $section['section_id'] ) && $section['section_id'] && isset( $section['section_title'] ) ) {
						$page_name = ( $this->has_tabs() ) ? sprintf( '%s_%s', $this->option_group, $section['tab_id'] ) : $this->option_group;

						add_settings_section( $section['section_id'], $section['section_title'], array( $this, 'section_intro' ), $page_name );

						if ( isset( $section['fields'] ) && is_array( $section['fields'] ) && ! empty( $section['fields'] ) ) {
							foreach ( $section['fields'] as $field ) {
								if ( isset( $field['id'] ) && $field['id'] && isset( $field['title'] ) ) {
									$tooltip = '';

									if ( isset( $field['link'] ) && is_array( $field['link'] ) ) {
										$link_url      = ( isset( $field['link']['url'] ) ) ? esc_html( $field['link']['url'] ) : '';
										$link_text     = ( isset( $field['link']['text'] ) ) ? esc_html( $field['link']['text'] ) : esc_html__( 'Learn More', $this->textdomain );
										$link_external = ( isset( $field['link']['external'] ) ) ? (bool) $field['link']['external'] : true;
										$link_type     = ( isset( $field['link']['type'] ) ) ? esc_attr( $field['link']['type'] ) : 'tooltip';
										$link_target   = ( $link_external ) ? ' target="_blank"' : '';

										if ( 'tooltip' === $link_type ) {
											$link_text = sprintf( '<i class="dashicons dashicons-info rgs-link-icon" title="%s"><span class="screen-reader-text">%s</span></i>', $link_text, $link_text );
										}

										$link = ( $link_url ) ? sprintf( '<a class="rgs-link__link" href="%s"%s>%s</a>', $link_url, $link_target, $link_text ) : '';

										if ( $link && 'tooltip' === $link_type ) {
											$tooltip = $link;
										} elseif ( $link ) {
											$field['subtitle'] .= ( empty( $field['subtitle'] ) ) ? $link : sprintf( '<br/><br/>%s', $link );
										}
									}

									$title = sprintf( '<span class="rgs-label">%s %s</span>', $field['title'], $tooltip );

									if ( ! empty( $field['subtitle'] ) ) {
										$title .= sprintf( '<span class="rgs-subtitle">%s</span>', $field['subtitle'] );
									}

									add_settings_field(
										$field['id'],
										$title,
										array( $this, 'generate_setting' ),
										$page_name,
										$section['section_id'],
										array(
											'section' => $section,
											'field'   => $field,
										)
									);
								}
							}
						}
					}
				}
			}
		}

		/**
		 * Usort callback. Sorts $this->settings by "section_order"
		 *
		 * @param array $a Sortable Array.
		 * @param array $b Sortable Array.
		 *
		 * @return array
		 */
		public function sort_array( $a, $b ) {
			if ( ! isset( $a['section_order'] ) ) {
				return 0;
			}

			return ( $a['section_order'] > $b['section_order'] ) ? 1 : 0;
		}

		/**
		 * Convert array to object, recursively.
		 * 
		 * @param  array  $array
		 * @return object
		 */
		function array_to_object( $array ) {
			$obj = new stdClass();
		 
			foreach ( $array as $k => $v ) {
			   if ( strlen( $k ) ) {
				  if ( is_array( $v ) ) {
					 $obj->{$k} = $this->array_to_object( $v ); //RECURSION
				  } else {
					 $obj->{$k} = $v;
				  }
			   }
			}
			
			return $obj;
		 }
		/**
		 * Generates the HTML output of the settings fields
		 *
		 * @param array $args callback args from add_settings_field().
		 */
		public function generate_setting( $args ) {
			$section = $args['section'];
			/**
			 * Filter: filter the default setting values for a given option group.
			 *
			 * @filter <option_group>_settings_defaults
			 * @since 1.1.0
			 * @param mixed $setting_defaults Default values for settings.
			 */
			$this->setting_defaults = apply_filters( $this->option_group . '_settings_defaults', array() );

			$args = wp_parse_args( $args['field'], $this->setting_defaults );

			$options = get_option( $this->option_group . '_settings' );

			$args['id']    = ( $this->has_tabs() ) ? sprintf( '%s_%s_%s', $section['tab_id'], $section['section_id'], $args['id'] ) : sprintf( '%s_%s', $section['section_id'], $args['id'] );
			
			$field_name    = isset( $args['name'] ) ? $args['name'] : $args['id'];
			$args['name']  = $this->generate_field_name( $field_name );
			$args['value'] = ( isset( $options[ $field_name ] ) ) ? $options[ $field_name ] : ( isset( $args['default'] ) ? $args['default'] : '' );

			// $args['class'] .= self::add_show_hide_classes( $args );
			$args['class'] = ( isset( $args['class'] ) ) ? $args['class'] . self::add_show_hide_classes( $args ) : self::add_show_hide_classes( $args );

			/**
			 * Action: execute callback before a given group.
			 *
			 * @action <option_group>_before_field
			 * @since 1.1.0
			 */
			do_action( $this->option_group . '_before_field' );

			/**
			 * Action: execute callback before a specific field in a given group.
			 *
			 * @action <option_group>_before_field_<field_id>
			 * @since 1.1.0
			 */
			do_action( $this->option_group . '_before_field_' . $args['id'] );

			$this->do_field_method( $args );

			/**
			 * Action: execute callback after a given group.
			 *
			 * @action <option_group>_after_field
			 * @since 1.1.0
			 */
			do_action( $this->option_group . '_after_field' );

			/**
			 * Action: execute callback after a specific field in a given group.
			 *
			 * @action <option_group>_after_field_<field_id>
			 * @since 1.1.0
			 */
			do_action( $this->option_group . '_after_field_' . $args['id'] );
		}

		/**
		 * Do field method, if it exists
		 *
		 * @param array $args Field arguments.
		 */
		public function do_field_method( $args ) {
			$generate_field_method = sprintf( 'generate_%s_field', $args['type'] );

			if ( method_exists( $this, $generate_field_method ) ) {
				$this->$generate_field_method( $args );
			}
		}

		/**
		 * Generate: Text field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_text_field( $args ) {
			$args['value'] = esc_attr( stripslashes( $args['value'] ) );
			
			//echo '<input type="text" name="' . esc_attr( $args['name'] ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $args['value'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '" class="regular-text ' . esc_attr( $args['class'] ) . '" />';
			$placeholder = ( isset( $args['placeholder'] ) ) ? ' placeholder="' . esc_attr( $args['placeholder'] ) . '"' : '';
			echo '<input type="text" name="' . esc_attr( $args['name'] ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $args['value'] ) . '"' . $placeholder . '" class="regular-text ' . esc_attr( $args['class'] ) . '" />';

			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate: Hidden field.
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_hidden_field( $args ) {
			$args['value'] = esc_attr( stripslashes( $args['value'] ) );

			echo '<input type="hidden" name="' . esc_attr( $args['name'] ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $args['value'] ) . '"  class="hidden-field ' . esc_attr( $args['class'] ) . '" />';
		}

		/**
		 * Generate: Number field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_number_field( $args ) {
			$args['value'] = esc_attr( stripslashes( $args['value'] ) );

			echo '<input type="number" name="' . esc_attr( $args['name'] ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $args['value'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '" class="regular-text ' . esc_attr( $args['class'] ) . '" />';

			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate: Time field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_time_field( $args ) {
			$args['value'] = esc_attr( stripslashes( $args['value'] ) );

			$timepicker = ( ! empty( $args['timepicker'] ) ) ? htmlentities( wp_json_encode( $args['timepicker'] ) ) : null;

			echo '<input type="text" name="' . esc_attr( $args['name'] ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $args['value'] ) . '" class="timepicker regular-text ' . esc_attr( $args['class'] ) . '" data-timepicker="' . esc_attr( $timepicker ) . '" />';

			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate: Date field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_date_field( $args ) {
			$args['value'] = esc_attr( stripslashes( $args['value'] ) );

			$datepicker = ( ! empty( $args['datepicker'] ) ) ? htmlentities( wp_json_encode( $args['datepicker'] ) ) : null;

			echo '<input type="text" name="' . esc_attr( $args['name'] ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $args['value'] ) . '" class="datepicker regular-text ' . esc_attr( $args['class'] ) . '" data-datepicker="' . esc_attr( $datepicker ) . '" />';

			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate Export Field.
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_export_field( $args ) {
			$args['value'] = esc_attr( stripslashes( $args['value'] ) );
			$args['value'] = empty( $args['value'] ) ? esc_html__( 'Export Settings', $this->textdomain ) : $args['value'];
			$option_group  = $this->option_group;
			$export_url    = site_url() . '/wp-admin/admin-ajax.php?action=' . $this->option_group . '_export_settings&_wpnonce=' . wp_create_nonce( $this->option_group . '_export_settings' ) . '&option_group=' . $option_group;

			echo '<a target=_blank href="' . esc_url( $export_url ) . '" class="button" name="' . esc_attr( $args['name'] ) . '" id="' . esc_attr( $args['id'] ) . '">' . esc_html( $args['value'] ) . '</a>';

			$options = get_option( $option_group . '_settings' );
			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate Import Field.
		 *
		 * @param array $args Field rguments.
		 */
		public function generate_import_field( $args ) {
			$args['value'] = esc_attr( stripslashes( $args['value'] ) );
			$args['value'] = empty( $args['value'] ) ? esc_html__( 'Import Settings', $this->textdomain ) : $args['value'];
			$option_group  = $this->option_group;

			echo sprintf(
				'
				<div class="rgs-import">
					<div class="rgs-import__false_btn">
						<input type="file" name="rgs-import-field" class="rgs-import__file_field" id="%s" accept=".json"/>
						<button type="button" name="' . $this->option_group . '_import_button" class="button rgs-import__button" id="%s">%s</button>
						<input type="hidden" class="' . $this->option_group . '_import_nonce" value="%s"></input>
						<input type="hidden" class="' . $this->option_group . '_import_option_group" value="%s"></input>
					</div>
					<span class="spinner"></span>
				</div>',
				esc_attr( $args['id'] ),
				esc_attr( $args['id'] ),
				esc_attr( $args['value'] ),
				esc_attr( wp_create_nonce( $this->option_group . '_import_settings' ) ),
				esc_attr( $this->option_group )
			);

			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate: Group field
		 *
		 * Generates a table of subfields, and a javascript template for create new repeatable rows
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_group_field( $args ) {
			$value     = (array) $args['value'];
			$row_count = ( ! empty( $value ) ) ? count( $value ) : 1;

			echo '<table class="widefat rgs-group" cellspacing="0">';

			echo '<tbody>';

			for ( $row = 0; $row < $row_count; $row ++ ) {
				// @codingStandardsIgnoreStart
				echo $this->generate_group_row_template( $args, false, $row );
				// @codingStandardsIgnoreEnd
			}

			echo '</tbody>';

			echo '</table>';

			printf(
				'<script type="text/html" id="%s_template">%s</script>',
				esc_attr( $args['id'] ),
				// @codingStandardsIgnoreStart
				$this->generate_group_row_template( $args, true )
				// @codingStandardsIgnoreEnd
			);

			$this->generate_description( $args['desc'] );
		}


		/**
		 * Generate Image Checkboxes.
		 *
		 * @param array $args Field arguments.
		 *
		 * @return void
		 */
		public function generate_image_checkboxes_field( $args ) {

			echo '<input type="hidden" name="' . esc_attr( $args['name'] ) . '" value="0" />';

			echo '<ul class="rgs-visual-field rgs-visual-field--image-checkboxes rgs-visual-field--grid rgs-visual-field--cols">';

			foreach ( $args['choices'] as $value => $choice ) {
				$field_id      = sprintf( '%s_%s', $args['id'], $value );
				$is_checked    = is_array( $args['value'] ) && in_array( $value, $args['value'], true );
				$checked_class = $is_checked ? 'rgs-visual-field__item--checked' : '';

				echo sprintf(
					'<li class="rgs-visual-field__item %s">
						<label>
							<div class="rgs-visual-field-image-radio__img_wrap">
								<img src="%s">
							</div>
							<div class="rgs-visual-field__item-footer">
								<input type="checkbox" name="%s[]" id="%s" value="%s" class="%s" %s>
								<span class="rgs-visual-field__item-text">%s</span>
							</div>
						</label>
					</li>',
					esc_attr( $checked_class ),
					esc_url( $choice['image'] ),
					esc_attr( $args['name'] ),
					esc_attr( $field_id ),
					esc_attr( $value ),
					esc_attr( $args['class'] ),
					checked( true, $is_checked, false ),
					esc_attr( $choice['text'] )
				);
			}

			echo '</ul>';

			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate: Image Radio field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_image_radio_field( $args ) {
			$args['value'] = esc_html( esc_attr( $args['value'] ) );
			$count         = count( $args['choices'] );

			echo sprintf( '<ul class="rgs-visual-field rgs-visual-field--image-radio rgs-visual-field--grid rgs-visual-field--cols rgs-visual-field--col-%s ">', esc_attr( $count ) );

			foreach ( $args['choices'] as $value => $choice ) {
				$field_id = sprintf( '%s_%s', $args['id'], $value );
				$checked  = $value === $args['value'] ? 'checked="checked"' : '';

				echo sprintf(
					'<li class="rgs-visual-field__item %s">				
						<label>
							<div class="rgs-visual-field-image-radio__img_wrap">
								<img src="%s">
							</div>
							<div class="rgs-visual-field__item-footer">
								<input type="radio" name="%s" id="%s" value="%s" class="%s" %s>
								<span class="rgs-visual-field__item-text">%s</span>
							</div>
						</label>
					</li>',
					( $checked ? 'rgs-visual-field__item--checked' : '' ),
					esc_attr( $choice['image'] ),
					esc_attr( $args['name'] ),
					esc_attr( $field_id ),
					esc_attr( $value ),
					esc_attr( $args['class'] ),
					esc_html( $checked ),
					esc_attr( $choice['text'] )
				);
			}
			echo '</ul>';

			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate group row template
		 *
		 * @param array $args  Field arguments.
		 * @param bool  $blank Blank values.
		 * @param int   $row   Iterator.
		 *
		 * @return string|bool
		 */
		public function generate_group_row_template( $args, $blank = false, $row = 0 ) {
			$row_template = false;
			$row_id       = ( ! empty( $args['value'][ $row ]['row_id'] ) ) ? $args['value'][ $row ]['row_id'] : $row;
			$row_id_value = ( $blank ) ? '' : $row_id;

			if ( $args['subfields'] ) {
				$row_class = ( 0 === $row % 2 ) ? 'alternate' : '';

				$row_template .= sprintf( '<tr class="rgs-group__row %s">', $row_class );

				$row_template .= sprintf( '<td class="rgs-group__row-index"><span>%d</span></td>', $row );

				$row_template .= '<td class="rgs-group__row-fields">';

				$row_template .= '<input type="hidden" class="rgs-group__row-id" name="' . sprintf( '%s[%d][row_id]', esc_attr( $args['name'] ), esc_attr( $row ) ) . '" value="' . esc_attr( $row_id_value ) . '" />';

				foreach ( $args['subfields'] as $subfield ) {
					$subfield = wp_parse_args( $subfield, $this->setting_defaults );

					$subfield['value'] = ( $blank ) ? '' : ( isset( $args['value'][ $row ][ $subfield['id'] ] ) ? $args['value'][ $row ][ $subfield['id'] ] : '' );
					$subfield['name']  = sprintf( '%s[%d][%s]', $args['name'], $row, $subfield['id'] );
					$subfield['id']    = sprintf( '%s_%d_%s', $args['id'], $row, $subfield['id'] );

					$class = sprintf( 'rgs-group__field-wrapper--%s', $subfield['type'] );

					$row_template .= sprintf( '<div class="rgs-group__field-wrapper %s">', $class );
					$row_template .= sprintf( '<label for="%s" class="rgs-group__field-label">%s</label>', $subfield['id'], $subfield['title'] );

					ob_start();
					$this->do_field_method( $subfield );
					$row_template .= ob_get_clean();

					$row_template .= '</div>';
				}

				$row_template .= '</td>';

				$row_template .= '<td class="rgs-group__row-actions">';

				$row_template .= sprintf( '<a href="javascript: void(0);" class="rgs-group__row-add" data-template="%s_template"><span class="dashicons dashicons-plus-alt"></span></a>', $args['id'] );
				$row_template .= '<a href="javascript: void(0);" class="rgs-group__row-remove"><span class="dashicons dashicons-trash"></span></a>';

				$row_template .= '</td>';

				$row_template .= '</tr>';
			}

			return $row_template;
		}

		/**
		 * Generate: Select field
		 *
		 * @param array $args Field rguments.
		 */
		public function generate_select_field( $args ) {
			$args['value'] = esc_html( esc_attr( $args['value'] ) );

			echo '<select name="' . esc_attr( $args['name'] ) . '" id="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( $args['class'] ) . '">';

			foreach ( $args['choices'] as $value => $text ) {
				if ( is_array( $text ) ) {
					echo sprintf( '<optgroup label="%s">', esc_html( $value ) );
					foreach ( $text as $group_value => $group_text ) {
						$selected = ( $group_value === $args['value'] ) ? 'selected="selected"' : '';
						echo sprintf( '<option value="%s" %s>%s</option>', esc_attr( $group_value ), esc_html( $selected ), esc_html( $group_text ) );
					}
					echo '</optgroup>';
					continue;
				}

				$selected = ( strval( $value ) === $args['value'] ) ? 'selected="selected"' : '';

				echo sprintf( '<option value="%s" %s>%s</option>', esc_attr( $value ), esc_html( $selected ), esc_html( $text ) );
			}

			echo '</select>';

			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate: Password field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_password_field( $args ) {
			$args['value'] = esc_attr( stripslashes( $args['value'] ) );

			echo '<input type="password" name="' . esc_attr( $args['name'] ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $args['value'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '" class="regular-text ' . esc_attr( $args['class'] ) . '" />';

			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate: Textarea field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_textarea_field( $args ) {
			$args['value'] = esc_html( esc_attr( $args['value'] ) );

			echo '<textarea name="' . esc_attr( $args['name'] ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '" rows="5" cols="60" class="' . esc_attr( $args['class'] ) . '">' . esc_html( $args['value'] ) . '</textarea>';

			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate: Radio field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_radio_field( $args ) {
			$args['value'] = esc_html( esc_attr( $args['value'] ) );

			foreach ( $args['choices'] as $value => $text ) {
				$field_id = sprintf( '%s_%s', $args['id'], $value );
				$checked  = ( $value === $args['value'] ) ? 'checked="checked"' : '';

				echo sprintf( '<label><input type="radio" name="%s" id="%s" value="%s" class="%s" %s> %s</label><br />', esc_attr( $args['name'] ), esc_attr( $field_id ), esc_html( $value ), esc_attr( $args['class'] ), esc_html( $checked ), esc_html( $text ) );
			}

			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate: Checkbox field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_checkbox_field( $args ) {
			$args['value'] = esc_attr( stripslashes( $args['value'] ) );
			$checked       = ( $args['value'] ) ? 'checked="checked"' : '';

			echo '<input type="hidden" name="' . esc_attr( $args['name'] ) . '" value="0" />';
			echo '<label><input type="checkbox" name="' . esc_attr( $args['name'] ) . '" id="' . esc_attr( $args['id'] ) . '" value="1" class="' . esc_attr( $args['class'] ) . '" ' . esc_html( $checked ) . '> ' . esc_attr( $args['desc'] ) . '</label>';
		}

		/**
		 * Generate: Toggle field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_toggle_field( $args ) {
			$args['value'] = esc_attr( stripslashes( $args['value'] ) );
			$checked       = ( $args['value'] ) ? 'checked="checked"' : '';

			echo '<input type="hidden" name="' . esc_attr( $args['name'] ) . '" value="0" />';
			echo '<label class="switch"><input type="checkbox" name="' . esc_attr( $args['name'] ) . '" id="' . esc_attr( $args['id'] ) . '" value="1" class="' . esc_attr( $args['class'] ) . '" ' . esc_html( $checked ) . '> ' . esc_html( $args['desc'] ) . '<span class="slider"></span></label>';
		}

		/**
		 * Generate: Checkboxes field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_checkboxes_field( $args ) {
			echo '<input type="hidden" name="' . esc_attr( $args['name'] ) . '" value="0" />';

			echo '<ul class="rgs-list rgs-list--checkboxes">';

			foreach ( $args['choices'] as $value => $text ) {
				$checked  = ( is_array( $args['value'] ) && in_array( strval( $value ), array_map( 'strval', $args['value'] ), true ) ) ? 'checked="checked"' : '';
				$field_id = sprintf( '%s_%s', $args['id'], $value );

				echo sprintf( '<li><label><input type="checkbox" name="%s[]" id="%s" value="%s" class="%s" %s> %s</label></li>', esc_attr( $args['name'] ), esc_attr( $field_id ), esc_html( $value ), esc_attr( $args['class'] ), esc_html( $checked ), esc_html( $text ) );
			}

			echo '</ul>';

			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate: Color field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_color_field( $args ) {
			$color_picker_id = sprintf( '%s_cp', $args['id'] );
			$args['value']   = esc_attr( stripslashes( $args['value'] ) );

			echo '<div style="position:relative;">';

			echo sprintf( '<input type="text" name="%s" id="%s" value="%s" class="%s">', esc_attr( $args['name'] ), esc_attr( $args['id'] ), esc_attr( $args['value'] ), esc_attr( $args['class'] ) );

			echo sprintf( '<div id="%s" style="position:absolute;top:0;left:190px;background:#fff;z-index:9999;"></div>', esc_attr( $color_picker_id ) );

			$this->generate_description( $args['desc'] );

			echo '<script type="text/javascript">
                jQuery(document).ready(function($){
                    var colorPicker = $("#' . esc_attr( $color_picker_id ) . '");
                    colorPicker.farbtastic("#' . esc_attr( $args['id'] ) . '");
                    colorPicker.hide();
                    $("#' . esc_attr( $args['id'] ) . '").on("focus", function(){
                        colorPicker.show();
                    });
                    $("#' . esc_attr( $args['id'] ) . '").on("blur", function(){
                        colorPicker.hide();
                        if($(this).val() == "") $(this).val("#");
                    });
                });
                </script>';

			echo '</div>';
		}

		/**
		 * Generate: File field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_file_field( $args ) {
			$args['value'] = esc_attr( $args['value'] );
			$button_id     = sprintf( '%s_button', $args['id'] );

			echo sprintf( '<input type="text" name="%s" id="%s" value="%s" class="regular-text %s"> ', esc_attr( $args['name'] ), esc_attr( $args['id'] ), esc_html( $args['value'] ), esc_attr( $args['class'] ) );

			echo sprintf( '<input type="button" class="button rgs-browse" id="%s" value="%s" />', esc_attr( $button_id ), esc_html__( 'Browse', $this->textdomain ) );
			?>
			<script type='text/javascript'>
				jQuery( document ).ready( function( $ ) {

					// Uploading files
					var file_frame;
					var wp_media_post_id = wp.media.model.settings.post.id; // Store the old id.
					var set_to_post_id = 0;

					jQuery( document.body ).on('click', '#<?php echo esc_attr( $button_id ); ?>', function( event ){

						event.preventDefault();

						// If the media frame already exists, reopen it.
						if ( file_frame ) {
							// Set the post ID to what we want
							file_frame.uploader.uploader.param( 'post_id', set_to_post_id );
							// Open frame
							file_frame.open();
							return;
						} else {
							// Set the wp.media post id so the uploader grabs the ID we want when initialised.
							wp.media.model.settings.post.id = set_to_post_id;
						}

						// Create the media frame.
						file_frame = wp.media.frames.file_frame = wp.media({
							title: '<?php echo esc_html__( 'Select a image to upload', $this->textdomain ); ?>',
							button: {
								text: '<?php echo esc_html__( 'Use this image', $this->textdomain ); ?>',
							},
							multiple: false	// Set to true to allow multiple files to be selected
						});

						// When an image is selected, run a callback.
						file_frame.on( 'select', function() {
							// We set multiple to false so only get one image from the uploader
							attachment = file_frame.state().get('selection').first().toJSON();

							// Do something with attachment.id and/or attachment.url here
							$( '#image-preview' ).attr( 'src', attachment.url ).css( 'width', 'auto' );
							$( '#image_attachment_id' ).val( attachment.id );
							$( '#<?php echo esc_attr( $args['id'] ); ?>' ).val( attachment.url );

							// Restore the main post ID
							wp.media.model.settings.post.id = wp_media_post_id;
						});

						// Finally, open the modal
						file_frame.open();
					});

					// Restore the main ID when the add media button is pressed
					jQuery( 'a.add_media' ).on( 'click', function() {
						wp.media.model.settings.post.id = wp_media_post_id;
					});
				});
				</script>
			<?php
		}

		/**
		 * Generate: Editor field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_editor_field( $args ) {
			$settings                  = ( isset( $args['editor_settings'] ) && is_array( $args['editor_settings'] ) ) ? $args['editor_settings'] : array();
			$settings['textarea_name'] = $args['name'];

			wp_editor( $args['value'], $args['id'], $settings );

			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate: Code editor field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_code_editor_field( $args ) {
			printf(
				'<textarea
					name="%s"
					id="%s"
					placeholder="%s"
					rows="5"
					cols="60"
					class="%s"
				>%s</textarea>',
				esc_attr( $args['name'] ),
				esc_attr( $args['id'] ),
				esc_attr( $args['placeholder'] ),
				esc_attr( $args['class'] ),
				esc_html( $args['value'] )
			);

			$settings = wp_enqueue_code_editor( array( 'type' => esc_attr( $args['mimetype'] ) ) );

			wp_add_inline_script(
				'code-editor',
				sprintf(
					'jQuery( function() { wp.codeEditor.initialize( "%s", %s ); } );',
					esc_attr( $args['id'] ),
					wp_json_encode( $settings )
				)
			);

			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate: Custom field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_custom_field( $args ) {
			if ( isset( $args['output'] ) && is_callable( $args['output'] ) ) {
				call_user_func( $args['output'], $args );
				return;
			}

			// @codingStandardsIgnoreStart
			echo ( isset( $args['output'] ) ) ? $args['output'] : $args['default']; // This output isn't easily escaped.
			// @codingStandardsIgnoreEnd
		}

		/**
		 * Generate: Multi Inputs field
		 *
		 * @param array $args Field arguments.
		 */
		public function generate_multiinputs_field( $args ) {
			$field_titles = array_keys( $args['default'] );
			$values       = array_values( $args['value'] );

			echo '<div class="rgs-multifields">';

			$i = 0;
			$c = count( $values );
			while ( $i < $c ) :

				$field_id = sprintf( '%s_%s', $args['id'], $i );
				$value    = esc_attr( stripslashes( $values[ $i ] ) );

				echo '<div class="rgs-multifields__field">';
				echo '<input type="text" name="' . esc_attr( $args['name'] ) . '[]" id="' . esc_attr( $field_id ) . '" value="' . esc_attr( $value ) . '" class="regular-text ' . esc_attr( $args['class'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '" />';
				echo '<br><span>' . esc_html( $field_titles[ $i ] ) . '</span>';
				echo '</div>';

				$i ++;
			endwhile;

			echo '</div>';

			$this->generate_description( $args['desc'] );
		}

		/**
		 * Generate: Field ID
		 *
		 * @param mixed $id Field ID.
		 *
		 * @return string
		 */
		public function generate_field_name( $id ) {
			return sprintf( '%s_settings[%s]', $this->option_group, $id );
		}

		/**
		 * Generate: Description
		 *
		 * @param mixed $description Field description.
		 */
		public function generate_description( $description, $args = array() ) {
			if ( $description && '' !== $description ) {
				$string = '<p class="description">' . wp_kses_post( $description ) . '</p>';
				/**
				 * Filter the description HTML.
				 * 
				 * @since 1.1.0
				 * 
				 * @param  string  $string
				 * @param  array   $args
				 */
				$string = apply_filters( $this->option_group . '_field_description', $string, $args );
				echo $string;
			}
		}

		/**
		 * Output the settings form
		 */
		public function settings() {
			/**
			 * Action: execute callback before the settings form for a given group.
			 *
			 * @action <option_group>_before_settings
			 * @since 1.1.0
			 */
			do_action( $this->option_group . '_before_settings' );
			?>
			<form action="options.php" method="post" novalidate enctype="multipart/form-data">
				<?php
				/**
				 * Action: execute callback before the settings fields for a given group.
				 *
				 * @action <option_group>_before_settings_fields
				 * @since 1.1.0
				 */
				do_action( $this->option_group . '_before_settings_fields' );
				
				settings_fields( $this->option_group );

				/**
				 * Hook: execute callback to output the settings sections for a given group.
				 *
				 * @hook <option_group>_do_settings_sections
				 * @since 1.1.0
				 */
				do_action( $this->option_group . '_do_settings_sections' );
				
				/**
				 * Filter: control whether the save changes button should be visible or not for a given option group.
				 * 
				 * @todo Reconsider this filter. Should probably just be a filter for the button HTML rather than on/off.
				 *
				 * @filter <option_group>_show_save_changes_button
				 * @since 1.1.0
				 * @param boolean
				 */
				if ( apply_filters( $this->option_group . '_show_save_changes_button', true ) ) {
					submit_button( __( 'Save Changes' ) );
				} ?>
			</form>
			<?php
			/**
			 * Action: execute callback after the settings form for a given group.
			 *
			 * @action <option_group>_after_settings
			 * @since 1.1.0
			 */
			do_action( $this->option_group . '_after_settings' );
		}

		/**
		 * Helper: Get Settings
		 *
		 * @return array
		 */
		public function get_settings() {
			$settings_name = $this->option_group . '_settings';

			static $settings = array();

			if ( isset( $settings[ $settings_name ] ) ) {
				return $settings[ $settings_name ];
			}

			$saved_settings             = get_option( $settings_name );
			$settings[ $settings_name ] = array();

			foreach ( $this->settings as $section ) {
				if ( empty( $section['fields'] ) ) {
					continue;
				}

				foreach ( $section['fields'] as $field ) {
					if ( ! empty( $field['default'] ) && is_array( $field['default'] ) ) {
						$field['default'] = array_values( $field['default'] );
					}

					$setting_key = ( $this->has_tabs() ) ? sprintf( '%s_%s_%s', $section['tab_id'], $section['section_id'], $field['id'] ) : sprintf( '%s_%s', $section['section_id'], $field['id'] );

					if ( $this->has_tabs() ) {
						if ( isset( $saved_settings[ $setting_key ] ) ) {
							$settings[ $settings_name ][ $section['tab_id'] ][ $section['section_id'] ][ $field['id'] ] = $saved_settings[ $setting_key ];
						} else {
							$settings[ $settings_name ][ $section['tab_id'] ][ $section['section_id'] ][ $field['id'] ] = ( isset( $field['default'] ) ) ? $field['default'] : false;
						}
					} else {
						if ( isset( $saved_settings[ $setting_key ] ) ) {
							$settings[ $settings_name ][ $section['section_id'] ][ $field['id'] ] = $saved_settings[ $setting_key ];
						} else {
							$settings[ $settings_name ][ $section['section_id'] ][ $field['id'] ] = ( isset( $field['default'] ) ) ? $field['default'] : false;
						}
					}
				}
			}

			// Not needed unless we're actually doing settings.
			if ( ! is_admin() ) {
				unset( $this->settings );
				unset( $this->tabs );
			}
	
			return $this->array_to_object( $settings[ $settings_name ] );
		}

		/**
		 * Tabless Settings sections
		 */
		public function do_tabless_settings_sections() {
			?>
			<div class="rgs-section rgs-tabless">
				<?php
				/**
				 * Action: add inside the postbox before the settings.
				 * 
				 * @action <option_group>_before_settings_section
				 * @since 1.1.0
				 */
				do_action( $this->option_group . '_before_settings_section' );
				
				$this->do_settings_sections( $this->option_group );
				
				/**
				 * Action: add inside the postbox after the settings.
				 * 
				 * @action <option_group>_after_settings_section
				 * @since 1.1.0
				 */
				do_action( $this->option_group . '_after_settings_section' );
				?>
			</div>
			<?php
		}

		/**
		 * Tabbed Settings sections
		 */
		public function do_tabbed_settings_sections() {
			$i = 0;
			foreach ( $this->tabs as $tab_data ) {
				?>
				<div id="tab-<?php echo esc_attr( $tab_data['id'] ); ?>" class="rgs-section rgs-tab rgs-tab--<?php echo esc_attr( $tab_data['id'] ); ?> <?php
				if ( 0 === $i ) {
					echo 'rgs-tab--active';
				}
				?>
				">
					<div class="postbox">
						<?php 
						/**
						 * Action: add inside the postbox before the settings.
						 * 
						 * @action <option_group>_<tab_id>_before_settings_section
						 * @since 1.1.0
						 */
						do_action( $this->option_group . '_' . $tab_data['id'] . '_before_settings_section' );

						$this->do_settings_sections( sprintf( '%s_%s', $this->option_group, $tab_data['id'] ), $tab_data['id'] );
						
						/**
						 * Action: add inside the postbox after the settings.
						 * 
						 * @action <option_group>_<tab_id>_after_settings_section
						 * @since 1.1.0
						 */
						do_action( $this->option_group . '_' . $tab_data['id'] . '_after_settings_section', $tab_data['id']  );
						?>
					</div>
				</div>
				<?php
				$i ++;
			}
		}

		/**
		 * Output the tab links
		 */
		public function tab_links() {
			/**
			 * Filter: control whether the tab links should be visible or not for a given option group.
			 *
			 * @filter <option_group>_show_tab_links
			 * @since 1.1.0
			 * @param boolean
			 */
			if ( ! apply_filters( $this->option_group . '_show_tab_links', true ) ) {
				return;
			}

			/**
			 * Action: execute callback before the tab links for a given option group.
			 *
			 * @action <option_group>_before_tab_links
			 * @since 1.1.0
			 */
			do_action( $this->option_group . '_before_tab_links' );
			?>
			<ul class="rgs-nav">
				<?php
				$i = 0;
				foreach ( $this->tabs as $tab_data ) {
					if ( ! $this->tab_has_settings( $tab_data['id'] ) ) {
						continue;
					}

					if ( ! isset( $tab_data['class'] ) ) {
						$tab_data['class'] = '';
					}

					$tab_data['class'] .= self::add_show_hide_classes( $tab_data );

					$active = ( 0 === $i ) ? 'rgs-nav__item--active' : '';
					?>
					<li class="rgs-nav__item <?php echo esc_attr( $active ); ?>">
						<a class="rgs-nav__item-link <?php echo esc_attr( $tab_data['class'] ); ?>" href="#tab-<?php echo esc_attr( $tab_data['id'] ); ?>"><?php echo wp_kses_post( $tab_data['title'] ); ?></a>
					</li>
					<?php
					$i ++;
				}
				?>
				<li class="rgs-nav__item rgs-nav__item--last">
					<?php submit_button( __( 'Save Changes' ), 'primary large', 'submit', false ); ?>
				</li>
			</ul>

			<?php // Add this here so notices are moved. ?>
			<div class="wrap rgs-notices"><h2>&nbsp;</h2></div>
			<?php
			/**
			 * Action: execute callback after the tab links for a given option group.
			 *
			 * @action <option_group>_after_tab_links
			 * @since 1.1.0
			 */
			do_action( $this->option_group . '_after_tab_links' );
		}

		/**
		 * Does this tab have settings?
		 *
		 * @param string $tab_id Tab ID.
		 *
		 * @return bool
		 */
		public function tab_has_settings( $tab_id ) {
			if ( empty( $this->settings ) ) {
				return false;
			}

			foreach ( $this->settings as $settings_section ) {
				if ( $tab_id !== $settings_section['tab_id'] ) {
					continue;
				}

				return true;
			}

			return false;
		}

		/**
		 * Check if this settings instance has tabs
		 */
		public function has_tabs() {
			if ( ! empty( $this->tabs ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Add Show Hide Classes.
		 *
		 * @param array  $args Field arguments.
		 * @param string $type Type.
		 */
		public static function add_show_hide_classes( $args, $type = 'show_if' ) {
			$class = '';
			$slug  = ' ' . str_replace( '_', '-', $type );
			if ( isset( $args[ $type ] ) && is_array( $args[ $type ] ) ) {
				$class .= $slug;
				foreach ( $args[ $type ] as $condition ) {
					if ( isset( $condition['field'] ) && $condition['value'] ) {
						$value_string = '';
						foreach ( $condition['value'] as $value ) {
							if ( ! empty( $value_string ) ) {
								$value_string .= '||';
							}
							$value_string .= $value;
						}

						if ( ! empty( $value_string ) ) {
							$class .= $slug . '--' . $condition['field'] . '===' . $value_string;
						}
					} else {
						$and_string = '';
						foreach ( $condition as $and_condition ) {
							if ( ! isset( $and_condition['field'] ) || ! isset( $and_condition['value'] ) ) {
								continue;
							}

							if ( ! empty( $and_string ) ) {
								$and_string .= '&&';
							}

							$value_string = '';
							foreach ( $and_condition['value'] as $value ) {
								if ( ! empty( $value_string ) ) {
									$value_string .= '||';
								}
								$value_string .= $value;
							}

							if ( ! empty( $value_string ) ) {
								$and_string .= $and_condition['field'] . '===' . $value_string;
							}
						}

						if ( ! empty( $and_string ) ) {
							$class .= $slug . '--' . $and_string;
						}
					}
				}
			}

			// Run the function again with hide if.
			if ( 'hide_if' !== $type ) {
				$class .= self::add_show_hide_classes( $args, 'hide_if' );
			}

			return $class;
		}

		/**
		 * Handle export settings action.
		 */
		public function export_settings() {
			$_wpnonce     = filter_input( INPUT_GET, '_wpnonce' );
			$option_group = filter_input( INPUT_GET, 'option_group' );

			if ( empty( $_wpnonce ) || ! wp_verify_nonce( $_wpnonce, $this->option_group . '_export_settings' ) ) {
				wp_die( esc_html__( 'Action failed.', $this->textdomain ) );
			}

			if ( empty( $option_group ) ) {
				wp_die( esc_html__( 'No option group specified.', $this->textdomain ) );
			}

			$options = get_option( esc_attr( $option_group ) . '_settings' );
			$options = wp_json_encode( $options );

			// output the file contents to the browser.
			header( 'Content-Type: text/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=rgs-settings-' . esc_attr( $option_group ) . '.json' );
			// @codingStandardsIgnoreStart
			echo $options; // The string is already encoded, and option values will have already been escaped.
			// @codingStandardsIgnoreEnd
			exit;
		}

		/**
		 * Import settings.
		 */
		public function import_settings() {
			$_wpnonce     = filter_input( INPUT_POST, '_wpnonce' );
			$option_group = filter_input( INPUT_POST, 'option_group' );
			$settings     = filter_input( INPUT_POST, 'settings' );

			if ( $option_group !== $this->option_group ) {
				return;
			}

			// verify nonce.
			if ( empty( $_wpnonce ) || ! wp_verify_nonce( $_wpnonce, $this->option_group . '_import_settings' ) ) {
				wp_send_json_error();
			}

			// check if $settings is a valid json.
			if ( ! is_string( $settings ) || ! is_array( json_decode( $settings, true ) ) ) {
				wp_send_json_error();
			}

			$settings_data = json_decode( $settings, true );
			update_option( $option_group . '_settings', $settings_data );

			wp_send_json_success();
		}
	
		/**
		 * Delivers minified suffix if script debug is turned off.
		 * 
		 * @since 1.1.0
		 * 
		 * @return string  ".min"|null
		 */
		private function get_minified() {
			return ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) ? '' : '.min';
		}

		/**
		 * A modified copy of core WP's do_settings_sections().
		 * 
		 * Prints out all settings sections added to a particular settings page.
		 *
		 * Part of the Settings API. Use this in a settings page callback function
		 * to output all the sections and fields that were added to that $page with
		 * add_settings_section() and add_settings_field()
		 *
		 * @global array $wp_settings_sections Storage array of all settings sections added to admin pages.
		 * @global array $wp_settings_fields Storage array of settings fields and info about their pages/sections.
		 * @since 2.7.0 (WP)
		 * @since 1.2.0 (This class)
		 *
		 * @param string $page      The slug name of the page whose settings sections you want to output.
		 * @param mixed  $tab_data  Tab ID if there are tabs, otherwise false.
		 */
		private function do_settings_sections( $page, $tab_data = false ) {
			global $wp_settings_sections, $wp_settings_fields;

			if ( ! isset( $wp_settings_sections[ $page ] ) ) {
				return;
			}

			// Hook prefix with or without tab ID.
			$hook_prefix = ( $tab_data ) ? $this->option_group . '_' . $tab_data : $this->option_group;

			foreach ( (array) $wp_settings_sections[ $page ] as $section ) {

				if ( '' !== $section['before_section'] ) {
					if ( '' !== $section['section_class'] ) {
						echo wp_kses_post( sprintf( $section['before_section'], esc_attr( $section['section_class'] ) ) );
					} else {
						echo wp_kses_post( $section['before_section'] );
					}
				}

				if ( $section['title'] ) {
					/**
					 * Action: Fires before the H2 tag of a given setting ID.
					 *
					 * @action <option_group>_<section_id>_before_h2
					 * @since 1.2.0
					 */
					do_action( $hook_prefix . '_' . $section['id'] . '_before_h2' );

					echo "<h2>{$section['title']}</h2>\n";
					
					/**
					 * Action: Fires before the H2 tag of a given setting ID.
					 *
					 * @action <option_group>_<section_id>_after_h2
					 * @since 1.2.0
					 */
					do_action( $hook_prefix . '_' . $section['id'] . '_after_h2' );
				}

				if ( $section['callback'] ) {
					call_user_func( $section['callback'], $section );
				}

				if ( ! isset( $wp_settings_fields ) || ! isset( $wp_settings_fields[ $page ] ) || ! isset( $wp_settings_fields[ $page ][ $section['id'] ] ) ) {
					continue;
				}

				/**
				 * Filters the table tag attributes for a given setting ID.
				 *
				 * @filter <option_group>_<section_id>_setting_section_table_args
				 * @since 1.2.0
				 */
				$table_args = apply_filters( $hook_prefix . '_' . $section['id'] . '_setting_section_table_args', array(
					'id' => $hook_prefix . '_' . $section['id'] . '_settings',
					'class' => 'form-table',
					'role'  => 'presentation',
				) );
				echo '<table id="' . esc_attr( $table_args['id'] ) . '" class="' . esc_attr( $table_args['class'] ) . '" role="' . esc_attr( $table_args['role'] ) . '">';
				$this->do_settings_fields( $page, $section['id'], $tab_data );
				echo '</table>';

				if ( '' !== $section['after_section'] ) {
					echo wp_kses_post( $section['after_section'] );
				}
			}
		}

		/**
		 * A modified copy of core WP's do_settings_fields().
		 * 
		 * Prints out the settings fields for a particular settings section.
		 *
		 * Part of the Settings API. Use this in a settings page to output
		 * a specific section. Should normally be called by do_settings_sections()
		 * rather than directly.
		 *
		 * @global array $wp_settings_fields Storage array of settings fields and their pages/sections.
		 *
		 * @since 2.7.0 (WP)
		 * @since 1.2.0 (This class)
		 *
		 * @param string $page      Slug title of the admin page whose settings fields you want to show.
		 * @param string $section   Slug title of the settings section whose fields you want to show.
		 * @param mixed  $tab_data  Tab ID if there are tabs, otherwise false.
		 */
		private function do_settings_fields( $page, $section, $tab_data = false ) {
			global $wp_settings_fields;

			if ( ! isset( $wp_settings_fields[ $page ][ $section ] ) ) {
				return;
			}

			// Hook prefix with or without tab ID.
			$hook_prefix = ( $tab_data ) ? $this->option_group . '_' . $tab_data : $this->option_group;

			foreach ( (array) $wp_settings_fields[ $page ][ $section ] as $field ) {

				$class = '';

				if ( ! empty( $field['args']['class'] ) ) {
					$class = ' class="' . esc_attr( $field['args']['class'] ) . '"';
				}

				$row = "<tr{$class}>";

				if ( ! empty( $field['args']['label_for'] ) ) {
					$row .= '<th scope="row"><label for="' . esc_attr( $field['args']['label_for'] ) . '">' . $field['title'] . '</label></th>';
				} else {
					$row .= '<th scope="row">' . $field['title'] . '</th>';
				}

				$row .=  '<td>';
				ob_start();
				echo call_user_func( $field['callback'], $field['args'] );
				$row .= ob_get_contents();
				ob_end_clean();
				$row .=  '</td>';
				$row .=  '</tr>';

				$rows[ $field['id'] ] = $row;
			}

			/**
			 * Filters the row array.
			 * 
			 * @since 1.1.0
			 * 
			 * @param  array  $rows
			 * @param  string $page
			 * @param  string $section
			 */
			$rows = apply_filters( $hook_prefix . '_settings_section_fields', $rows, $page, $section );

			/**
			 * Filters the row array.
			 * 
			 * @since 1.1.0
			 * 
			 * @param  array  $rows
			 * @param  string $page
			 * @param  string $section
			 */
			$rows = apply_filters( $hook_prefix . '_' . $section . '_settings_section_fields', $rows, $page, $section );

			foreach ( $rows as $key => $row ) {
				do_action( $hook_prefix . '_' . $section . '_settings_section_before_field', $page, $section, $key );
				do_action( $hook_prefix . '_' . $section . '_settings_section_before_field_' . $key, $page, $section );
				echo $row;
				do_action( $hook_prefix . '_' . $section . '_settings_section_after_field_' . $key, $page, $section );
				do_action( $hook_prefix . '_' . $section . '_settings_section_after_field', $page, $section, $key );
			}
		}

	}
}

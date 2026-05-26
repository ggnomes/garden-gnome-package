<?php
/*  Copyright (C) 2013-26  Garden Gnome Software (email : web@ggnome.com)

    This program is free software; you can redistribute it and/or
    modify it under the terms of the GNU General Public License
    as published by the Free Software Foundation; either version 2
    of the License, or any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/***********************************************************************
 * Plugin Name: Garden Gnome Package
 * Plugin URI:  https://ggnome.com/ggpkg
 * Description: Import Pano2VR & Object2VR Content into Wordpress.
 * Version:     2.5.2
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author:      <a href="https://ggnome.com">Garden Gnome Software</a>
 ************************************************************************/

define( 'GGPKG_MIN_WP_VERSION', '5.0' );
define( 'GGPKG_MIN_PHP_VERSION', '7.2' );

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once( 'include/ggpackage.php' );

function ggpkg_uninstall() {
	delete_option( 'ggsw_import_settings' );
}

class GGPackageViewer {
	public $options;
	private $options_page;

	public $valid_default_extensions = "css,html,htm,txt,pdf,xml,json,js,mjs," . // html player and web extensions
	                                   "jpg,jpeg,gif,png,apng,weba,webm,webp,svg,avif,ico,cur,avi,mp3,aac,mp4,mov,swf," . // image, video and audio formats
	                                   "ttf,woff,woff2,otf,bcmap,properties,pfb,ftl,icc," .  // web fonts and pdf viewer files
	                                   "wasm,glb,splat,ksplat,ply,spz,sog,rad"; // 3D object and splat formats

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_shortcode( 'ggpkg', array( $this, 'shortcode' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'settings_link' ) );
		add_filter( 'upload_mimes', array( $this, 'add_filetypes' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'add_allow_upload_extension_exception' ), 10, 3 );
		add_action( 'add_attachment', array( $this, 'unzip_package' ) );
		add_filter( 'post_mime_types', array( $this, 'modify_post_mime_types' ) );
		add_action( 'delete_attachment', array( $this, 'delete_package' ) );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'get_attachment_image_attributes' ), 1, 2 );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepare_attachment_for_js' ), 1, 2 );
		add_filter( 'wp_get_attachment_metadata', array( $this, 'get_attachment_metadata' ), 1, 2 );
		add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_fields_to_edit' ), 10, 2 );
		add_filter( 'media_row_actions', array( $this, 'media_row_actions' ), 10, 2 );
		add_filter( 'wp_mime_type_icon', array( $this, 'mime_type_icon' ), 1, 3 );
		add_filter( 'media_send_to_editor', array( $this, 'media_send_to_editor' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'show_unpack_notices' ) );
		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
		add_action( 'elementor/widgets/widgets_registered', [ $this, 'register_widgets' ] );

		$this->options_page = null;
		$this->options      = get_option( 'ggsw_import_settings' );
		if ( $this->options ) {
			if ( ! isset( $this->options["file_extensions"] ) ) {
				$this->options["file_extensions"] = "";
			}
			if ( ! isset( $this->options['require_upload_capability'] ) ) {
				$this->options['require_upload_capability'] = true; // Default to enabled
			}
			if ( ! isset( $this->options['upload_capability'] ) || $this->options['upload_capability'] === '' ) {
				$this->options['upload_capability'] = 'upload_ggpkg';
			}
			if ( ! isset( $this->options['strict_remote_ssl'] ) ) {
				$this->options['strict_remote_ssl'] = 'on';
			}
			if ( ! isset( $this->options['remote_url_allowed_hosts'] ) ) {
				$this->options['remote_url_allowed_hosts'] = '';
			}
		} else {
			$this->options = array();
			$this->import_settings_default();
		}
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
			add_action( 'admin_init', array( $this, 'import_admin_init' ) );
			add_action( 'admin_init', array( $this, 'handle_reextract_action' ) );
		}
		if ( isset( $_GET['page'] ) && $_GET['page'] == 'ggsw_import' ) {
			add_action( 'admin_print_scripts', array( $this, 'settings_scripts' ) );
			add_action( 'admin_print_styles', array( $this, 'settings_styles' ) );
		}

		register_activation_hook( __FILE__, array( $this, 'install' ) );
		register_uninstall_hook( __FILE__, 'ggpkg_uninstall' );
	}

	/**********************************************************************
	 * Admin / Settings
	 *********************************************************************/
	public function register_widgets( $widgets_manager = null ) {

		include_once( 'include/elementor_widget.php' );
		if ( ! $widgets_manager ) {
			$widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
		}

		if ( method_exists( $widgets_manager, 'register' ) ) {
			$widgets_manager->register( new \Elementor_GGPKG_Widget() );
		} elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
			$widgets_manager->register_widget_type( new \Elementor_GGPKG_Widget() );
		}

	}

	public function import_settings_default() {
		$this->options['width']                    = 640;
		$this->options['height']                   = 480;
		$this->options['start_preview']            = false;
		$this->options['pano2vr_player_version']   = "package";
		$this->options['object2vr_player_version'] = "package";
		$this->options['file_extensions']          = "";
		$this->options['allow_url_shortcode']      = false;
		$this->options['require_upload_capability'] = true;
		$this->options['upload_capability']         = 'upload_ggpkg';
		$this->options['strict_remote_ssl']         = 'on';
		$this->options['remote_url_allowed_hosts']  = '';
	}

	public function should_check_upload_capability() {
		return isset( $this->options['require_upload_capability'] ) && (bool) $this->options['require_upload_capability'];
	}

	public function get_upload_capability() {
		$capability = isset( $this->options['upload_capability'] ) ? sanitize_key( $this->options['upload_capability'] ) : '';
		if ( $capability === '' ) {
			$capability = 'upload_ggpkg';
		}

		return apply_filters( 'ggpkg_upload_capability', $capability );
	}

	public function can_user_upload_ggpkg( $user = null ) {
		if ( is_object( $user ) && isset( $user->ID ) ) {
			if ( user_can( $user, 'manage_options' ) || is_super_admin( $user->ID ) ) {
				return true;
			}
		} else {
			if ( current_user_can( 'manage_options' ) || is_super_admin() ) {
				return true;
			}
		}

		if ( ! $this->should_check_upload_capability() ) {
			return true;
		}

		$capability = $this->get_upload_capability();
		if ( is_object( $user ) && isset( $user->ID ) ) {
			return user_can( $user, $capability );
		}

		return current_user_can( $capability );
	}

	function attribute_set_false( $attribute ) {
		if ( isset( $attribute ) && ( $attribute == 'false' || $attribute == '0' ) ) {
			return true;
		} else {
			return false;
		}
	}

	public function install() {
		// check requirements
		global $wp_version;

		if ( version_compare( PHP_VERSION, GGPKG_MIN_PHP_VERSION, '<' ) ) {
			$this->trigger_error( sprintf( __( 'Garden Gnome Package requires PHP %1$s or higher. Current version: %2$s.', 'ggpkg' ), GGPKG_MIN_PHP_VERSION, PHP_VERSION ), E_USER_ERROR );
		}
		if ( isset( $wp_version ) && version_compare( $wp_version, GGPKG_MIN_WP_VERSION, '<' ) ) {
			$this->trigger_error( sprintf( __( 'Garden Gnome Package requires WordPress %1$s or higher. Current version: %2$s.', 'ggpkg' ), GGPKG_MIN_WP_VERSION, $wp_version ), E_USER_ERROR );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->trigger_error( __( 'The PHP Zip extension is not installed on your server. Without it the Garden Gnome Package plugin will not work. Please contact your server administrator.', 'ggpkg' ), E_USER_ERROR );
		}
		if ( ! function_exists( 'simplexml_load_file' ) ) {
			$this->trigger_error( __( 'The libxml extension is not installed on your server. Without it the Garden Gnome Package plugin will not work. Please contact your server administrator.', 'ggpkg' ), E_USER_ERROR );
		}
		if ( is_plugin_active( "ggpkg-import/ggpkg-import.php" ) ) {
			deactivate_plugins( "ggpkg-import/ggpkg-import.php" );
		}
	}

	public function trigger_error( $message, $errno ) {
		if ( isset( $_GET['action'] ) && $_GET['action'] == 'error_scrape' ) {
			echo '<strong>' . $message . '</strong>';
			exit;
		} else {
			trigger_error( $message, $errno );
		}
	}

	public function gg_chmod( $path, $filePerm = 0644, $dirPerm = 0755 ) {
		if ( ! file_exists( $path ) ) {
			return ( false );
		}

		if ( is_file( $path ) ) {
			chmod( $path, $filePerm );
		} elseif ( is_dir( $path ) ) {
			$foldersAndFiles = scandir( $path );
			$entries         = array_slice( $foldersAndFiles, 2 );

			foreach ( $entries as $entry ) {
				$this->gg_chmod( $path . "/" . $entry, $filePerm, $dirPerm );
			}

			chmod( $path, $dirPerm );
		}

		return ( true );
	}

	public function render_block( $block_attributes, $content ) {
		global $post;
//		$imageUrl     = $block_attributes['imageUrl'];
		$attachmentID = $block_attributes['attachmentID'];

		if ( isset( $attachmentID ) && ( $this->attachment_is_package( $attachmentID ) ) ) {
			$package = new GGPackage( $this, $attachmentID );
			if ( isset( $block_attributes['width'] ) ) {
				$package->width = $block_attributes['width'];
			}
			if ( isset( $block_attributes['height'] ) ) {
				$package->height = $block_attributes['height'];
			}
			if ( isset( $block_attributes['startPreview'] ) ) {
				$package->use_preview = $block_attributes['startPreview'] ? true : false;
			}

			return $package->get_html_code( $post->ID );
		} else {
			return "No Garden Gnome Package selected!";
		}
	}

	public function attachment_is_package( $attachmentID ) {
		$mime_type = get_post_mime_type( $attachmentID );

		if ( ( $mime_type == "application/ggsw-package" ) ||
		     ( $mime_type == "image/ggsw-package" ) ) {
			return true;
		}

		return false;
	}

	public function init() {
		$plugin_dir = basename( dirname( __FILE__ ) );
		load_plugin_textdomain( 'ggpkg', false, $plugin_dir . '/languages' );
		wp_set_script_translations( 'ggpkg', 'ggpkg', $plugin_dir . '/languages' );
		wp_enqueue_style( 'ggskin-style', plugin_dir_url( __FILE__ ) . 'include/ggskin.css' );

		$this->register_block();
	}

	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			// Gutenberg is not active.
			return;
		}

		$editor_dependency = wp_script_is( 'wp-block-editor', 'registered' ) ? 'wp-block-editor' : 'wp-editor';

		wp_register_script(
			'ggpkg',
			plugins_url( 'include/block.js', __FILE__ ),
			array( 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', $editor_dependency ),
			filemtime( plugin_dir_path( __FILE__ ) . 'include/block.js' )
		);

		register_block_type( 'ggpkg/ggpkg-block', array(
			'editor_script'   => 'ggpkg',
			'render_callback' => array( $this, 'render_block' )
		) );
	}

	public function add_admin_page() {
		$this->options_page = add_options_page( __( 'Garden Gnome Package', 'ggpkg' ), __( 'Garden Gnome Package', 'ggpkg' ), 'manage_options', 'ggpkg', array(
			$this,
			'import_options_page'
		) );
		add_action( 'load-' . $this->options_page, array( $this, 'add_help' ) );
	}

	public function import_options_page() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			add_settings_error( 'ggsw_import_general', 'ziparchive_check', __( 'The PHP Zip extension is not installed on your server. Without it the Garden Gnome Package plugin will not work. Please contact your server administrator.', 'ggpkg' ), 'error' );
		}
		if ( ! function_exists( 'simplexml_load_file' ) ) {
			add_settings_error( 'ggsw_import_general', 'simplexml_check', __( 'The libxml extension is not installed on your server. Without it the Garden Gnome Package plugin will not work. Please contact your server administrator.', 'ggpkg' ), 'error' );
		}

		if ( ! empty( $_POST ) && is_admin() && isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'ggsw_options' ) ) {
			$this->options = array(
				'width'                    => sanitize_text_field( $_POST['ggsw_player_size_w'] ),
				'height'                   => sanitize_text_field( $_POST['ggsw_player_size_h'] ),
				'start_preview'            => isset( $_POST['ggsw_player_start_preview'] ) ? sanitize_text_field( $_POST['ggsw_player_start_preview'] ) : "",
				'allow_url_shortcode'      => isset( $_POST['ggsw_player_allow_url_shortcode'] ) ? sanitize_text_field( $_POST['ggsw_player_allow_url_shortcode'] ) : "",
				'strict_remote_ssl'        => isset( $_POST['ggsw_player_strict_remote_ssl'] ) ? sanitize_text_field( $_POST['ggsw_player_strict_remote_ssl'] ) : "",
				'remote_url_allowed_hosts' => isset( $_POST['ggsw_player_remote_url_allowed_hosts'] ) ? sanitize_text_field( $_POST['ggsw_player_remote_url_allowed_hosts'] ) : "",
				'require_upload_capability' => isset( $_POST['ggsw_require_upload_capability'] ) ? sanitize_text_field( $_POST['ggsw_require_upload_capability'] ) : "",
				'upload_capability'         => isset( $_POST['ggsw_upload_capability'] ) ? sanitize_key( $_POST['ggsw_upload_capability'] ) : 'upload_ggpkg',
				'pano2vr_player_version'   => sanitize_text_field( $_POST['ggsw_pano2vr_player_version'] ),
				'object2vr_player_version' => sanitize_text_field( $_POST['ggsw_object2vr_player_version'] ),
				'file_extensions'          => sanitize_text_field( $_POST['ggsw_file_extensions'] ),
			);

			$success = $this->options == get_option( 'ggsw_import_settings' );
			$success |= update_option( 'ggsw_import_settings', $this->options );
			if ( $success ) {
				add_settings_error( 'ggsw_import_general', 'settings_updated', __( 'Settings saved.', 'ggpkg' ), 'updated' );
			} else {
				add_settings_error( 'ggsw_import_general', 'settings_updated', __( 'Settings could not be saved.', 'ggpkg' ), 'error' );
			}
		} ?>
        <div class="wrap">
			<?php settings_errors( 'ggsw_import_general' ); ?>
            <h2><?php _e( 'Garden Gnome Package' ); ?></h2>
            <form action="" method="post">
                <input name="nonce" type="hidden" id="nonce" value="<?php echo wp_create_nonce( 'ggsw_options' ); ?>">
                <table class="form-table">
                    <tr valign="top">
                        <td scope="row"><?php _e( 'Default Player Size', 'ggpkg' ) ?></td>
                        <td>
                            <label for="ggsw_player_size_w"><?php _e( 'Width' ); ?></label>
                            <input name="ggsw_player_size_w" type="text" id="ggsw_player_size_w"
                                   value="<?php echo esc_attr( $this->options['width'] ); ?>"
                                   class="medium-text"/>
                            <label for="ggsw_player_size_h"><?php _e( 'Height' ); ?></label>
                            <input name="ggsw_player_size_h" type="text" id="ggsw_player_size_h"
                                   value="<?php echo esc_attr( $this->options['height'] ); ?>"
                                   class="medium-text"/><br/>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="ggsw_player_start_preview"><?php _e( 'Preview Image', 'ggpkg' ); ?></label>
                        </td>
                        <td>
                            <input name="ggsw_player_start_preview" type="checkbox"
							       <?php if ( $this->options['start_preview'] ) : ?>checked<?php endif; ?> /><?php _e( 'Show the preview image on start.', 'ggpkg' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="ggsw_player_allow_url_shortcode"><?php _e( 'Shortcode', 'ggpkg' ); ?></label>
                        </td>
                        <td>
                            <input name="ggsw_player_allow_url_shortcode" type="checkbox"
							       <?php if ( $this->options['allow_url_shortcode'] ?? false ) : ?>checked<?php endif; ?> /><?php _e( 'Enable the <code>url</code> field in the <code>ggpkg</code> shortcode. This may pose a security risk if you use untrusted external URLs.', 'ggpkg' ); ?>
                        </td>
                    </tr>
					<tr>
						<td>
							<label for="ggsw_player_strict_remote_ssl"><?php _e( 'Remote URL Security', 'ggpkg' ); ?></label>
						</td>
						<td>
							<input id="ggsw_player_strict_remote_ssl" name="ggsw_player_strict_remote_ssl" type="checkbox"
							       <?php if ( $this->options['strict_remote_ssl'] ?? 'on' ) : ?>checked<?php endif; ?> /><?php _e( 'Verify TLS certificates for remote shortcode URLs (recommended).', 'ggpkg' ); ?>
							<br/>
							<label for="ggsw_player_remote_url_allowed_hosts"><?php _e( 'Allowed hosts (comma separated, leave empty to allow all):', 'ggpkg' ); ?></label>
							<input id="ggsw_player_remote_url_allowed_hosts" name="ggsw_player_remote_url_allowed_hosts" type="text"
							       value="<?php echo esc_attr( $this->options['remote_url_allowed_hosts'] ?? '' ); ?>"
							       class="regular-text"/>
						</td>
					</tr>
					<tr>
						<td>
							<label for="ggsw_require_upload_capability"><?php _e( 'Upload Permissions', 'ggpkg' ); ?></label>
						</td>
						<td>
							<input id="ggsw_require_upload_capability" name="ggsw_require_upload_capability" type="checkbox"
							       <?php if ( $this->options['require_upload_capability'] ?? true ) : ?>checked<?php endif; ?> /><?php _e( 'Only allow GGPKG uploads for users with this capability:', 'ggpkg' ); ?>
							<input id="ggsw_upload_capability" name="ggsw_upload_capability" type="text"
										   value="<?php echo esc_attr( $this->options['upload_capability'] ?? 'upload_ggpkg' ); ?>"
								   class="regular-text"/>
									<p class="description"><?php _e( 'Enabled by default. Default capability: <code>upload_ggpkg</code>', 'ggpkg' ); ?></p>
						</td>
					</tr>
                    <tr>
                        <td valign="top" scope="row"><?php _e( 'Valid File Extensions', 'ggpkg' ) ?></td>
                        <td>
                            <label for="ggsw_file_extensions"><?php _e( 'Default:' ); ?></label>
                            <input id="ggsw_file_extensions" type="text" maxlength="100" disabled
                                   value="<?php echo $this->valid_default_extensions; ?>"
                                   class="large-text"/><br/>
                            <label for="ggsw_file_extensions"><?php _e( 'Additional:' ); ?></label>
                            <input name="ggsw_file_extensions" type="text" id="ggsw_file_extensions"
                                   value="<?php echo esc_attr( $this->options['file_extensions'] ); ?>"
                                   class="large-text"/><br/>
                        </td>
                    </tr>
                    <tr>
                        <td><h3>Pano2VR</h3></td>
                    </tr>
                    <tr>
                        <td>
                            <label for="ggsw_pano2vr_player_version"><?php _e( 'Player Version', 'ggpkg' ); ?></label>
                        </td>
                        <td>
                            <select id="ggsw_pano2vr_player_version" name="ggsw_pano2vr_player_version">
                                <option value="package"><?php _e( 'from package', 'ggpkg' ); ?></option>
								<?php
								if ( file_exists( plugin_dir_path( __FILE__ ) . 'pano2vr_player' ) ) {
									$all_folders = scandir( plugin_dir_path( __FILE__ ) . 'pano2vr_player' );
									foreach ( $all_folders as $version_folder ) {
										if ( substr( $version_folder, 0, 1 ) != '.' ) { ?>
                                            <option value="<?php echo esc_attr( $version_folder ); ?>" <?php if ( $this->options['pano2vr_player_version'] == $version_folder ) {
												echo( 'selected = \"selected\"' );
											} ?>><?php echo esc_html( str_replace( "_", " ", $version_folder ) ); ?></option>
											<?php
										}
									}
								} ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><h3>Object2VR</h3></td>
                    </tr>
                    <tr>
                        <td>
                            <label for="ggsw_object2vr_player_version"><?php _e( 'Player Version', 'ggpkg' ); ?></label>
                        </td>
                        <td>
                            <select id="ggsw_object2vr_player_version" name="ggsw_object2vr_player_version">
                                <option value="package"><?php _e( 'from package', 'ggpkg' ); ?></option>
								<?php
								if ( file_exists( plugin_dir_path( __FILE__ ) . 'object2vr_player' ) ) {
									$all_folders = scandir( plugin_dir_path( __FILE__ ) . 'object2vr_player' );
									foreach ( $all_folders as $version_folder ) {
										if ( substr( $version_folder, 0, 1 ) != '.' ) { ?>
                                            <option value="<?php echo esc_attr( $version_folder ); ?>" <?php if ( $this->options['object2vr_player_version'] == $version_folder ) {
												echo( 'selected = \"selected\"' );
											} ?>><?php echo esc_html( str_replace( "_", " ", $version_folder ) ); ?></option>
											<?php
										}
									}
								} ?>
                            </select>
                        </td>
                    </tr>
					<?php
					do_settings_sections( 'ggpkg' ); ?>
                </table>

				<?php submit_button(); ?>
            </form>
        </div>
		<?php
	}

	public function settings_scripts() {
		wp_enqueue_script( 'media-upload' );
		wp_enqueue_script( 'thickbox' );
	}

	public function settings_styles() {
		wp_enqueue_style( 'thickbox' );
	}

	public function import_admin_init() {
		$args = array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'import_validate_options' ),
			'default'           => null,
		);
		register_setting( 'ggsw_import_settings', 'ggsw_import_settings', $args );
		add_settings_section( 'ggsw_import_settings_main', __( 'General Settings', 'ggpkg' ), array(
			$this,
			'import_section_text'
		), 'ggpkg' );
	}

	public function import_section_text() {
	}

	public function import_validate_options( $input ) {
		$valid = array();
		if ( isset( $input['width'] ) && ( $input['width'] ) ) {
			$valid['width'] = strval( $input['width'] );
		} else {
			$valid['width'] = '640';
		}
		if ( isset( $input['height'] ) && ( $input['height'] ) ) {
			$valid['height'] = strval( $input['height'] );
		} else {
			$valid['height'] = '480';
		}
		$valid['start_preview']            = $input['start_preview'] === "on" ? "on" : "";
		$valid['allow_url_shortcode']      = $input['allow_url_shortcode'] === "on" ? "on" : "";
		$valid['strict_remote_ssl']        = isset( $input['strict_remote_ssl'] ) && $input['strict_remote_ssl'] === "on" ? "on" : "";
		$valid['remote_url_allowed_hosts'] = isset( $input['remote_url_allowed_hosts'] ) ? sanitize_text_field( $input['remote_url_allowed_hosts'] ) : '';
		$valid['require_upload_capability'] = isset( $input['require_upload_capability'] ) && $input['require_upload_capability'] === "on" ? "on" : "";
		$valid['upload_capability']         = isset( $input['upload_capability'] ) ? sanitize_key( $input['upload_capability'] ) : 'upload_ggpkg';
		if ( $valid['upload_capability'] === '' ) {
			$valid['upload_capability'] = 'upload_ggpkg';
		}
		$valid['pano2vr_player_version']   = strval( $input['pano2vr_player_version'] );
		$valid['object2vr_player_version'] = strval( $input['object2vr_player_version'] );
		$valid['file_extensions']          = strval( $input['file_extensions'] );

		return $valid;
	}

	function add_help() {
		$screen = get_current_screen();
		$screen->add_help_tab( array(
			'id'      => 'ggpkg-default',
			'title'   => __( 'Default' ),
			'content' => '<br/><a href="https://ggnome.com/ggpkg" target="_blank">' . __( "Garden Gnome Package Documentation", 'ggpkg' ) . '</a>'
		) );
	}

	public function settings_link( $links ) {
		array_unshift( $links, '<a href="options-general.php?page=ggpkg">' . __( 'Settings', 'ggpkg' ) . '</a>' );

		return $links;
	}

	/**********************************************************************
	 * Upload / Display / Delete GGPKG-Files
	 *********************************************************************/

	public function add_filetypes( $existing_mimes = array(), $user = null ) {
		if ( ! $this->can_user_upload_ggpkg( $user ) ) {
			unset( $existing_mimes['ggpkg'] );

			return $existing_mimes;
		}

		// add ggpkg extension to the array
		$existing_mimes['ggpkg'] = 'image/ggsw-package';
		// old type, does not all preview images everywhere
		//	$existing_mimes['ggpkg'] = 'application/ggsw-package';
		return $existing_mimes;
	}

	public function add_allow_upload_extension_exception( $data, $file, $filename ) {
		if ( ! $this->can_user_upload_ggpkg() ) {
			return $data;
		}

		if ( substr( $filename, - 6 ) == ".ggpkg" ) {
			$proper_filename = false;
			$ext             = 'ggpkg';
			$type            = 'image/ggsw-package';

			return compact( 'ext', 'type', 'proper_filename' );
		}

		return $data;
	}

	function modify_post_mime_types( $post_mime_types ) {
		$post_mime_types['image/ggsw-package'] = array(
			__( 'Garden Gnome Packages' ),
			__( 'Manage GGPKGs' ),
			_n_noop( 'GGPKG <span class="count">(%s)</span>', 'GGPKGs <span class="count">(%s)</span>' )
		);

		return $post_mime_types;
	}

// Exception for WordPress 4.7.1 file contents check system using finfo_file (wp-include/functions.php)

	public function unzip_package( $attachmentID = "" ) {
		$attachmentID = intval( $attachmentID );
		if ( $attachmentID <= 0 ) {
			return;
		}

		$had_unpack_errors   = false;
		$had_unpack_warnings = false;

		$valid_extensions = explode( ",", $this->valid_default_extensions );
		if ( isset( $this->options['file_extensions'] ) ) {
			$more_extensions = explode( ",", $this->options['file_extensions'] );
			if ( $more_extensions ) {
				$valid_extensions = array_merge( $valid_extensions, $more_extensions );
			}
		}
		$valid_extensions = array_values( array_filter( array_map( 'strtolower', array_map( 'trim', $valid_extensions ) ) ) );
		if ( $this->attachment_is_package( $attachmentID ) ) {
			$this->set_unpack_status( $attachmentID, 'processing', __( 'Unpacking package...', 'ggpkg' ) );
			$attachment = get_attached_file( $attachmentID );
			if ( $attachment ) {
				$path_parts   = pathinfo( $attachment );
				$filename     = str_replace( '-', '_', $path_parts['filename'] );
				$filename     = str_replace( ' ', '_', $filename );
				$extract_path = $path_parts['dirname'] . "/" . $filename;

				if ( ! $this->guard_extract_path_collision( $attachmentID, $extract_path ) ) {
					$msg = sprintf( __( 'Extraction folder collision detected for package %s.', 'ggpkg' ), $filename );
					$this->error_log( $msg );
					$this->record_unpack_error( $attachmentID, $msg );
					$this->queue_unpack_notice( $msg );
					$this->set_unpack_status( $attachmentID, 'error', __( 'Unpack failed due to extraction path collision.', 'ggpkg' ) );

					return;
				}

				if ( is_numeric( substr( $filename, 0, 1 ) ) ) {
					$filename = '_' . $filename;
				}

				if ( ! $this->prepare_extract_directory( $extract_path ) ) {
					$msg = sprintf( __( 'Could not create extraction folder for package %s.', 'ggpkg' ), $filename );
					$this->error_log( $msg );
					$this->record_unpack_error( $attachmentID, $msg );
					$this->queue_unpack_notice( $msg );
					$had_unpack_errors = true;
				}
				$zip_file = new ZipArchive();
				if ( $zip_file->open( $attachment ) === true ) {
					$validFiles = [];
					for ( $i = 0; $i < $zip_file->count(); $i ++ ) {
						$fn  = $zip_file->getNameIndex( $i );
						$ext = strtolower( pathinfo( $fn, PATHINFO_EXTENSION ) );
						if ( ( $ext === "" ) || in_array( $ext, $valid_extensions ) ) {
							$validFiles[] = $fn;
						} else {
							$msg = sprintf( __( "Invalid file extension '%1\$s' in file %2\$s", 'ggpkg' ), $ext, $fn );
							$this->error_log( $msg );
							$this->record_unpack_error( $attachmentID, $msg, 'warning' );
							$this->queue_unpack_notice( $msg, 'warning' );
							$had_unpack_warnings = true;
						}
					}

					if ( ! $zip_file->extractTo( $extract_path, $validFiles ) ) {
						$status = $zip_file->getStatusString();
						$this->error_log( $status );
						$this->error_log( $validFiles );
						$msg = sprintf( __( 'Error extracting package %1$s: %2$s', 'ggpkg' ), $filename, $status );
						$this->error_log( "Error extracting " . $filename );
						$this->record_unpack_error( $attachmentID, $msg );
						$this->queue_unpack_notice( $msg );
						$had_unpack_errors = true;
					} else {
						update_post_meta( $attachmentID, 'ggpkg_extract_path', $extract_path );
						$player_version = '';
						if ( file_exists( $extract_path . "/gginfo.json" ) ) {
							if ( ( $json_content = file_get_contents( $extract_path . "/gginfo.json" ) ) != false ) {
								$json_content = utf8_encode( $json_content );
								$json         = json_decode( $json_content );
								$json_player  = $json->{'player'};
								if ( isset( $json_player ) ) {
									$json_version = $json_player->{'version'};
									if ( isset( $json_version ) ) {
										$player_version = $json_version;
									}
								}
							}
						}
						if ( $player_version == '' ) {
							// try to parse older player versions from the header
							foreach ( [ 'pano2vr', 'object2vr' ] as &$sw ) {
								if ( file_exists( $extract_path . "/" . $sw . "_player.js" ) ) {
									$lines = file( $extract_path . "/" . $sw . "_player.js" );
									if ( isset( $lines[1] ) ) {
										preg_match( "/.*\s(\d\.\w+(\.[\w|\ ]+)?).*$/", $lines[1], $matches );
										if ( isset( $matches[1] ) ) {
											$player_version = $matches[1];
										}
									}
								}
							}
						}
						if ( $player_version != '' ) {
							$player_version = str_replace( " ", "_", $player_version );
							foreach ( [ 'pano2vr', 'object2vr' ] as &$sw ) {
								$src_player = $extract_path . "/" . $sw . "_player.js";
								if ( file_exists( $src_player ) ) {
									if ( ! file_exists( plugin_dir_path( __FILE__ ) . "" . $sw . "_player" ) ) {
										$this->ensure_directory( plugin_dir_path( __FILE__ ) . $sw . "_player" );
									}
									$dest_dir = plugin_dir_path( __FILE__ ) . $sw . "_player/" . $player_version;
									if ( ! file_exists( $dest_dir ) ) {
										if ( $this->ensure_directory( $dest_dir ) ) {
											copy( $src_player, $dest_dir . "/" . $sw . "_player.js" );
										}
									}
								}
							}
						}
						// $this->gg_chmod($extract_path,666,777); // make sure the user can also delete the package files
						foreach ( [ 'pano2vr', 'object2vr' ] as &$sw ) {
							if ( file_exists( $extract_path . "/" . $sw . "_player.js" ) ) {
								$uniquePlayerVar = "var " . $filename . "_" . $sw . "Player = " . $sw . "Player;";
								$playerFile      = $extract_path . "/" . $sw . "_player.js";
								file_put_contents( $playerFile, $uniquePlayerVar, FILE_APPEND );
								if ( file_exists( $extract_path . "/skin.js" ) ) {
									$uniqueSkinVar = "var " . $filename . "_" . $sw . "Skin = " . $sw . "Skin;";
									$skinFile      = $extract_path . "/skin.js";
									file_put_contents( $skinFile, $uniqueSkinVar, FILE_APPEND );
								}
							}
						}
					}
					$zip_file->close();
				} else {
					$msg = sprintf( __( 'Could not open package archive %s.', 'ggpkg' ), $filename );
					$this->error_log( $msg );
					$this->record_unpack_error( $attachmentID, $msg );
					$this->queue_unpack_notice( $msg );
					$had_unpack_errors = true;
				}

				if ( $had_unpack_errors ) {
					$this->set_unpack_status( $attachmentID, 'error', __( 'Package unpack failed. See error details below.', 'ggpkg' ) );
				} elseif ( $had_unpack_warnings ) {
					$this->set_unpack_status( $attachmentID, 'warning', __( 'Package unpacked with warnings. Some files were skipped.', 'ggpkg' ) );
				} else {
					$this->set_unpack_status( $attachmentID, 'ok', __( 'Package unpacked successfully.', 'ggpkg' ) );
					delete_post_meta( $attachmentID, 'ggpkg_unpack_errors' );
				}
			} else {
				$msg = __( 'Could not locate uploaded package file for extraction.', 'ggpkg' );
				$this->record_unpack_error( $attachmentID, $msg );
				$this->queue_unpack_notice( $msg );
				$this->set_unpack_status( $attachmentID, 'error', __( 'Unpack failed because the uploaded file could not be located.', 'ggpkg' ) );
			}
		}
	}

	public function record_unpack_error( $attachment_id, $message, $severity = 'error' ) {
		$attachment_id = intval( $attachment_id );
		if ( $attachment_id <= 0 ) {
			return;
		}

		$errors = get_post_meta( $attachment_id, 'ggpkg_unpack_errors', true );
		if ( ! is_array( $errors ) ) {
			$errors = array();
		}

		$errors[] = wp_strip_all_tags( $message );
		$errors   = array_slice( array_unique( $errors ), - 10 );

		update_post_meta( $attachment_id, 'ggpkg_unpack_errors', $errors );
		$this->append_unpack_status_message( $attachment_id, $severity, $message );
	}

	public function set_unpack_status( $attachment_id, $status, $summary ) {
		$attachment_id = intval( $attachment_id );
		if ( $attachment_id <= 0 ) {
			return;
		}

		$current = get_post_meta( $attachment_id, 'ggpkg_unpack_status', true );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$current['status']     = sanitize_key( $status );
		$current['summary']    = wp_strip_all_tags( $summary );
		$current['updated_at'] = current_time( 'mysql' );
		if ( ! isset( $current['messages'] ) || ! is_array( $current['messages'] ) ) {
			$current['messages'] = array();
		}

		update_post_meta( $attachment_id, 'ggpkg_unpack_status', $current );
	}

	public function append_unpack_status_message( $attachment_id, $severity, $message ) {
		$attachment_id = intval( $attachment_id );
		if ( $attachment_id <= 0 ) {
			return;
		}

		$current = get_post_meta( $attachment_id, 'ggpkg_unpack_status', true );
		if ( ! is_array( $current ) ) {
			$current = array(
				'status'     => 'ok',
				'summary'    => '',
				'updated_at' => current_time( 'mysql' ),
				'messages'   => array(),
			);
		}
		if ( ! isset( $current['messages'] ) || ! is_array( $current['messages'] ) ) {
			$current['messages'] = array();
		}

		$current['messages'][] = array(
			'severity' => sanitize_key( $severity ),
			'message'  => wp_strip_all_tags( $message ),
			'time'     => current_time( 'mysql' ),
		);
		$current['messages']   = array_slice( $current['messages'], - 20 );
		$current['updated_at'] = current_time( 'mysql' );

		if ( $severity === 'error' ) {
			$current['status'] = 'error';
		} elseif ( $severity === 'warning' && ( ! isset( $current['status'] ) || $current['status'] !== 'error' ) ) {
			$current['status'] = 'warning';
		}

		update_post_meta( $attachment_id, 'ggpkg_unpack_status', $current );
	}

	public function queue_unpack_notice( $message, $type = 'error' ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$transient_key = 'ggpkg_unpack_notices_' . $user_id;
		$notices       = get_transient( $transient_key );
		if ( ! is_array( $notices ) ) {
			$notices = array();
		}
		$notices[] = array(
			'type'    => sanitize_key( $type ),
			'message' => wp_strip_all_tags( $message ),
		);

		set_transient( $transient_key, $notices, 15 * MINUTE_IN_SECONDS );
	}

	public function show_unpack_notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		if ( ! in_array( $screen->base, array( 'upload', 'media', 'post' ), true ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$transient_key = 'ggpkg_unpack_notices_' . $user_id;
		$notices       = get_transient( $transient_key );
		if ( ! is_array( $notices ) || empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			if ( is_array( $notice ) ) {
				$type    = isset( $notice['type'] ) ? $notice['type'] : 'error';
				$message = isset( $notice['message'] ) ? $notice['message'] : '';
			} else {
				$type    = 'error';
				$message = $notice;
			}

			$notice_class = in_array( $type, array( 'warning', 'success', 'info', 'error' ), true ) ? 'notice-' . $type : 'notice-error';
			echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}

		delete_transient( $transient_key );
	}

	public function attachment_fields_to_edit( $form_fields, $post ) {
		if ( ! $this->attachment_is_package( $post->ID ) ) {
			return $form_fields;
		}

		$unpack_status = get_post_meta( $post->ID, 'ggpkg_unpack_status', true );
		if ( is_array( $unpack_status ) && ! empty( $unpack_status['status'] ) ) {
			$status_value = strtoupper( $unpack_status['status'] );
			$summary      = isset( $unpack_status['summary'] ) ? $unpack_status['summary'] : '';
			$updated_at   = isset( $unpack_status['updated_at'] ) ? $unpack_status['updated_at'] : '';

			$form_fields['ggpkg_unpack_status'] = array(
				'label' => __( 'GGPKG Unpack Status', 'ggpkg' ),
				'input' => 'html',
				'html'  => '<div><strong>' . esc_html( $status_value ) . '</strong><br/>' . esc_html( $summary ) . ( $updated_at ? '<br/><em>' . esc_html( $updated_at ) . '</em>' : '' ) . '</div>',
			);
		}

		$errors = get_post_meta( $post->ID, 'ggpkg_unpack_errors', true );
		if ( ! is_array( $errors ) || empty( $errors ) ) {
			return $form_fields;
		}

		$error_items = '';
		foreach ( $errors as $error ) {
			$error_items .= '<li>' . esc_html( $error ) . '</li>';
		}

		$form_fields['ggpkg_unpack_errors'] = array(
			'label' => __( 'GGPKG Unpack Errors', 'ggpkg' ),
			'input' => 'html',
			'html'  => '<div style="color:#b32d2e;"><ul style="margin:0 0 0 1.2em;">' . $error_items . '</ul></div>',
		);

		return $form_fields;
	}

	public function media_row_actions( $actions, $post ) {
		if ( ! $this->attachment_is_package( $post->ID ) || ! $this->can_user_upload_ggpkg() ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'upload.php?action=ggpkg_reextract&attachment_id=' . intval( $post->ID ) ),
			'ggpkg_reextract_' . intval( $post->ID )
		);

		$actions['ggpkg_reextract'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Re-extract GGPKG', 'ggpkg' ) . '</a>';

		return $actions;
	}

	public function handle_reextract_action() {
		if ( ! is_admin() || ! isset( $_GET['action'] ) || $_GET['action'] !== 'ggpkg_reextract' ) {
			return;
		}
		if ( ! $this->can_user_upload_ggpkg() ) {
			wp_die( esc_html__( 'You do not have permission to manage GGPKG extraction.', 'ggpkg' ) );
		}

		$attachment_id = isset( $_GET['attachment_id'] ) ? intval( $_GET['attachment_id'] ) : 0;
		if ( $attachment_id <= 0 || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'ggpkg_reextract_' . $attachment_id ) ) {
			wp_die( esc_html__( 'Invalid re-extract request.', 'ggpkg' ) );
		}
		if ( ! $this->attachment_is_package( $attachment_id ) ) {
			$this->queue_unpack_notice( __( 'Selected attachment is not a valid GGPKG file.', 'ggpkg' ) );
			wp_safe_redirect( admin_url( 'upload.php' ) );
			exit;
		}

		$this->unzip_package( $attachment_id );
		$status = get_post_meta( $attachment_id, 'ggpkg_unpack_status', true );
		if ( is_array( $status ) && isset( $status['status'] ) && $status['status'] === 'ok' ) {
			$this->queue_unpack_notice( __( 'GGPKG package re-extracted successfully.', 'ggpkg' ), 'success' );
		}

		wp_safe_redirect( admin_url( 'upload.php' ) );
		exit;
	}

	public function ensure_directory( $path ) {
		if ( is_dir( $path ) ) {
			return true;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		if ( is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'mkdir' ) ) {
			if ( $wp_filesystem->mkdir( $path, FS_CHMOD_DIR ) ) {
				return true;
			}
		}

		return wp_mkdir_p( $path );
	}

	public function prepare_extract_directory( $extract_path ) {
		if ( is_dir( $extract_path ) ) {
			if ( ! $this->del_tree( $extract_path ) ) {
				return false;
			}
		}

		return $this->ensure_directory( $extract_path );
	}

	public function guard_extract_path_collision( $attachment_id, $extract_path ) {
		$query = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'post__not_in'   => array( intval( $attachment_id ) ),
			'meta_query'     => array(
				array(
					'key'   => 'ggpkg_extract_path',
					'value' => $extract_path,
				),
			),
		) );

		return empty( $query );
	}

	/***********************************************************************
	 * Activation / Deactivation
	 ***********************************************************************/

	public function error_log( $message ) {
		$timestamp        = date( 'd/m/Y H:i:s' );
		$ggsw_plugin_path = plugin_dir_path( __FILE__ );
		$ggsw_log_file    = $ggsw_plugin_path . 'error.log';
		$message          = print_r( $message, true );
		if ( ! file_exists( $ggsw_log_file ) ) {
			$file_handle = fopen( $ggsw_log_file, 'w' );
			fwrite( $file_handle, "[" . $timestamp . "] : Logfile created.\r\n" );
			fclose( $file_handle );
		}

		error_log( "[" . $timestamp . "] : " . $message . "\r\n", 3, $ggsw_log_file );
	}

	public function delete_package( $attachmentID = "" ) {
		if ( $this->attachment_is_package( $attachmentID ) ) {
			$attachment = get_attached_file( $attachmentID );
			if ( $attachment ) {
				$path_parts   = pathinfo( $attachment );
				$filename     = str_replace( '-', '_', $path_parts['filename'] );
				$extract_path = $path_parts['dirname'] . "/" . $filename;
				if ( ! $this->del_tree( $extract_path ) ) {
					$this->error_log( "Could not delete directory " . $extract_path );
				}
			}
			delete_post_meta( $attachmentID, 'ggpkg_unpack_errors' );
			delete_post_meta( $attachmentID, 'ggpkg_unpack_status' );
			delete_post_meta( $attachmentID, 'ggpkg_extract_path' );
		}
	}

	public function del_tree( $dir ) {
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			( is_dir( "$dir/$file" ) ) ? $this->del_tree( "$dir/$file" ) : unlink( "$dir/$file" );
		}

		return rmdir( $dir );
	}

	public function get_attachment_image_attributes( $data, $attachment ) // Change icon in the library view
	{
		$attachmentID = $attachment->ID;
		if ( $this->attachment_is_package( $attachmentID ) ) {
			$package = new GGPackage( $this, $attachmentID );
			if ( $package->file_in_package( "preview.jpg" ) ) {
				$data["src"] = $package->to_url( "preview.jpg" );
			} else {
				$data["src"] = plugin_dir_url( __FILE__ ) . "include/ggpkg.png";
			}
		}

		return $data;
	}

	public function prepare_attachment_for_js( $data, $attachment ) {
		$attachmentID = $attachment->ID;
		if ( $this->attachment_is_package( $attachmentID ) ) {
			if ( ! is_array( $data ) ) { // fix warnings, if the package is included as image
				$data = array();
			}
			$package = new GGPackage( $this, $attachmentID );
			if ( $package->file_in_package( "preview.jpg" ) ) {
				$data['url'] = $package->to_url( "preview.jpg" );
				$s           = $package->get_preview_image_size();
				if ( ( $s[0] > 0 ) && ( $s[1] > 0 ) ) {
					$data["width"]  = $s[0];
					$data["height"] = $s[1];
				}
				$data['mime']    = 'image/jpeg';
				$data['type']    = 'image';
				$data['subtype'] = 'jpeg';
				if ( ( $data['sizes'] ) && ( $data['sizes']['full'] ) ) {
					$data['sizes']['full']['url'] = $package->to_url( "preview.jpg" );
				}
			} else {
				// show the default file name, only works for non "image" types
				$data['mime']    = 'application/ggsw-package';
				$data['type']    = 'application';
				$data['subtype'] = 'ggsw-package';
			}
		}

		return $data;
	}

	public function get_attachment_metadata( $data, $attachmentID ) // used in library view
	{
		if ( $this->attachment_is_package( $attachmentID ) ) {
			if ( ! is_array( $data ) ) { // fix warnings, if the package is included as image
				$data = array();
			}
			$package = new GGPackage( $this, $attachmentID );
			if ( $package->file_in_package( "preview.jpg" ) ) {
				$data['file']      = $package->to_url( "preview.jpg" );
				$data['thumb']     = $package->to_url( "preview.jpg" );
				$data['mime-type'] = 'image/jpeg';
				$s                 = $package->get_preview_image_size();
				if ( ( $s[0] > 0 ) && ( $s[1] > 0 ) ) {
					$data["width"]  = intval( $s[0] );
					$data["height"] = intval( $s[1] );
				}
				$sizes = array();
				$thumb = array();
				if ( ( $s[0] > 0 ) && ( $s[1] > 0 ) ) {
					$thumb["width"]  = intval( $s[0] );
					$thumb["height"] = intval( $s[1] );
					$thumb['file']   = $package->folder() . "/preview.jpg";
				}
				$sizes['thumbnail'] = $thumb;
				$data['sizes']      = $sizes;
			} else {
				$data['mime-type'] = 'application/ggsw-package';
				$data["width"]     = 100;
				$data["height"]    = 128;
			}
		}

		return $data;
	}

	public function mime_type_icon( $data, $mime_type, $attachmentID ) {
		if ( ( $mime_type == "application/ggsw-package" ) ||
		     ( $mime_type == "image/ggsw-package" ) ) {
			$data = plugin_dir_url( __FILE__ ) . "include/ggpkg_file.png";
		}

		return $data;
	}

	public function media_send_to_editor( $html, $attachmentID, $attachment ) {
		if ( $this->attachment_is_package( $attachmentID ) && isset( $_POST['post_id'] ) && $_POST['post_id'] != 0 ) {
			$html = "[ggpkg id=" . $attachmentID . "]\n";

			return $html;
		} else {
			return $html;
		}
	}

	public function shortcode( $attributes ) {
		global $post;
		$attachmentID = isset( $attributes['id'] ) ? $attributes['id'] : false;
		$package      = new GGPackage( $this );

		if ( isset( $attributes['url'] ) ) {
			if ( $this->options['allow_url_shortcode'] ?? false ) {
				$url = $attributes['url'];
				if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
					$url = strtok( $url, "?" ); // remove query string
					$url = strtok( $url, "#" ); // remove fragment string
					$url = str_replace( '/index.html', '/', $url );
					if ( substr( $url, - 1 ) != '/' ) {
						$url = $url . '/';
					}
					if ( ! $package->set_from_url( esc_url( $url ) ) ) {
						return "<b>Garden Gnome Package Shortcode: Could not load remote package URL. Check allowed hosts and TLS settings.</b>";
					}
				} else {
					return "<b>Invalid URL</b>";
				}
			} else {
				return "<b>Garden Gnome Package Shortcode: The url field is disabled in the settings</b>";
			}
		} elseif ( $attachmentID ) {
			$package->from_attachment( $attachmentID );
		}
		if ( isset( $attributes['start_preview'] ) ) {
			$package->use_preview = $this->attribute_set_true( $attributes['start_preview'] );
		}
		if ( isset( $attributes['width'] ) ) {
			$package->width = trim( $attributes['width'] );
		}
		if ( isset( $attributes['height'] ) ) {
			$package->height = trim( $attributes['height'] );
		}
		if ( isset( $attributes['start_node'] ) ) {
			if ( preg_match( "/^(\w{1,25})$/", $attributes['start_node'] ) ) {
				$package->start_node = trim( $attributes['start_node'] );
			}
		}
		if ( isset( $attributes['start_view'] ) ) {
			if ( preg_match( "/(([\w\,\|\/]{0,30}))$/", $attributes['start_view'] ) ) {
				$package->start_view = trim( $attributes['start_view'] );
			}
		}

		return $package->get_html_code( $post->ID );
	}

	function attribute_set_true( $attribute ) {
		if ( isset( $attribute ) && ( $attribute == 'true' || $attribute == '1' ) ) {
			return true;
		} else {
			return false;
		}
	}
}

$ggPackageViewer = new GGPackageViewer();


?>

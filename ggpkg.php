<?php
/*  Copyright (C) 2013-19  Garden Gnome Software (email : web@ggnome.com)

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
 * Version:     2.2.8
 * Author:      <a href="https://ggnome.com">Garden Gnome Software</a>
 ************************************************************************/

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
		add_filter( 'wp_mime_type_icon', array( $this, 'mime_type_icon' ), 1, 3 );
		add_filter( 'media_send_to_editor', array( $this, 'media_send_to_editor' ), 10, 3 );
		add_action( 'elementor/widgets/widgets_registered', [ $this, 'register_widgets' ] );

		$this->options_page = null;
		$this->options      = get_option( 'ggsw_import_settings' );
		if ( ! $this->options ) {
			$this->options = array();
			$this->import_settings_default();
		}
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
			add_action( 'admin_init', array( $this, 'import_admin_init' ) );
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
	public function register_widgets() {

		include_once( 'include/elementor_widget.php' );
		\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_GGPKG_Widget() );

	}

	public function import_settings_default() {
		$this->options['width']                    = 640;
		$this->options['height']                   = 480;
		$this->options['start_preview']            = false;
		$this->options['pano2vr_player_version']   = "package";
		$this->options['object2vr_player_version'] = "package";
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

		wp_register_script(
			'ggpkg',
			plugins_url( 'include/block.js', __FILE__ ),
			array( 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-editor' ),
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

		if ( ! empty( $_POST ) && is_admin() && wp_verify_nonce( $_POST['nonce'], 'ggsw_options' ) ) {
			$this->options = array(
				'width'                    => sanitize_text_field( $_POST['ggsw_player_size_w'] ),
				'height'                   => sanitize_text_field( $_POST['ggsw_player_size_h'] ),
				'start_preview'            => isset( $_POST['ggsw_player_start_preview'] ) ? sanitize_text_field( $_POST['ggsw_player_start_preview'] ) : "",
				'pano2vr_player_version'   => sanitize_text_field( $_POST['ggsw_pano2vr_player_version'] ),
				'object2vr_player_version' => sanitize_text_field( $_POST['ggsw_object2vr_player_version'] )
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
			<?php screen_icon(); ?>
            <h2><?php _e( 'Garden Gnome Package' ); ?></h2>
            <form action="" method="post">
                <input name="nonce" type="hidden" id="nonce" value="<?php echo wp_create_nonce( 'ggsw_options' ); ?>">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e( 'Default Player Size', 'ggpkg' ) ?></th>
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
                            <label for="ggsw_player_start_preview"><?php _e( 'Start player as preview image', 'ggpkg' ); ?></label>
                        </td>
                        <td>
                            <input name="ggsw_player_start_preview" type="checkbox"
							       <?php if ( $this->options['start_preview'] ) : ?>checked<?php endif; ?> />
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
                                            ?>
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
		$valid['pano2vr_player_version']   = strval( $input['pano2vr_player_version'] );
		$valid['object2vr_player_version'] = strval( $input['object2vr_player_version'] );

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

	public function add_filetypes( $existing_mimes = array() ) {
		// add ggpkg extension to the array
		$existing_mimes['ggpkg'] = 'image/ggsw-package';
		// old type, does not all preview images everywhere
		//	$existing_mimes['ggpkg'] = 'application/ggsw-package';
		return $existing_mimes;
	}

	public function add_allow_upload_extension_exception( $data, $file, $filename ) {
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
		if ( $this->attachment_is_package( $attachmentID ) ) {
			$attachment = get_attached_file( $attachmentID );
			if ( $attachment ) {
				$path_parts   = pathinfo( $attachment );
				$filename     = str_replace( '-', '_', $path_parts['filename'] );
				$filename     = str_replace( ' ', '_', $filename );
				$extract_path = $path_parts['dirname'] . "/" . $filename;
				if ( is_numeric( substr( $filename, 0, 1 ) ) ) {
					$filename = '_' . $filename;
				}
				if ( ! mkdir( $extract_path, 0777 ) ) {
					$this->error_log( "mkdir failed!" );
				}
				$zip_file = new ZipArchive();
				if ( $zip_file->open( $attachment ) == true ) {
					if ( $zip_file->extractTo( $extract_path ) == false ) {
						$this->error_log( "Error extracting " . $zip_file );
					} else {
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
										mkdir( plugin_dir_path( __FILE__ ) . $sw . "_player" );
									}
									$dest_dir = plugin_dir_path( __FILE__ ) . $sw . "_player/" . $player_version;
									if ( ! file_exists( $dest_dir ) ) {
										if ( mkdir( $dest_dir ) ) {
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
				}
			}
		}
	}

	/***********************************************************************
	 * Activation / Deactivation
	 ***********************************************************************/

	public function error_log( $message ) {
		$timestamp        = date( 'd/m/Y H:i:s' );
		$ggsw_plugin_path = plugin_dir_path( __FILE__ );
		$ggsw_log_file    = $ggsw_plugin_path . 'error.log';

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
			$url = $attributes['url'];
			$url = str_replace( '/index.html', '/', $url );
			if ( substr( $url, - 1 ) != '/' ) {
				$url = $url . '/';
			}
			if ( parse_url( $url, PHP_URL_SCHEME ) == '' ) {
				$url = home_url( $url );
			}
			$package->set_from_url( $url );
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
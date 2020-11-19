<?php


class GGPackage {

	private $attachment_id;
	private $attachment_url;
	private $attachment_file;
	private $remote_url;
	private $local_url;
	private $is_pano = false;
	private $is_object = false;
	private $player_file = "";
	private $skin_file = "";
	private $translations_file = "";
	private $xml_file = "";
	private $preview_file = "";
	public $use_preview = false;
	public $preview_only = false;
	public $use_async = false;
	public $show_play_button = true;
	public $start_node = "";
	public $start_view = "";
	public $width = "500px";
	public $height = "500px";
	public $pano_player_version = "package";
	public $object_player_version = "package";
	private $json;
	private static $code_uid = 0;
	private $viewer;

	public function __construct( $viewer = null, $attachmentID = null ) {
		$this->viewer = $viewer;
		if ( isset( $viewer ) && isset( $viewer->options ) ) {
			$options = $viewer->options;
			if ( isset( $options['width'] ) ) {
				$this->width = $options['width'];
			}
			if ( isset( $options['height'] ) ) {
				$this->height = $options['height'];
			}
			$this->use_preview           = $options['start_preview'] === 'on';
			$this->pano_player_version   = $options['pano2vr_player_version'];
			$this->object_player_version = $options['object2vr_player_version'];
		}
		if ( isset( $attachmentID ) ) {
			$this->from_attachment( $attachmentID );
		}
	}

	public function from_attachment( $id ) {
		$this->attachment_id   = $id;
		$attachment_url        = wp_get_attachment_url( $this->attachment_id );
		$this->attachment_file = get_attached_file( $id );
		$filename              = strrchr( $attachment_url, '/' );
		$filename              = str_replace( '-', '_', $filename );
		$attachment_url        = substr( $attachment_url, 0, strrpos( $attachment_url, '/' ) ) . $filename;
		$this->local_url       = substr( $attachment_url, 0, strrpos( $attachment_url, '.' ) );
		// remove domain?
		// $this->local_url       = $this->url_from_local($this->local_url);
		$this->attachment_url = $attachment_url;
		if ( substr( $this->local_url, - 1 ) != "/" ) {
			$this->local_url .= "/";
		}
		if ( $this->file_in_package( "gginfo.json" ) ) {
			if ( ( $json_content = file_get_contents( $this->abs_folder() . "/gginfo.json" ) ) != false ) {
				$json_content = utf8_encode( $json_content );
				$this->parse_gginfo_json( $json_content );
			}
		} else {
			if ( $this->file_in_package( "pano2vr_player.js" ) ) {
				$this->is_pano     = true;
				$this->player_file = "pano2vr_player.js";
				$this->xml_file    = "pano.xml";
			}
			if ( $this->file_in_package( "object2vr_player.js" ) ) {
				$this->is_object   = true;
				$this->player_file = "object2vr_player.js";
				$this->xml_file    = "object.xml";
			}
			if ( $this->file_in_package( "skin.js" ) ) {
				$this->skin_file = "skin.js";
			}
			if ( $this->file_in_package( "translations.js" ) ) {
				$this->translations_file = "translations.js";
			}
			if ( $this->file_in_package( "preview.jpg" ) ) {
				$this->preview_file = "preview.jpg";
			} else {
				$this->preview_file = "empty.jpg";
			}
		}
	}

	public function set_from_url( $url ) {
		$this->attachment_id   = "r";
		$this->remote_url      = $url;
		$this->attachment_url  = "";
		$this->attachment_file = "";
		$remote_json_file      = $url . "gginfo.json";
		$transient             = 'ggexurl_' . md5( $url );
		$json_response         = get_transient( $transient );

		if ( false === $json_response ) {
			// Transient expired, refresh the data
			$json_response = wp_remote_get( $remote_json_file, [ 'sslverify' => false ] );
			$code          = wp_remote_retrieve_response_code( $json_response );
			set_transient( $transient, $json_response, ( $code == "200" ) ? 3600 : 60 );
		} else {
			$code = wp_remote_retrieve_response_code( $json_response );
		}
		$json_content = wp_remote_retrieve_body( $json_response );
		if ( $code == "200" ) {
			$this->parse_gginfo_json( $json_content );
		}
	}

	public function file_in_package( $file ) {
		$extract_path_local = $this->abs_folder();
		if ( file_exists( $extract_path_local . "/" . $file ) ) {
			return true;
		} else {
			return false;
		}
	}

	public function url_from_local( $url ) {
		$url_from_local = $url;
		$url_parts      = parse_url( $url );
		// 	if ($url_parts['host'] == $_SERVER['SERVER_NAME'])
		// 	{
		$url_from_local = $url_parts['path'];

		// 	}
		return $url_from_local;
	}

	public function folder() {
		$path_parts = pathinfo( $this->attachment_file );
		$filename   = str_replace( '-', '_', $path_parts['filename'] );
		$filename   = str_replace( ' ', '_', $filename );

		return $filename;
	}

	public function unique_player() {
		if ( $this->remote_url ) {
			return "";
		} else {
			$filename = $this->folder();
			if ( is_numeric( substr( $filename, 0, 1 ) ) ) {
				$filename = '_' . $filename;
			}

			return $filename;
		}
	}

	public function abs_folder() {
		$path_parts = pathinfo( $this->attachment_file );

		return $path_parts['dirname'] . "/" . $this->folder();
	}

	public function file_url( $file ) {
		$attachmentURL = $this->attachment_url;
		$attachmentURL = substr( $attachmentURL, 0, strrpos( $attachmentURL, '/' ) ) . $this->filename;
		$extract_path  = substr( $attachmentURL, 0, strrpos( $attachmentURL, '.' ) );

		return $extract_path . "/" . $file;
	}

	public function to_url( $file ) {
		if ( $this->remote_url ) {
			return $this->remote_url . $file;
		} else {
			return $this->local_url . $file;
		}
	}

	public function get_preview_image_size() {
		$attachmentID = $this->attachment_id;
		$w            = get_post_meta( $attachmentID, "ggsw_width", true );
		$h            = get_post_meta( $attachmentID, "ggsw_height", true );
		if ( ! ( ( $w ) && ( $h ) ) ) {
			$previewFile = $this->abs_folder( $attachmentID ) . "/preview.jpg";
			if ( file_exists( $previewFile ) ) {
				$previewSize = getimagesize( $previewFile );
				$w           = $previewSize[0];
				$h           = $previewSize[1];
				update_post_meta( $attachmentID, "ggsw_width", $w );
				update_post_meta( $attachmentID, "ggsw_height", $h );
			}
		}

		return array( $w, $h );
	}

	public function parse_gginfo_json( $json_content ) {
		$json = json_decode( $json_content );
		if ( ! $json ) {
			return;
		}
		$this->json = $json;
		$json_type  = $json->{'type'};
		if ( isset( $json->type ) ) {
			// only newer player version have the async function, but they where introduced at the same time as as the json file in the package
			$this->use_async = true;
			$this->is_pano   = ( $json_type == 'panorama' );
			$this->is_object = ( $json_type == 'object' );
			if ( $this->is_pano ) {
				$this->xml_file = "pano.xml";
			}
			if ( $this->is_object ) {
				$this->xml_file = "object.xml";
			}
		}
		if ( isset( $json->player ) && isset( $json->player->js ) ) {
			$this->player_file = $json->player->js;
		}
		if ( isset( $json->skin ) && isset( $json->skin->js ) ) {
			$this->skin_file = $json->skin->js;
		}
		if ( isset( $json->translations ) && isset( $json->translations->js ) ) {
			$this->translations_file = $json->translations->js;
		}
		if ( isset( $json->preview ) ) {
			if ( isset( $json->preview->img ) ) {
				$this->preview_file = $json->preview->img;
			} else {
//				$attributes['start_preview'] = 'false';
			}
		} else {
//			$attributes['start_preview'] = 'false';
		}

	}

	public function get_html_code( $postID ) {
		$ID = "_" . GGPackage::$code_uid . "_" . $postID;
		GGPackage::$code_uid ++;

		if ( $this->json ) {
			$json_externals = $this->json->{'external'};
			if ( isset( $json_externals ) ) {
				$js_files = $json_externals->{'js'};
				if ( isset( $js_files ) ) {
					$index = 0;
					foreach ( $js_files as $js_file ) {
						if ( substr( $js_file, 0, 4 ) === "http" ) {
							wp_enqueue_script( 'js_' . $this->attachment_id . '_' . $index, $js_file );
						} else {
							if ( ( substr( $js_file, 0, 6 ) == 'webvr/' ) ||
							     ( substr( $js_file, 0, 6 ) == 'webxr/' ) ) { // only load the webvr scripts from one source
								wp_enqueue_script( 'js_g_' . $js_file, $this->to_url( $js_file ) );
							} else {
								wp_enqueue_script( 'js_' . $this->attachment_id . '_' . $index, $this->to_url( $js_file ) );
							}
						}
						$index ++;
					}
				}
				$css_files = $json_externals->{'css'};
				if ( isset( $css_files ) ) {
					$index = 0;
					foreach ( $css_files as $css_file ) {
						if ( substr( $css_file, 0, 4 ) === "http" ) {
							wp_enqueue_style( 'css_' . $this->attachment_id . '_' . $index, $css_file );
						} else {
							wp_enqueue_style( 'css_' . $this->attachment_id . '_' . $index, $this->to_url( $css_file ) );
						}
						$index ++;
					}
				}
			}
		}

		$width  = $this->width;
		$height = $this->height;
		if ( is_numeric( $width ) ) {
			$width = $width . "px";
		}
		if ( is_numeric( $height ) ) {
			$height = $height . "px";
		}
		$html = "<div id='ggpkg_container" . $ID . "' style='width:" . $width . "; height:" . $height . "; position: relative;'>\n";
		if ( $this->use_preview ) {
			$html .= "<div style='width:100%; height:100%; overflow: hidden; position:relative;'>\n";
			if ( $this->preview_file ) {
				$html .= "<img src='" . $this->to_url( $this->preview_file ) . "' alt='' onclick='startPlayer" . $ID . "();' style='min-width:100%; max-width: 10000px; min-height: 100%; max-height: 10000px; position: absolute;'>\n";
			}
			if ( $this->show_play_button ) {
				$html .= "<img src='" . $this->url_from_local( plugin_dir_url( __FILE__ ) . "play.png" ) . "' alt='' onclick='startPlayer" . $ID . "();' style='width: 180px; height: 180px; display: block;";
			}
			if ( substr( $width, - 1 ) == '%' ) {
				if ( substr( $height, - 1 ) == '%' ) {
					$html .= "top: 50%; margin-top: -90px; left: 50%; margin-left: -90px;";
				} else {
					$html .= "top: " . strval( $height / 2 - 90 ) . "px; left: 50%; margin-left: -90px;";
				}
			} else {
				if ( substr( $height, - 1 ) == '%' ) {
					$html .= "top: 50%; margin-top: -90px; left: " . strval( $width / 2 - 90 ) . "px;";
				} else {
					$html .= "top: " . strval( $height / 2 - 90 ) . "px; left: " . strval( $width / 2 - 90 ) . "px;";
				}
			}
			$html .= "box-shadow: none; position: absolute;'>\n";
			$html .= "</div>\n";
		} else {
			$html .= "Loading...\n";
		}
		$html .= "</div>\n";
		if ( ( $this->remote_url == "" ) && ( $this->is_pano ) && ( $this->pano_player_version != "package" ) // Remote packages should load the remote player to avoid CORS trouble.
		     && ( file_exists( plugin_dir_path( dirname( __FILE__ ) ) . "pano2vr_player/" . $this->pano_player_version . "/pano2vr_player.js" ) ) ) {
			wp_enqueue_script( 'js_ggsw_pano2vr_player', plugin_dir_url( dirname( __FILE__ ) ) . "pano2vr_player/" . $this->pano_player_version . "/pano2vr_player.js" );

		} elseif ( ( $this->remote_url == "" ) && ( $this->is_object ) && ( $this->object_player_version != "package" )
		           && ( file_exists( plugin_dir_path( dirname( __FILE__ ) ) . "object2vr_player/" . $this->object_player_version . "/object2vr_player.js" ) ) ) {
			wp_enqueue_script( 'js_ggsw_object2vr_player', plugin_dir_url( dirname( __FILE__ ) ) . "object2vr_player/" . $this->object_player_version . "/object2vr_player.js" );
		} else {
			$html .= "<script type='text/javascript' src='" . $this->to_url( $this->player_file ) . "'></script>\n";
		}
		if ( $this->translations_file ) {
			$html .= "<script type='text/javascript' src='" . $this->to_url( $this->translations_file ) . "'></script>\n";
		}
		if ( $this->skin_file ) {
			$html .= "<script type='text/javascript' src='" . $this->to_url( $this->skin_file ) . "'></script>\n";
		}
		$html .= "<script type='text/javascript'>\n";

		$uplayer = $this->unique_player();
		if ( $this->is_object ) {
			$jsObj         = "obj" . $ID;
			$jsClassPrefix = "object2vr";
		} else {
			$jsObj         = "pano" . $ID;
			$jsClassPrefix = "pano2vr";
		}
		if ( $uplayer != "" ) {
			$play_js = "if  (typeof " . $uplayer . "_" . $jsClassPrefix . "Player != 'undefined') {\n";
			$play_js .= "\t" . $jsObj . "=new " . $uplayer . "_" . $jsClassPrefix . "Player('ggpkg_container" . $ID . "');\n";
			$play_js .= "} else {\n";
			$play_js .= "\t" . $jsObj . "=new " . $jsClassPrefix . "Player('ggpkg_container" . $ID . "');\n";
			$play_js .= "}\n";
		} else {
			$play_js = $jsObj . "=new " . $jsClassPrefix . "Player('ggpkg_container" . $ID . "');\n";
		}
		if ( $this->start_node != "" ) {
			$play_js .= $jsObj . ".startNode = '" . $this->start_node . "';\n";
		}
		if ( $this->start_view != "" ) {
			$view_params = explode( "/", $this->start_view, 4 );
			if ( sizeof( $view_params ) >= 3 ) {
				$play_js .= $jsObj . ".startView = {\n";
				$play_js .= "   pan :" . floatval( trim( $view_params[0] ) ) . ",\n";
				$play_js .= "   tilt :" . floatval( trim( $view_params[1] ) ) . ",\n";
				$play_js .= "   fov :" . floatval( trim( $view_params[2] ) ) . ",\n";
				if ( sizeof( $view_params ) > 3 ) {
					$play_js .= "   projection :" . intval( trim( $view_params[3] ) ) . ",\n";
				}
				$play_js .= "};\n";
			}
		}
		if ( $this->skin_file ) {
			if ( $uplayer != "" ) {
				$play_js .= "if  (typeof " . $uplayer . "_" . $jsClassPrefix . "Skin != 'undefined') {\n";
				$play_js .= "\tskin" . $ID . "=new " . $uplayer . "_" . $jsClassPrefix . "Skin(" . $jsObj . ", '" . $this->to_url( "" ) . "');\n";
				$play_js .= "} else {\n";
				$play_js .= "\tskin" . $ID . "=new " . $jsClassPrefix . "Skin(" . $jsObj . ", '" . $this->to_url( "" ) . "');\n";
				$play_js .= "}\n";
			} else {
				$play_js .= "skin" . $ID . "=new " . $jsClassPrefix . "Skin(" . $jsObj . ", '" . $this->to_url( "" ) . "');\n";
			}
		}
		if ( $this->use_async ) {
			$play_js .= $jsObj . ".readConfigUrlAsync('" . $this->to_url( $this->xml_file ) . "');\n";
		} else {
			$play_js .= $jsObj . ".readConfigUrl('" . $this->to_url( $this->xml_file ) . "');\n";
		}
		$play_js .= "var contentOverflow = document.getElementById('content') ? document.getElementById('content').style.overflow : '';\n";
		$play_js .= $jsObj . ".addListener('fullscreenenter', function() {\n";
		$play_js .= "	if (document.getElementById('content')) {\n";
		$play_js .= "		document.getElementById('content').style.overflow = 'visible';\n";
		$play_js .= "	}\n";
		$play_js .= "});\n";
		$play_js .= $jsObj . ".addListener('fullscreenexit', function() {\n";
		$play_js .= "	if (document.getElementById('content')) {\n";
		$play_js .= "		document.getElementById('content').style.overflow = contentOverflow;\n";
		$play_js .= "	}\n";
		$play_js .= "});\n";
		// make the code look nice
//		$play_js="\t".str_replace("\n","\n\t",$play_js);
		if ( $this->use_preview ) {
			if ( ! $this->preview_only ) {
				$html .= "function startPlayer" . $ID . "() {\n";
				$html .= $play_js;
				$html .= "}\n";
			}
		} else {
			$html .= "window.addEventListener('load',function() {\n" . $play_js . "\n});\n";
		}
		$html .= "</script>\n";

		return $html;
	}
}

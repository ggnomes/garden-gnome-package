<?php

use Elementor\Controls_Manager;
use Elementor\Plugin;
use Elementor\Utils;
use Elementor\Widget_Base;

class Elementor_GGPKG_Widget extends Widget_Base {

	public function get_name() {
		return 'ggpkg';
	}

	public function get_title() {
		return __( 'Garden Gnome Package', 'ggpkg' );
	}

	public function get_icon() {
		return 'eicon-image';;
	}

	public function get_categories() {
		return [ 'general' ];
	}

	protected function _register_controls() {

		$this->start_controls_section(
			'content_section',
			[
				'label' => __( 'Content', 'ggpkg' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);
		$this->add_responsive_control(
			'height',
			[
				'label'   => __( 'Height (px)', 'ggpkg' ),
				'type'    => Controls_Manager::SLIDER,
				'size_units' => ['px'],
				'range'   => [
				   'px' => [
					   'min' => 50,
					   'max' => 2000,
					   'step' => 10,
				   ],
				],
				'default' => [
				   'size' => 400,
				],
				'selectors' => [
				   '{{WRAPPER}} .ggpkg-elementor-widget' => 'height:{{SIZE}}px;',
				],
			]
		);
		$this->add_control(
			'start_preview',
			[
				'label'        => __( 'Start with Preview', 'ggpkg' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'your-plugin' ),
				'label_off'    => __( 'No', 'your-plugin' ),
				'return_value' => 'yes',
				'default'      => 'no',
			]
		);
		$this->add_control(
			'image',
			[
				'label'   => __( 'Choose Garden Gnome Package', 'ggpkg' ),
				'type'    => Controls_Manager::MEDIA,
				'default' => [
					'url' => Utils::get_placeholder_image_src(),
				],
			]
		);
		$this->end_controls_section();

	}

	protected function render() {

		global $post;
		global $ggPackageViewer;

		$settings      = $this->get_settings_for_display();
		$id            = $settings['image']['id'];
		$start_preview = $settings['start_preview'] == 'yes';

		echo '<div class="ggpkg-elementor-widget" style="width:100%;">';

		if ( $id > 0 ) {
			if ( ! $ggPackageViewer->attachment_is_package( $id ) ) {
				echo "Not a valid Garden Gnome Package";
			} else {
				$package = new GGPackage( $this );
				$package->from_attachment( $id );
				if ( Plugin::$instance->editor->is_edit_mode() ) {
					$package->use_preview      = true;
					$package->show_play_button = $start_preview;
					$package->preview_only     = true;
				} else {
					$package->use_preview = $start_preview;
				}
				$package->width  = "100%";
				$package->height  = "100%";

				echo $package->get_html_code( $post->ID );
			}
		} else {
			echo "no package selected";
		}
		echo '</div>';

	}

}
<?php

class Test_GGPKG_Unpack extends WP_UnitTestCase {
	private $admin_user_id;

	public function set_up(): void {
		parent::set_up();
		$this->admin_user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );
	}

	private function create_test_attachment_from_zip( $filename, $files ) {
		$upload = wp_upload_dir();
		$path   = trailingslashit( $upload['path'] ) . $filename;

		$zip = new ZipArchive();
		$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		foreach ( $files as $entry_name => $entry_content ) {
			$zip->addFromString( $entry_name, $entry_content );
		}
		$zip->close();

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/ggsw-package',
				'post_title'     => 'Test GGPKG',
				'post_status'    => 'inherit',
			),
			$path
		);
		update_attached_file( $attachment_id, $path );

		return $attachment_id;
	}

	public function test_unpack_invalid_extension_sets_warning_status() {
		global $ggPackageViewer;

		$attachment_id = $this->create_test_attachment_from_zip(
			'test-warning.ggpkg',
			array(
				'gginfo.json' => '{"type":"panorama","player":{"js":"pano2vr_player.js"},"configuration":"pano.xml"}',
				'pano2vr_player.js' => 'console.log("ok")',
				'pano.xml' => '<xml/>',
				'bad.php' => '<?php echo 1;',
			)
		);

		$ggPackageViewer->unzip_package( $attachment_id );

		$status = get_post_meta( $attachment_id, 'ggpkg_unpack_status', true );
		$this->assertIsArray( $status );
		$this->assertEquals( 'warning', $status['status'] );

		$errors = get_post_meta( $attachment_id, 'ggpkg_unpack_errors', true );
		$this->assertIsArray( $errors );
		$this->assertNotEmpty( $errors );
	}

	public function test_media_notice_queue_renders_warning_notice() {
		global $ggPackageViewer;

		$ggPackageViewer->queue_unpack_notice( 'Queued warning', 'warning' );
		set_current_screen( 'upload' );

		ob_start();
		$ggPackageViewer->show_unpack_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( 'Queued warning', $output );
	}

	public function test_media_row_actions_contains_reextract() {
		global $ggPackageViewer;

		$attachment_id = $this->create_test_attachment_from_zip(
			'test-row-action.ggpkg',
			array(
				'gginfo.json' => '{}',
			)
		);
		$post = get_post( $attachment_id );

		$actions = $ggPackageViewer->media_row_actions( array(), $post );
		$this->assertArrayHasKey( 'ggpkg_reextract', $actions );
	}
}

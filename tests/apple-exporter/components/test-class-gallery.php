<?php
/**
 * Publish to Apple News tests: Gallery_Test class
 *
 * @package Apple_News
 * @subpackage Tests
 */

use \Apple_Exporter\Components\Gallery;

/**
 * A class to test the behavior of the
 * Apple_Exporter\Components\Gallery class.
 *
 * @package Apple_News
 * @subpackage Tests
 */
class Gallery_Test extends Component_TestCase {

	/**
	 * Test content representing the output of a complex gallery.
	 *
	 * @access private
	 * @var string
	 */
	private $_complex_html = <<<HTML
<div id="gallery-1" class="gallery galleryid-0 gallery-columns-3 gallery-size-full">
	<figure class="gallery-item">
		<div class="gallery-icon landscape">
			<img width="721" height="643" src="http://someurl.com/filename-1.jpg" class="attachment-full size-full" alt="Alt Text 1" aria-describedby="gallery-1-52" srcset="http://someurl.com/filename-1.jpg 721w, http://someurl.com/filename-1-300x268.jpg 300w" sizes="100vw"/>
		</div>
		<figcaption class="wp-caption-text gallery-caption" id="gallery-1-52">Caption 1</figcaption>
	</figure>
	<figure class="gallery-item">
		<div class="gallery-icon portrait">
			<img width="700" height="766" src="http://someurl.com/another-filename-2.jpg" class="attachment-full size-full" alt="Alt Text 2" aria-describedby="gallery-1-53" srcset="http://someurl.com/another-filename-2.jpg 700w, http://someurl.com/another-filename-2-274x300.jpg 274w" sizes="100vw"/>
		</div>
		<figcaption class="wp-caption-text gallery-caption" id="gallery-1-53">Caption 2</figcaption>
	</figure>
</div>
HTML;

	/**
	 * Test content representing the output of a simple gallery.
	 *
	 * @access private
	 * @var string
	 */
	private $_simple_html = <<<HTML
<div class="gallery">
	<img src="http://someurl.com/filename-1.jpg" alt="Example" />
	<img src="http://someurl.com/another-filename-2.jpg" alt="Example" />
</div>
HTML;

	/**
	 * A filter function to modify the layout in the generated JSON.
	 *
	 * @param array $json The JSON array to modify.
	 *
	 * @access public
	 * @return array The modified JSON.
	 */
	public function filter_apple_news_gallery_json( $json ) {
		$json['layout'] = 'fancy-layout';

		return $json;
	}

	/**
	 * Test the apple_news_gallery_json filter.
	 *
	 * @access public
	 */
	public function testFilter() {

		// Setup.
		$component = $this->setup_component( $this->_simple_html );

		// Add the filter and set a custom layout.
		add_filter(
			'apple_news_gallery_json',
			array( $this, 'filter_apple_news_gallery_json' )
		);

		// Ensure the filter properly modified the layout.
		$this->assertEquals(
			array(
				'role' => 'gallery',
				'items' => array(
					array(
						'URL' => 'bundle://filename-1.jpg',
						'accessibilityCaption' => 'Example',
					),
					array(
						'URL' => 'bundle://another-filename-2.jpg',
						'accessibilityCaption' => 'Example',
					),
				),
				'layout' => 'fancy-layout',
			),
			$component->to_array()
		);

		// Teardown.
		remove_filter(
			'apple_news_gallery_json',
			array( $this, 'filter_apple_news_gallery_json' )
		);
	}

	/**
	 * Ensures that the component generates the proper JSON for a simple gallery.
	 *
	 * @access public
	 */
	public function testGeneratedJSON() {

		// Setup.
		$component = $this->setup_component( $this->_simple_html );

		// Test for valid JSON.
		$this->assertEquals(
			array(
				'role' => 'gallery',
				'items' => array(
					array(
						'URL' => 'bundle://filename-1.jpg',
						'accessibilityCaption' => 'Example',
					),
					array(
						'URL' => 'bundle://another-filename-2.jpg',
						'accessibilityCaption' => 'Example',
					),
				),
				'layout' => 'gallery-layout',
			),
			$component->to_array()
		);
	}

	/**
	 * Ensures that the component generates the proper JSON for a complex gallery.
	 *
	 * @access public
	 */
	public function testGeneratedJSONComplex() {

		// Setup.
		$component = $this->setup_component( $this->_complex_html );

		// Test for valid JSON.
		$this->assertEquals(
			array(
				'role' => 'gallery',
				'items' => array(
					array(
						'URL' => 'bundle://filename-1.jpg',
						'accessibilityCaption' => 'Alt Text 1',
						'caption' => array(
							'text' => 'Caption 1',
						),
					),
					array(
						'URL' => 'bundle://another-filename-2.jpg',
						'accessibilityCaption' => 'Alt Text 2',
						'caption' => array(
							'text' => 'Caption 2',
						),
					),
				),
				'layout' => 'gallery-layout',
			),
			$component->to_array()
		);
	}

	/**
	 * Tests the functionality of the `use_remote_images` setting.
	 *
	 * @access public
	 */
	public function testGeneratedJSONRemoteImages() {

		// Setup.
		$this->settings->set( 'use_remote_images', 'yes' );
		$this->prophecized_workspace->bundle_source(
			'filename-1.jpg',
			'http://someurl.com/filename-1.jpg'
		)->shouldNotBeCalled();
		$this->prophecized_workspace->bundle_source(
			'another-filename-2.jpg',
			'http://someurl.com/another-filename-2.jpg'
		)->shouldNotBeCalled();
		$component = new Gallery(
			$this->_simple_html,
			$this->prophecized_workspace->reveal(),
			$this->settings,
			$this->styles,
			$this->layouts
		);

		// Ensure that the URL parameters are using remote images.
		$this->assertEquals(
			array(
				'role' => 'gallery',
				'items' => array(
					array(
						'URL' => 'http://someurl.com/filename-1.jpg',
						'accessibilityCaption' => 'Example',
					),
					array(
						'URL' => 'http://someurl.com/another-filename-2.jpg',
						'accessibilityCaption' => 'Example',
					),
				),
				'layout' => 'gallery-layout',
			),
			$component->to_array()
		);
	}

	/**
	 * Given HTML content, sets up a workspace and a Gallery component.
	 *
	 * @param string $content The HTML content to feed into the component.
	 *
	 * @access private
	 * @return Gallery The Gallery component constructed from the content.
	 */
	private function setup_component( $content ) {

		// Set up the workspace.
		$this->settings->set( 'use_remote_images', 'no' );
		$this->prophecized_workspace->bundle_source(
			'filename-1.jpg',
			'http://someurl.com/filename-1.jpg'
		)->shouldBeCalled();
		$this->prophecized_workspace->bundle_source(
			'another-filename-2.jpg',
			'http://someurl.com/another-filename-2.jpg'
		)->shouldBeCalled();

		return new Gallery(
			$content,
			$this->prophecized_workspace->reveal(),
			$this->settings,
			$this->styles,
			$this->layouts
		);
	}
}

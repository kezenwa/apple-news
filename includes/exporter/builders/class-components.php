<?php
namespace Exporter\Builders;

use \Exporter\Component_Factory as Component_Factory;

/**
 * @since 0.4.0
 */
class Components {

	/**
	 * The content object to be exported.
	 *
	 * @var  Exporter_Content
	 * @since 0.2.0
	 */
	private $content;

	function __construct( $content ) {
		$this->content = $content;
	}

	public function to_array() {
		return $this->build_components();
	}

	/**
	 * Builds an array with all the components of this WordPress content.
	 */
	private function build_components() {
		$components = $this->meta_components();
		foreach ( $this->split_into_components() as $component ) {
			$components[] = $component->to_array();
		}
		return $components;
	}

	/**
	 * Meta components are those which were not created from HTML, instead, they
	 * contain only text. This text is normally created from the article
	 * metadata.
	 */
	private function meta_components() {
		$components = array();

		// The content's cover is optional. In WordPress, it's a post's thumbnail
		// or featured image.
		if ( $this->content_cover() ) {
			$components[] = $this->get_component_from_shortname( 'cover', $this->content_cover() )->to_array();
		}

		// Add title
		$components[] = $this->get_component_from_shortname( 'title', $this->content_title() )->to_array();

		// Add title
		if ( $this->content_byline() ) {
			$components[] = $this->get_component_from_shortname( 'byline', $this->content_byline() )->to_array();
		}

		return $components;
	}


	/**
	 * Anchor components that are anchorable
	 */
	private function anchor_components( $components ) {
		$len = count( $components );

		for ( $i = 0; $i < $len; $i++ ) {
			$component = $components[ $i ];

			if ( ! $component->is_anchorable ) {
				continue;
			}

			// Anchor this component to previous component
			$uid             = uniqid();
			$other_component = $components[ $i - 1 ];
			$other_component->set_json( 'identifier', 'component-' . $uid );
			$component->set_json( 'anchor', array(
				'targetComponentIdentifier' => 'component-' . $uid,
				'targetAnchorPosition'      => 'center',
				'rangeStart' => 0,
				'rangeLength' => 1,
			) );
		}

		return $components;
	}

	/**
	 * Split components from the source WordPress content.
	 */
	private function split_into_components() {
		// Pullquote check
		$pullquote          = $this->content_setting( 'pullquote' );
		$pullquote_position = $this->content_setting( 'pullquote_position' );

		// Loop though the first-level nodes of the body element. Components
		// might include child-components, like an Cover and Image.
		$result   = array();
		$position = 0;
		foreach ( $this->content_nodes() as $node ) {
			$components = $this->get_components_from_node( $node );

			if ( !empty( $pullquote ) && $pullquote_position > 0 ) {
				// Do we have to insert a pullquote into the article?
				// If so, iterate all components, and add when the position is reached.
				foreach ( $components as $component ) {
					$position++;
					$result[] = $component;

					if ( $position == $pullquote_position ) {
						$pullquote_component = $this->get_component_from_shortname( 'blockquote', "<blockquote>$pullquote</blockquote>" );
						$pullquote_component->set_anchorable( true );
						$result[] = $pullquote_component;

						$pullquote_position = 0;
					}
				}
			} else {
				// No pullquote check needed, just add components into result.
				$result = array_merge( $result, $components );
			}
		}

		return $this->anchor_components( $result );
	}

	private function get_component_from_shortname( $shortname, $html ) {
		return Component_Factory::get_component( $shortname, $html );
	}

	private function get_components_from_node( $node ) {
		return Component_Factory::get_components_from_node( $node );
	}

	// Isolate dependencies
	// ------------------------------------------------------------------------

	private function content_id() {
		return $this->content->id();
	}

	private function content_title() {
		return $this->content->title() ?: 'Untitled Article';
	}

	private function content_text() {
		return $this->content->content();
	}

	private function content_intro() {
		return $this->content->intro();
	}

	private function content_cover() {
		return $this->content->cover();
	}

	private function content_setting( $name ) {
		return $this->content->get_setting( $name );
	}

	private function content_byline() {
		return $this->content->byline();
	}

	private function content_nodes() {
		return $this->content->nodes();
	}


}

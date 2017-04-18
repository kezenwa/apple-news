<?php
namespace Apple_Push_API;

use Apple_Push_API\Request\Request_Exception as Request_Exception;

class MIME_Builder {

	/**
	 * Boundary to separate bundle items in the MIME request.
	 *
	 * @var string
	 * @access private
	 */
	private $boundary;

	/**
	 * Holds a debug version of the MIME request content, minus binary data.
	 *
	 * @var string
	 * @access private
	 */
	private $debug_content;

	/**
	 * End of line format for MIME requests
	 *
	 * @var string
	 * @access private
	 */
	private $eol = "\r\n";

	/**
	 * Valid MIME types for Apple News bundles.
	 *
	 * @var array
	 * @access private
	 */
	private static $valid_mime_types = array (
		'image/jpeg',
		'image/png',
		'image/gif',
		'application/font-sfnt',
		'application/x-font-truetype',
		'application/font-truetype',
		'application/vnd.ms-opentype',
		'application/x-font-opentype',
		'application/font-opentype',
		'application/octet-stream',
	);

	/**
	 * Constructor.
	 */
	function __construct() {
		$this->boundary = md5( microtime() );
	}

	/**
	 * Get the boundary.
	 *
	 * @return string
	 * @access public
	 */
	public function boundary() {
		return $this->boundary;
	}

	/**
	 * Add metadata to the MIME request.
	 *
	 * @param mixed $meta
	 * @return string
	 * @access public
	 */
	public function add_metadata( $meta ) {
		$attachment  = '--' . $this->boundary . $this->eol;
		$attachment .= 'Content-Type: application/json' . $this->eol;
		$attachment .= 'Content-Disposition: form-data; name=metadata' . $this->eol . $this->eol;
		$attachment .= wp_json_encode( $meta ) . $this->eol;

		$this->debug_content .= $attachment;

		return $attachment;
	}

	/**
	 * Add a JSON string to the MIME request.
	 *
	 * @param string $name
	 * @param string $filename
	 * @param string $content
	 * @return string
	 * @access public
	 */
	public function add_json_string( $name, $filename, $content ) {
		return $this->build_attachment(
			$name,
			$filename,
			$content,
			'application/json',
			strlen( $content )
		);
	}

	/**
	 * Add file contents to the MIME request.
	 *
	 * @param string $filepath
	 * @param string $name
	 * @return string
	 * @access public
	 */
	public function add_content_from_file( $filepath, $name = null ) {
		// Get the contents of the file.
		$contents = '';

		// Try wp_remote_get first.
		if ( defined( 'WPCOM_IS_VIP_ENV' ) && WPCOM_IS_VIP_ENV ) {
			$request = vip_safe_wp_remote_get( $filepath );
		} else {
			$request = wp_remote_get( $filepath );
		}

		if ( is_wp_error( $request ) ) {
			// Try file_get_contents instead. This could be a local path.
			$contents = file_get_contents( $filepath );
		} else {
			$contents = wp_remote_retrieve_body( $request );
		}

		// Attempt to get the size
		$size = strlen( $contents );

		// If this fails for some reason, try alternate methods
		if ( empty( $size ) ) {
			if ( filter_var( $filepath, FILTER_VALIDATE_URL ) ) {
				$headers = get_headers( $filepath );
				foreach ( $headers as $header ) {
					if ( preg_match( '/Content-Length: ([0-9]+)/i', $header, $matches ) ) {
						$size = intval( $matches[1] );
					}
				}
			} else {
				// This will be the final catch for local files
				$size = filesize( $filepath );
			}
		}

		// If the name wasn't specified, build it from the filename
		$filename = \Apple_News::get_filename( $filepath );
		if ( empty( $name ) ) {
			$name = sanitize_key( $filename );
		}

		return $this->build_attachment(
			$name,
			$filename,
			$contents,
			$this->get_mime_type_for( $filepath ),
			$size
		);
	}

	/**
	 * Close a file added to the MIME request.
	 *
	 * @return string
	 * @access public
	 */
	public function close() {
		$close = '--' . $this->boundary . '--';
		$this->debug_content .= $close;
		return $close;
	}

	/**
	 * Build an attachment in the MIME request.
	 *
	 * @param string $name
	 * @param string $filename
	 * @param string $content
	 * @param string $mime_type
	 * @param int $size
	 * @return string
	 * @access private
	 */
	private function build_attachment( $name, $filename, $content, $mime_type, $size ) {
		// Ensure the file isn't empty
		if ( empty( $content ) ) {
			throw new Request_Exception( sprintf(
				__( 'The attachment %s could not be included in the request because it was empty.', 'apple-news' ),
				esc_html( $filename )
			) );
		}

		// Ensure a valid size was provided
		if ( 0 >= intval( $size ) ) {
			throw new Request_Exception( sprintf(
				__( 'The attachment %s could not be included in the request because its size was %s.', 'apple-news' ),
				esc_html( $filename ),
				esc_html( $size )
			) );
		}

		// Build the attachment
		$attachment  = '--' . $this->boundary . $this->eol;
		$attachment .= 'Content-Type: ' . $mime_type . $this->eol;
		$attachment .= 'Content-Disposition: form-data; name=' . $name . '; filename=' . $filename . '; size=' . $size . $this->eol . $this->eol;

		$this->debug_content .= $attachment;

		$attachment .= $content . $this->eol;

		if ( 'application/json' === $mime_type ) {
			$this->debug_content .= $content . $this->eol;
		} else {
			$this->debug_content .= "(binary contents of $filename)" . $this->eol;
		}

		return $attachment;
	}

	/**
	 * Get the MIME type for a file.
	 *
	 * @todo replace with the proper WordPress function.
	 * @param string $filepath
	 * @return string
	 * @access private
	 */
	private function get_mime_type_for( $filepath ) {
		// TODO: rethink this for better integration with WordPress
		return 'application/octet-stream';
	}

	/**
	 * Check if this file is a valid MIME type to be included in the bundle.
	 *
	 * @param string $type
	 * @return boolean
	 * @access private
	 */
	private function is_valid_mime_type( $type ) {
		return in_array( $type, self::$valid_mime_types, true );
	}

	/**
	 * Gets the debug version of the MIME content
	 *
	 * @param array $args
	 * @return string
	 * @access public
	 */
	public function get_debug_content( $args ) {
		$content = '';

		// Parse the header from the args and convert it into the format
		// that would actually be sent to the API.
		if ( ! empty( $args['headers'] ) && is_array( $args['headers'] ) ) {
			foreach ( $args['headers'] as $key => $value ) {
				$content .= sprintf(
					'%s: %s%s',
					$key,
					$value,
					$this->eol
				);
			}
		}

		$content .= $this->eol . $this->debug_content;

		return $content;
	}

}

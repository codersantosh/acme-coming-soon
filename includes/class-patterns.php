<?php // phpcs:ignore Class file names should be based on the class name with "class-" prepended.
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add design WordPress Gutenberg Block patterns.
 *
 * @link       https://addonspress.com/
 * @since      1.0.0
 *
 * @package    Acme_Coming_Soon
 * @subpackage Acme_Coming_Soon/patterns
 */

/**
 * Register the bundled block patterns and their category.
 *
 * @since      1.0.0
 * @package    Acme_Coming_Soon
 * @subpackage Acme_Coming_Soon/patterns
 * @author     codersantosh <codersantosh@gmail.com>
 */
class Acme_Coming_Soon_Patterns {

	/**
	 * Gets an instance of this object.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return Acme_Coming_Soon_Patterns
	 */
	public static function get_instance() {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Initialize the class and set up actions.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return void
	 */
	public function run() {
		add_action( 'init', array( $this, 'register_block_pattern_category' ) );
		add_action( 'init', array( $this, 'register_patterns' ) );
	}

	/**
	 * Register the pattern category.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return void
	 */
	public function register_block_pattern_category() {
		if ( ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}

		register_block_pattern_category(
			'pwp-coming-soon-and-maintenance',
			array(
				'label' => esc_html__( 'Coming Soon and Maintenance', 'acme-coming-soon' ),
			)
		);
	}

	/**
	 * Load and register patterns from the bundled JSON file.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return void
	 */
	public function register_patterns() {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		/**
		 * Filters the local block-pattern data file.
		 *
		 * @since 1.0.7
		 *
		 * @param string $patterns_file Absolute path to a JSON file.
		 */
		$patterns_file = apply_filters( 'acme_coming_soon_patterns_file', ACME_COMING_SOON_PATH . 'includes/json/patterns.json' );
		if ( ! is_string( $patterns_file ) || ! is_readable( $patterns_file ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the bundled local pattern file directly.
		$contents = file_get_contents( $patterns_file );
		if ( false === $contents || '' === trim( $contents ) ) {
			return;
		}

		try {
			$patterns = json_decode( $contents, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			return;
		}

		if ( ! is_array( $patterns ) || ! array_is_list( $patterns ) ) {
			return;
		}

		/**
		 * Filters the bundled block-pattern definitions.
		 *
		 * Each definition must contain a non-empty slug, title.rendered and
		 * pattern_content string. Definitions are validated again after filtering.
		 *
		 * @since 1.0.7
		 *
		 * @param array $patterns Block-pattern definitions.
		 */
		$patterns = apply_filters( 'acme_coming_soon_patterns', $patterns );
		if ( ! is_array( $patterns ) || ! array_is_list( $patterns ) ) {
			return;
		}

		foreach ( $patterns as $pattern ) {
			if ( $this->is_valid_pattern( $pattern ) ) {
				$this->register_block_pattern( $pattern );
			}
		}
	}

	/**
	 * Validate a block-pattern definition.
	 *
	 * @since 1.0.7
	 * @access private
	 *
	 * @param mixed $pattern Pattern definition.
	 * @return bool Whether the definition has the required shape.
	 */
	private function is_valid_pattern( $pattern ) {
		return is_array( $pattern )
			&& isset( $pattern['slug'], $pattern['title'], $pattern['pattern_content'] )
			&& is_string( $pattern['slug'] ) && '' !== trim( $pattern['slug'] )
			&& is_array( $pattern['title'] )
			&& isset( $pattern['title']['rendered'] )
			&& is_string( $pattern['title']['rendered'] ) && '' !== trim( $pattern['title']['rendered'] )
			&& is_string( $pattern['pattern_content'] ) && '' !== trim( $pattern['pattern_content'] );
	}

	/**
	 * Register an individual block pattern.
	 *
	 * @since 1.0.7
	 * @access private
	 *
	 * @param array $pattern Single block pattern.
	 * @return void
	 */
	private function register_block_pattern( $pattern ) {
		register_block_pattern(
			sanitize_title( 'pwp-' . $pattern['slug'] ),
			array(
				'title'      => sanitize_text_field( $pattern['title']['rendered'] ),
				'content'    => $pattern['pattern_content'],
				'categories' => array( 'pwp-coming-soon-and-maintenance' ),
			)
		);
	}
}

acme_coming_soon_patterns()->run();

<?php // phpcs:ignore Class file names should be based on the class name with "class-" prepended.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class used to manage a plugin's settings via the REST API.
 *
 * @link       https://addonspress.com/
 * @since      1.0.0
 *
 * @package    Acme_Coming_Soon
 * @subpackage Acme_Coming_Soon/Acme_Coming_Soon_Api_Settings
 */

/**
 * Plugin settings REST controller.
 *
 * @since      1.0.0
 * @package    Acme_Coming_Soon
 * @subpackage Acme_Coming_Soon/Acme_Coming_Soon_Api_Settings
 * @author     codersantosh <codersantosh@gmail.com>
 * @see        Acme_Coming_Soon_Api
 */
if ( ! class_exists( 'Acme_Coming_Soon_Api_Settings' ) ) {

	/**
	 * Plugin settings REST controller.
	 *
	 * @since 1.0.0
	 */
	class Acme_Coming_Soon_Api_Settings extends Acme_Coming_Soon_Api {

		/**
		 * Initialize the class and set up actions.
		 *
		 * @access public
		 * @return void
		 */
		public function run() {
			$this->type      = 'acme_coming_soon_api_settings';
			$this->rest_base = 'settings';

			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		/**
		 * Register the settings REST route.
		 *
		 * @since 1.0.0
		 * @access public
		 * @return void
		 */
		public function register_routes() {
			$namespace = $this->namespace . $this->version;

			register_rest_route(
				$namespace,
				'/' . $this->rest_base,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_item' ),
						'args'                => array(),
						'permission_callback' => array( $this, 'get_item_permissions_check' ),
					),
					array(
						'methods'             => WP_REST_Server::EDITABLE,
						'callback'            => array( $this, 'update_item' ),
						'args'                => $this->get_endpoint_args(),
						'permission_callback' => array( $this, 'get_item_permissions_check' ),
					),
					'schema' => array( $this, 'get_public_item_schema' ),
				)
			);
		}

		/**
		 * Build the endpoint arguments with strict pre-sanitization type checks.
		 *
		 * WordPress coerces scalar values into lists while sanitizing REST
		 * arguments (wp_parse_list()), so a malformed string sent for an array
		 * property would silently replace the stored list with an empty one.
		 * The strict callback rejects those payloads before sanitization.
		 *
		 * @since 1.0.7
		 * @access private
		 * @return array Endpoint arguments.
		 */
		private function get_endpoint_args() {
			$args = rest_get_endpoint_args_for_schema( $this->get_item_schema(), WP_REST_Server::EDITABLE );

			foreach ( $args as $key => $arg ) {
				$args[ $key ]['validate_callback'] = array( $this, 'validate_setting_arg' );
			}

			return $args;
		}

		/**
		 * Validate a settings request argument before it is sanitized.
		 *
		 * Schema validation runs before request sanitization, which is the only
		 * point where the raw client types are still available.
		 *
		 * @since 1.0.7
		 * @access public
		 *
		 * @param mixed           $value   Raw argument value.
		 * @param WP_REST_Request $request Request object.
		 * @param string          $param   Argument name.
		 * @return true|WP_Error Whether the value is valid.
		 */
		public function validate_setting_arg( $value, $request, $param ) {
			$valid = rest_validate_request_arg( $value, $request, $param );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$attributes = $request->get_attributes();
			$schema     = isset( $attributes['args'][ $param ] ) && is_array( $attributes['args'][ $param ] ) ? $attributes['args'][ $param ] : array();

			return $this->validate_strict_types( $value, $schema, $param );
		}

		/**
		 * Reject values whose raw type does not match the schema.
		 *
		 * WordPress schema validation treats scalar values as one-item lists
		 * for array types, so it cannot reject the type violation in a JSON
		 * payload. This strict walk fails closed instead.
		 *
		 * @since 1.0.7
		 * @access private
		 *
		 * @param mixed  $value  Value to validate.
		 * @param array  $schema Schema for the value.
		 * @param string $path   Field path used in error messages.
		 * @return true|WP_Error Whether the value has the declared type.
		 */
		private function validate_strict_types( $value, $schema, $path ) {
			if ( ! is_array( $schema ) || empty( $schema['type'] ) || ! is_string( $schema['type'] ) ) {
				return true;
			}

			if ( 'array' === $schema['type'] && ! is_array( $value ) ) {
				return $this->invalid_type_error( $path, 'array' );
			}

			if ( 'object' === $schema['type'] ) {
				if ( is_object( $value ) ) {
					$value = (array) $value;
				} elseif ( ! is_array( $value ) ) {
					return $this->invalid_type_error( $path, 'object' );
				}
			}

			if ( ! is_array( $value ) ) {
				return true;
			}

			if ( 'array' === $schema['type'] && isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
				foreach ( $value as $index => $item ) {
					$valid = $this->validate_strict_types( $item, $schema['items'], $path . '[' . $index . ']' );
					if ( is_wp_error( $valid ) ) {
						return $valid;
					}
				}
			}

			if ( 'object' === $schema['type'] && isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
				foreach ( $value as $key => $item ) {
					if ( ! isset( $schema['properties'][ $key ] ) ) {
						continue;
					}

					$valid = $this->validate_strict_types( $item, $schema['properties'][ $key ], $path . '[' . $key . ']' );
					if ( is_wp_error( $valid ) ) {
						return $valid;
					}
				}
			}

			return true;
		}

		/**
		 * Build a 400 error for a value with the wrong type.
		 *
		 * @since 1.0.7
		 * @access private
		 *
		 * @param string $path Field path.
		 * @param string $type Expected JSON schema type.
		 * @return WP_Error Type error.
		 */
		private function invalid_type_error( $path, $type ) {
			return new WP_Error(
				'rest_invalid_type',
				/* translators: 1: Settings field path, 2: Expected JSON schema type. */
				sprintf( __( '%1$s is not of type %2$s.', 'acme-coming-soon' ), $path, $type ),
				array(
					'status' => 400,
					'param'  => $path,
				)
			);
		}

		/**
		 * Check whether the current user may read and manage settings.
		 *
		 * @since 1.0.7
		 * @access public
		 *
		 * @param WP_REST_Request $request Full details about the request.
		 * @return bool Whether the request has access.
		 */
		public function get_item_permissions_check( $request ) {
			unset( $request );

			return acme_coming_soon_can_manage();
		}

		/**
		 * Retrieve the merged and sanitized settings.
		 *
		 * @since 1.0.7
		 * @access public
		 *
		 * @param WP_REST_Request $request Full details about the request.
		 * @return array|WP_Error Settings or an error.
		 */
		public function get_item( $request ) {
			unset( $request );

			return $this->prepare_value( acme_coming_soon_get_options(), $this->get_item_schema() );
		}

		/**
		 * Sanitize a complete settings value.
		 *
		 * @since 1.0.7
		 * @access protected
		 *
		 * @param mixed $value Settings value.
		 * @param array $schema Settings schema.
		 * @return array|WP_Error Sanitized settings or an error.
		 */
		protected function prepare_value( $value, $schema ) {
			$sanitized_value = rest_sanitize_value_from_schema( $value, $schema );
			if ( is_wp_error( $sanitized_value ) ) {
				return $sanitized_value;
			}
			if ( ! is_array( $sanitized_value ) ) {
				return new WP_Error( 'rest_invalid_settings', __( 'The settings value is invalid.', 'acme-coming-soon' ), array( 'status' => 400 ) );
			}

			$defaults = acme_coming_soon_default_options();

			$sanitized_value['on']        = ! empty( $sanitized_value['on'] );
			$sanitized_value['access']    = isset( $sanitized_value['access'] ) && in_array( $sanitized_value['access'], array( '', 'login', 'admin', 'roles' ), true ) ? $sanitized_value['access'] : $defaults['access'];
			$sanitized_value['template']  = absint( isset( $sanitized_value['template'] ) ? $sanitized_value['template'] : 0 );
			$sanitized_value['deleteAll'] = ! empty( $sanitized_value['deleteAll'] );

			$exclude                    = isset( $sanitized_value['exclude'] ) && is_array( $sanitized_value['exclude'] ) ? $sanitized_value['exclude'] : array();
			$exclude['useRoles']        = acme_coming_soon_sanitize_roles( isset( $exclude['useRoles'] ) ? $exclude['useRoles'] : array() );
			$exclude['searchBot']       = ! empty( $exclude['searchBot'] );
			$exclude['ips']             = $this->sanitize_list( isset( $exclude['ips'] ) ? $exclude['ips'] : array(), 'acme_coming_soon_sanitize_ip' );
			$exclude['slugs']           = $this->sanitize_list( isset( $exclude['slugs'] ) ? $exclude['slugs'] : array(), 'acme_coming_soon_sanitize_path_slug' );
			$sanitized_value['exclude'] = $exclude; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Plugin settings key, not a WP_Query argument.

			return $sanitized_value;
		}

		/**
		 * Sanitize and deduplicate a list of strings.
		 *
		 * @since 1.0.7
		 * @access private
		 *
		 * @param mixed  $items List items.
		 * @param string $callback Sanitization callback.
		 * @return array Sanitized list.
		 */
		private function sanitize_list( $items, $callback ) {
			if ( ! is_array( $items ) ) {
				return array();
			}

			$sanitized_items = array();
			foreach ( $items as $item ) {
				$sanitized_item = call_user_func( $callback, $item );
				if ( is_string( $sanitized_item ) && '' !== $sanitized_item && ! in_array( $sanitized_item, $sanitized_items, true ) ) {
					$sanitized_items[] = $sanitized_item;
				}
			}

			return $sanitized_items;
		}

		/**
		 * Merge a partial payload, validate the full configuration, sanitize and save it.
		 *
		 * @since 1.0.7
		 * @access public
		 *
		 * @param WP_REST_Request $request Full details about the request.
		 * @return array|WP_Error Settings or an error.
		 */
		public function update_item( $request ) {
			$schema = $this->get_item_schema();
			$params = $request->get_params();

			if ( ! is_array( $params ) ) {
				return $this->invalid_param_error( __( 'The settings payload must be an object.', 'acme-coming-soon' ) );
			}

			$payload_validation = rest_validate_value_from_schema( $params, $schema );
			if ( is_wp_error( $payload_validation ) ) {
				return $this->validation_error( $payload_validation );
			}

			$strict_validation = $this->validate_strict_types( $params, $schema, $this->rest_base );
			if ( is_wp_error( $strict_validation ) ) {
				return $this->validation_error( $strict_validation );
			}

			$merged_options = acme_coming_soon_merge_options( acme_coming_soon_get_options(), $params );
			$merged_options = $this->get_known_properties( $merged_options, $schema );

			$full_validation = rest_validate_value_from_schema( $merged_options, $schema );
			if ( is_wp_error( $full_validation ) ) {
				return $this->validation_error( $full_validation );
			}

			if ( ! empty( $merged_options['on'] ) && ! acme_coming_soon_is_published_page( $merged_options['template'] ) ) {
				return new WP_Error(
					'rest_invalid_template',
					__( 'A published maintenance page must be selected before enabling maintenance mode.', 'acme-coming-soon' ),
					array(
						'status' => 400,
						'params' => array( 'template' => __( 'A published maintenance page is required.', 'acme-coming-soon' ) ),
					)
				);
			}

			$sanitized_options = $this->prepare_value( $merged_options, $schema );
			if ( is_wp_error( $sanitized_options ) ) {
				return new WP_Error( $sanitized_options->get_error_code(), $sanitized_options->get_error_message(), array( 'status' => 400 ) );
			}

			// Persist only submitted keys so defaults are never written by a partial update.
			$update = $this->get_requested_properties( $sanitized_options, $params, $schema );
			$result = acme_coming_soon_update_options( $update );

			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'rest_settings_not_saved', $result->get_error_message(), array( 'status' => 500 ) );
			}
			if ( ! $result ) {
				return new WP_Error( 'rest_settings_not_saved', __( 'The settings could not be saved.', 'acme-coming-soon' ), array( 'status' => 500 ) );
			}

			return $this->get_item( $request );
		}

		/**
		 * Project a value onto the properties defined by a schema.
		 *
		 * This removes unknown legacy keys before validating stored settings.
		 *
		 * @since 1.0.7
		 * @access private
		 *
		 * @param mixed $value Settings value.
		 * @param array $schema Settings schema.
		 * @return mixed Projected value.
		 */
		private function get_known_properties( $value, $schema ) {
			if ( ! is_array( $value ) ) {
				return $value;
			}

			if ( 'array' === $schema['type'] && isset( $schema['items'] ) ) {
				foreach ( $value as $index => $item ) {
					$value[ $index ] = $this->get_known_properties( $item, $schema['items'] );
				}
				return $value;
			}

			if ( 'object' !== $schema['type'] ) {
				return $value;
			}

			$known = array();
			foreach ( $value as $key => $item ) {
				if ( isset( $schema['properties'][ $key ] ) ) {
					$known[ $key ] = $this->get_known_properties( $item, $schema['properties'][ $key ] );
				}
			}

			return $known;
		}

		/**
		 * Select only the sanitized keys supplied by a partial payload.
		 *
		 * @since 1.0.7
		 * @access private
		 *
		 * @param array $sanitized Full sanitized settings.
		 * @param array $payload Partial request payload.
		 * @param array $schema Settings schema.
		 * @return array Sanitized partial update.
		 */
		private function get_requested_properties( $sanitized, $payload, $schema ) {
			$update = array();

			foreach ( $payload as $key => $value ) {
				if ( ! array_key_exists( $key, $sanitized ) || ! isset( $schema['properties'][ $key ] ) ) {
					continue;
				}

				$property_schema = $schema['properties'][ $key ];
				if ( 'object' === $property_schema['type'] && is_array( $value ) ) {
					$update[ $key ] = $this->get_requested_properties( $sanitized[ $key ], $value, $property_schema );
				} else {
					$update[ $key ] = $sanitized[ $key ];
				}
			}

			return $update;
		}

		/**
		 * Convert a schema validation failure to an HTTP 400 error.
		 *
		 * @since 1.0.7
		 * @access private
		 *
		 * @param WP_Error $validation Validation error.
		 * @return WP_Error Client error.
		 */
		private function validation_error( $validation ) {
			$data = method_exists( $validation, 'get_error_data' ) ? $validation->get_error_data() : ( isset( $validation->data ) ? $validation->data : array() );
			$data = is_array( $data ) ? $data : array();

			$data['status'] = 400;
			$code           = method_exists( $validation, 'get_error_code' ) ? $validation->get_error_code() : 'rest_invalid_param';
			$message        = method_exists( $validation, 'get_error_message' ) ? $validation->get_error_message() : __( 'The settings payload is invalid.', 'acme-coming-soon' );

			return new WP_Error( $code, $message, $data );
		}

		/**
		 * Build an HTTP 400 error for a malformed payload.
		 *
		 * @since 1.0.7
		 * @access private
		 *
		 * @param string $message Error message.
		 * @return WP_Error Client error.
		 */
		private function invalid_param_error( $message ) {
			return new WP_Error( 'rest_invalid_param', $message, array( 'status' => 400 ) );
		}

		/**
		 * Retrieve the registered settings schema.
		 *
		 * @since 1.0.7
		 * @access protected
		 * @return array Registered schema.
		 */
		protected function get_registered_schema() {
			static $cached_schema = null;

			if ( null === $cached_schema ) {
				$cached_schema = acme_coming_soon_admin()->get_settings_schema();
			}

			return $cached_schema;
		}

		/**
		 * Retrieve the settings schema, conforming to JSON Schema.
		 *
		 * @since 1.0.7
		 * @access public
		 * @return array Item schema.
		 */
		public function get_item_schema() {
			$schema = array(
				'$schema'              => 'http://json-schema.org/draft-04/schema#',
				'title'                => $this->type,
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => $this->get_registered_schema()['properties'],
			);

			/**
			 * Filters the item's schema.
			 *
			 * @since 1.0.7
			 *
			 * @param array $schema Item schema data.
			 */
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Preserve the existing REST schema filter name.
			$schema       = apply_filters( "rest_{$this->type}_item_schema", $schema );
			$this->schema = $schema;

			return $this->add_additional_fields_schema( $this->schema );
		}

		/**
		 * Gets an instance of this object.
		 *
		 * @since 1.0.0
		 * @access public
		 * @return Acme_Coming_Soon_Api_Settings
		 */
		public static function get_instance() {
			static $instance = null;

			if ( null === $instance ) {
				$instance = new self();
			}

			return $instance;
		}
	}
}

acme_coming_soon_api_settings()->run();

<?php
/**
 * OpenAPI generator.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\CLI;

use WP_CLI;

/**
 * Builds the OpenAPI document from registered REST routes and their schemas.
 */
final class OpenAPI {

	/**
	 * Generate the OpenAPI document and print it as JSON.
	 *
	 * Paths, methods, parameters, request bodies and response schemas come from
	 * the registered routes and their schema callbacks.
	 *
	 * ## EXAMPLES
	 *
	 *     wp re-events openapi > docs/api/openapi.json
	 */
	public static function generate(): void {
		$json = wp_json_encode( self::build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( ! is_string( $json ) ) {
			WP_CLI::error( 'The OpenAPI document could not be encoded.' );
		}

		WP_CLI::line( $json );
	}

	/**
	 * Build the document.
	 *
	 * @return array<string, mixed>
	 */
	private static function build(): array {
		$server  = rest_get_server();
		$paths   = [];
		$schemas = [ 'Error' => self::error_schema() ];
		$tags    = [];

		foreach ( $server->get_routes( 're-events/v1' ) as $route => $handlers ) {
			$route = (string) $route;
			$path  = self::path( $route );

			if ( '/' === $path ) {
				continue;
			}

			$options     = $server->get_route_options( $route );
			$item_schema = self::item_schema( $options );
			$component   = null;

			if ( null !== $item_schema ) {
				$component             = self::component_name( $item_schema, $path );
				$schemas[ $component ] = self::clean_schema( $item_schema );
			}

			foreach ( (array) $handlers as $handler ) {
				if ( ! is_array( $handler ) || ! isset( $handler['methods'] ) ) {
					continue;
				}

				foreach ( self::methods( $handler['methods'] ) as $method ) {
					$response = (string) ( $handler['response'] ?? $options['response'] ?? self::response_type( $path, $method ) );

					$paths[ $path ][ strtolower( $method ) ] = self::operation( $path, $route, $method, $handler, $component, $response );

					$tags[ self::tag( $path ) ] = true;
				}
			}
		}

		return [
			'openapi'    => '3.1.0',
			'info'       => [
				'title'       => 'ReEvents API',
				'version'     => RE_EVENTS_VERSION,
				'description' => 'REST API for events, venues, organizers and bookings.',
			],
			'security'   => [],
			'servers'    => [
				[
					'url'         => '/wp-json/re-events/v1',
					'description' => 'Current site',
				],
			],
			'tags'       => array_map(
				static function ( string $name ): array {
					return [ 'name' => $name ];
				},
				array_keys( $tags )
			),
			'paths'      => $paths ? $paths : (object) [],
			'components' => [
				'securitySchemes' => [
					'ApplicationPassword' => [
						'type'        => 'http',
						'scheme'      => 'basic',
						'description' => 'WordPress Application Password.',
					],
				],
				'schemas'         => $schemas,
			],
		];
	}

	/**
	 * Build a generated operation.
	 *
	 * @param string               $path      OpenAPI path.
	 * @param string               $route     Registered route regex.
	 * @param string               $method    HTTP method.
	 * @param array<string, mixed> $handler   Route handler.
	 * @param string|null          $component Response schema component.
	 * @param string               $response  Response type: item, collection or deleted.
	 * @return array<string, mixed>
	 */
	private static function operation( string $path, string $route, string $method, array $handler, ?string $component, string $response ): array {
		$args        = isset( $handler['args'] ) && is_array( $handler['args'] ) ? $handler['args'] : [];
		$path_params = self::path_params( $route );
		$parameters  = [];
		$properties  = [];
		$required    = [];
		$is_write    = in_array( $method, [ 'POST', 'PUT', 'PATCH', 'DELETE' ], true );
		$is_body     = in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true );

		foreach ( $args as $name => $arg ) {
			if ( ! is_array( $arg ) ) {
				continue;
			}

			$name   = (string) $name;
			$schema = self::schema( $arg );

			if ( in_array( $name, $path_params, true ) ) {
				$parameters[] = [
					'name'        => $name,
					'in'          => 'path',
					'required'    => true,
					'description' => (string) ( $arg['description'] ?? '' ),
					'schema'      => $schema,
				];
				continue;
			}

			if ( $is_body ) {
				$properties[ $name ] = $schema;

				if ( ! empty( $arg['description'] ) ) {
					$properties[ $name ]['description'] = (string) $arg['description'];
				}

				if ( ! empty( $arg['required'] ) ) {
					$required[] = $name;
				}
				continue;
			}

			$parameters[] = [
				'name'        => $name,
				'in'          => 'query',
				'required'    => ! empty( $arg['required'] ),
				'description' => (string) ( $arg['description'] ?? '' ),
				'schema'      => $schema,
			];
		}

		$operation = [
			'operationId' => self::operation_id( $method, $path ),
			'tags'        => [ self::tag( $path ) ],
			'summary'     => self::summary( $path, $method, $component, $response ),
			'responses'   => self::responses( $method, $path, $component, $response ),
		];

		if ( $is_write ) {
			$operation['security'] = [ [ 'ApplicationPassword' => [] ] ];
		}

		if ( $parameters ) {
			$operation['parameters'] = $parameters;
		}

		if ( $properties ) {
			$schema = [
				'type'       => 'object',
				'properties' => $properties,
			];

			if ( $required ) {
				$schema['required'] = $required;
			}

			$operation['requestBody'] = [
				'required' => (bool) $required,
				'content'  => [
					'application/json' => [ 'schema' => $schema ],
				],
			];
		}

		return $operation;
	}

	/**
	 * Build responses for an operation.
	 *
	 * @param string      $method    HTTP method.
	 * @param string      $path      OpenAPI path.
	 * @param string|null $component Response schema component.
	 * @param string      $response  Response type.
	 * @return array<int|string, mixed>
	 */
	private static function responses( string $method, string $path, ?string $component, string $response ): array {
		$schema    = self::response_schema( $response, $component );
		$created   = 'POST' === $method && 'item' === $response;
		$status    = $created ? '201' : '200';
		$responses = [];

		if ( null === $schema ) {
			$responses[ $status ] = [ 'description' => 'OK' ];
		} else {
			$entry = [
				'description' => $created ? 'Item created.' : ( 'deleted' === $response ? 'Item deleted.' : 'OK' ),
				'content'     => [
					'application/json' => [ 'schema' => $schema ],
				],
			];

			if ( $created ) {
				$entry['headers'] = [
					'Location' => [
						'description' => 'URL of the created item.',
						'schema'      => [
							'type'   => 'string',
							'format' => 'uri',
						],
					],
				];
			}

			if ( 'collection' === $response ) {
				$entry['headers'] = [
					'X-WP-Total'      => [
						'description' => 'Total number of matching items.',
						'schema'      => [ 'type' => 'integer' ],
					],
					'X-WP-TotalPages' => [
						'description' => 'Total number of result pages.',
						'schema'      => [ 'type' => 'integer' ],
					],
				];
			}

			$responses[ $status ] = $entry;
		}

		if ( in_array( $method, [ 'POST', 'PUT', 'PATCH', 'DELETE' ], true ) ) {
			$responses['400'] = self::error_response( 'Invalid request data.' );
			$responses['401'] = self::error_response( 'Authentication required.' );
			$responses['403'] = self::error_response( 'Insufficient permissions.' );
		}

		if ( str_contains( $path, '{' ) ) {
			$responses['404'] = self::error_response( 'Not found.' );
		}

		return $responses;
	}

	/**
	 * Build a response schema.
	 *
	 * @param string      $response  Response type.
	 * @param string|null $component Schema component.
	 * @return array<string, mixed>|null
	 */
	private static function response_schema( string $response, ?string $component ): ?array {
		if ( null === $component ) {
			return null;
		}

		$ref = [ '$ref' => '#/components/schemas/' . $component ];

		return match ( $response ) {
			'collection' => [
				'type'  => 'array',
				'items' => $ref,
			],
			'deleted'    => [
				'type'       => 'object',
				'properties' => [
					'deleted'  => [ 'type' => 'boolean' ],
					'previous' => $ref,
				],
			],
			default      => $ref,
		};
	}

	/**
	 * Build an error response.
	 *
	 * @param string $description Description.
	 * @return array<string, mixed>
	 */
	private static function error_response( string $description ): array {
		return [
			'description' => $description,
			'content'     => [
				'application/json' => [
					'schema' => [ '$ref' => '#/components/schemas/Error' ],
				],
			],
		];
	}

	/**
	 * Build the error schema.
	 *
	 * @return array<string, mixed>
	 */
	private static function error_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'code'    => [ 'type' => 'string' ],
				'message' => [ 'type' => 'string' ],
				'data'    => [
					'type'       => 'object',
					'properties' => [
						'status' => [ 'type' => 'integer' ],
						'errors' => [
							'type'                 => 'object',
							'description'          => 'Field-level validation messages keyed by field path.',
							'additionalProperties' => [ 'type' => 'string' ],
						],
					],
				],
			],
		];
	}

	/**
	 * Read the item schema from route options.
	 *
	 * @param array<string, mixed> $options Route options.
	 * @return array<string, mixed>|null
	 */
	private static function item_schema( array $options ): ?array {
		$callback = $options['schema'] ?? null;

		if ( ! is_callable( $callback ) ) {
			return null;
		}

		$schema = call_user_func( $callback );

		return is_array( $schema ) ? $schema : null;
	}

	/**
	 * Remove keys that do not belong in an OpenAPI schema.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @return array<string, mixed>
	 */
	private static function clean_schema( array $schema ): array {
		unset( $schema['$schema'] );

		return self::normalize_schema( $schema );
	}

	/**
	 * Normalize WordPress schema keys for OpenAPI.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @return array<string, mixed>
	 */
	private static function normalize_schema( array $schema ): array {
		if ( isset( $schema['readonly'] ) ) {
			$schema['readOnly'] = (bool) $schema['readonly'];
			unset( $schema['readonly'] );
		}

		foreach ( $schema as $key => $value ) {
			if ( is_array( $value ) ) {
				$schema[ $key ] = self::normalize_schema( $value );
			}
		}

		return $schema;
	}

	/**
	 * Derive a component name from the schema title.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @param string               $path   OpenAPI path.
	 */
	private static function component_name( array $schema, string $path ): string {
		$title = (string) ( $schema['title'] ?? '' );

		if ( '' === $title ) {
			$segments = array_values(
				array_filter(
					explode( '/', trim( $path, '/' ) ),
					static function ( string $segment ): bool {
						return ! str_starts_with( $segment, '{' );
					}
				)
			);

			$title = $segments ? (string) end( $segments ) : 'Resource';
			$title = rtrim( $title, 's' );
		}

		return str_replace( ' ', '', ucwords( str_replace( [ '-', '_' ], ' ', $title ) ) );
	}

	/**
	 * Derive a summary from the method and response type.
	 *
	 * @param string      $path      OpenAPI path.
	 * @param string      $method    HTTP method.
	 * @param string|null $component Schema component.
	 * @param string      $response  Response type.
	 */
	private static function summary( string $path, string $method, ?string $component, string $response ): string {
		if ( '/ping' === $path ) {
			return 'Health check';
		}

		$singular = $component ?? 'resource';
		$plural   = self::plural( $singular );

		if ( 'deleted' === $response ) {
			return 'Delete ' . $singular;
		}

		if ( 'collection' === $response ) {
			return 'POST' === $method ? 'Sync ' . $plural : 'List ' . $plural;
		}

		return match ( $method ) {
			'POST'          => 'Create ' . $singular,
			'PUT', 'PATCH'  => 'Update ' . $singular,
			'DELETE'        => 'Delete ' . $singular,
			default         => 'Get ' . $singular,
		};
	}

	/**
	 * Pluralize a component name.
	 *
	 * @param string $singular Singular name.
	 */
	private static function plural( string $singular ): string {
		if ( str_ends_with( $singular, 'y' ) ) {
			return substr( $singular, 0, -1 ) . 'ies';
		}

		return str_ends_with( $singular, 's' ) ? $singular : $singular . 's';
	}

	/**
	 * Infer the response type when the route does not declare one.
	 *
	 * @param string $path   OpenAPI path.
	 * @param string $method HTTP method.
	 */
	private static function response_type( string $path, string $method ): string {
		if ( 'DELETE' === $method ) {
			return 'deleted';
		}

		if ( 'GET' === $method && ! str_contains( $path, '{' ) ) {
			return 'collection';
		}

		return 'item';
	}

	/**
	 * Convert a route regex to an OpenAPI path.
	 *
	 * @param string $route Route regex.
	 */
	private static function path( string $route ): string {
		$path = preg_replace( '#^/re-events/v1#', '', $route ) ?? $route;
		$path = preg_replace( '#\(\?P<([a-zA-Z0-9_]+)>[^)]*\)#', '{$1}', $path ) ?? $path;

		return '' === $path ? '/' : $path;
	}

	/**
	 * Extract named path parameters from a route regex.
	 *
	 * @param string $route Route regex.
	 * @return string[]
	 */
	private static function path_params( string $route ): array {
		preg_match_all( '/\(\?P<([a-zA-Z0-9_]+)>/', $route, $matches );

		return $matches[1];
	}

	/**
	 * Normalize handler methods.
	 *
	 * @param mixed $methods Handler methods.
	 * @return string[]
	 */
	private static function methods( $methods ): array {
		if ( is_string( $methods ) ) {
			$methods = explode( ',', $methods );
		}

		if ( ! is_array( $methods ) ) {
			return [];
		}

		$normalized = [];

		foreach ( $methods as $key => $value ) {
			// WordPress stores methods as [ 'GET' => true ]; accept plain lists too.
			$method = is_string( $key ) ? $key : (string) $value;
			$method = strtoupper( trim( $method ) );

			if ( '' !== $method ) {
				$normalized[] = $method;
			}
		}

		return $normalized;
	}

	/**
	 * Build a schema from an argument definition.
	 *
	 * @param array<string, mixed> $arg Argument definition.
	 * @return array<string, mixed>
	 */
	private static function schema( array $arg ): array {
		$schema = [];

		foreach ( [ 'type', 'format', 'enum', 'default', 'minimum', 'maximum', 'items' ] as $key ) {
			if ( isset( $arg[ $key ] ) ) {
				$schema[ $key ] = $arg[ $key ];
			}
		}

		$schema['type'] ??= 'string';

		return $schema;
	}

	/**
	 * Build an operation ID.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   OpenAPI path.
	 */
	private static function operation_id( string $method, string $path ): string {
		$slug = preg_replace( '/[^a-zA-Z0-9]+/', '_', $path ) ?? $path;

		return strtolower( $method ) . '_' . trim( $slug, '_' );
	}

	/**
	 * Pick a tag from the first path segment.
	 *
	 * @param string $path OpenAPI path.
	 */
	private static function tag( string $path ): string {
		$segment = explode( '/', trim( $path, '/' ) )[0];

		return match ( $segment ) {
			'events'     => 'Events',
			'venues'     => 'Venues',
			'organizers' => 'Organizers',
			'categories' => 'Categories',
			'tickets'    => 'Bookings',
			'bookings'   => 'Bookings',
			default      => 'System',
		};
	}
}

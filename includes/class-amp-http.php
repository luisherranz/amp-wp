<?php
/**
 * Class AMP_HTTP
 *
 * @since 1.0
 * @package AMP
 */

/**
 * Class AMP_HTTP
 */
class AMP_HTTP {

	/**
	 * Query var which is submitted with a form which had an action attribute which was automatically converted into action-xhr.
	 *
	 * @see \AMP_Form_Sanitizer::sanitize()
	 * @var string
	 */
	const ACTION_XHR_CONVERTED_QUERY_VAR = '_wp_amp_action_xhr_converted';

	/**
	 * Headers sent (or attempted to be sent).
	 *
	 * This is used primarily for the benefit of unit testing. Otherwise, `headers_list()` should be used.
	 *
	 * @since 1.0
	 * @see AMP_HTTP::send_header()
	 * @var array[]
	 */
	public static $headers_sent = [];

	/**
	 * Whether Server-Timing headers are sent.
	 *
	 * By default this is false to prevent breaking some web servers with an unexpected number of response headers. To
	 * enable in `WP_DEBUG` mode, consider the following plugin code:
	 *
	 *     add_action( 'amp_init', function () {
	 *         AMP_HTTP::$server_timing = ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || current_user_can( 'manage_options' ) );
	 *     } );
	 *
	 * @link https://gist.github.com/westonruter/053f8f47c21df51f1a081fc41b47f547
	 * @var bool
	 */
	public static $server_timing = false;

	/**
	 * AMP-specific query vars that were purged.
	 *
	 * @since 0.7
	 * @since 1.0 Moved to AMP_HTTP class.
	 * @see AMP_HTTP::purge_amp_query_vars()
	 * @var string[]
	 */
	public static $purged_amp_query_vars = [];

	/**
	 * Send an HTTP response header.
	 *
	 * This largely exists to facilitate unit testing but it also provides a better interface for sending headers.
	 *
	 * @since 0.7.0
	 * @since 1.0 Moved to AMP_HTTP class.
	 *
	 * @param string $name  Header name.
	 * @param string $value Header value.
	 * @param array  $args {
	 *     Args to header().
	 *
	 *     @type bool $replace     Whether to replace a header previously sent. Default true.
	 *     @type int  $status_code Status code to send with the sent header.
	 * }
	 * @return bool Whether the header was sent.
	 */
	public static function send_header( $name, $value, $args = [] ) {
		$args = array_merge(
			[
				'replace'     => true,
				'status_code' => null,
			],
			$args
		);

		self::$headers_sent[] = array_merge( compact( 'name', 'value' ), $args );
		if ( headers_sent() ) {
			return false;
		}

		header(
			sprintf( '%s: %s', $name, $value ),
			$args['replace'],
			$args['status_code']
		);
		return true;
	}

	/**
	 * Send Server-Timing header.
	 *
	 * If WP_DEBUG is not enabled and an admin user (who can manage_options) is not logged-in, the Server-Header will not be sent.
	 *
	 * @since 1.0
	 *
	 * @param string $name        Name.
	 * @param float  $duration    Duration. If negative, will be added to microtime( true ). Optional.
	 * @param string $description Description. Optional.
	 * @return bool Return value of send_header call. If WP_DEBUG is not enabled or admin user (who can manage_options) is not logged-in, this will always return false.
	 */
	public static function send_server_timing( $name, $duration = null, $description = null ) {
		if ( ! self::$server_timing ) {
			return false;
		}
		$value = $name;
		if ( isset( $description ) ) {
			$value .= sprintf( ';desc="%s"', str_replace( [ '\\', '"' ], '', substr( $description, 0, 100 ) ) );
		}
		if ( isset( $duration ) ) {
			if ( $duration < 0 ) {
				$duration = microtime( true ) + $duration;
			}
			$value .= sprintf( ';dur=%f', $duration * 1000 );
		}
		return self::send_header( 'Server-Timing', $value, [ 'replace' => false ] );
	}

	/**
	 * Remove query vars that come in requests such as for amp-live-list.
	 *
	 * WordPress should generally not respond differently to requests when these parameters
	 * are present. In some cases, when a query param such as __amp_source_origin is present
	 * then it would normally get included into pagination links generated by get_pagenum_link().
	 * The whitelist sanitizer empties out links that contain this string as it matches the
	 * blacklisted_value_regex. So by preemptively scrubbing any reference to these query vars
	 * we can ensure that WordPress won't end up referencing them in any way.
	 *
	 * @since 0.7
	 * @since 1.0 Moved to AMP_HTTP class.
	 */
	public static function purge_amp_query_vars() {
		$query_vars = [
			'__amp_source_origin',
			self::ACTION_XHR_CONVERTED_QUERY_VAR,
			'amp_latest_update_time',
			'amp_last_check_time',
		];

		// Scrub input vars.
		foreach ( $query_vars as $query_var ) {
			if ( ! isset( $_GET[ $query_var ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				continue;
			}
			self::$purged_amp_query_vars[ $query_var ] = wp_unslash( $_GET[ $query_var ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			unset( $_REQUEST[ $query_var ], $_GET[ $query_var ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$scrubbed = true;
		}

		if ( isset( $scrubbed ) ) {
			$build_query = static function ( $query ) use ( $query_vars ) {
				$pattern = '/^(' . implode( '|', $query_vars ) . ')(?==|$)/';
				$pairs   = [];
				foreach ( explode( '&', $query ) as $pair ) {
					if ( ! preg_match( $pattern, $pair ) ) {
						$pairs[] = $pair;
					}
				}

				return implode( '&', $pairs );
			};

			// Scrub QUERY_STRING.
			if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
				$_SERVER['QUERY_STRING'] = $build_query( $_SERVER['QUERY_STRING'] );
			}

			// Scrub REQUEST_URI.
			if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
				list( $path, $query ) = explode( '?', $_SERVER['REQUEST_URI'], 2 );

				$pairs                  = $build_query( $query );
				$_SERVER['REQUEST_URI'] = $path;
				if ( ! empty( $pairs ) ) {
					$_SERVER['REQUEST_URI'] .= "?{$pairs}";
				}
			}
		}
	}

	/**
	 * Filter the allowed redirect hosts to include AMP caches.
	 *
	 * @since 1.0
	 *
	 * @param array $allowed_hosts Allowed hosts.
	 * @return array Allowed redirect hosts.
	 */
	public static function filter_allowed_redirect_hosts( $allowed_hosts ) {
		return array_merge( $allowed_hosts, self::get_amp_cache_hosts() );
	}

	/**
	 * Get the domains for AMP Caches.
	 *
	 * @since 1.3
	 *
	 * @todo Eventually this list should be populated dynamically. See <https://github.com/ampproject/amp-wp/issues/2382>.
	 * @return string[] Domains for AMP caches.
	 */
	public static function get_amp_cache_domains() {
		return [
			// Google AMP Cache subdomain.
			'cdn.ampproject.org',

			// Cloudflare AMP Cache.
			'amp.cloudflare.com',

			// Bing AMP Cache.
			'bing-amp.com',
		];
	}

	/**
	 * Get list of AMP cache hosts (that is, CORS origins).
	 *
	 * @since 1.0
	 * @link https://www.ampproject.org/docs/fundamentals/amp-cors-requests#1)-allow-requests-for-specific-cors-origins
	 *
	 * @return array AMP cache hosts.
	 */
	public static function get_amp_cache_hosts() {
		$hosts = [];

		// Google AMP Cache (legacy).
		$hosts[] = 'cdn.ampproject.org';

		// From the publisher’s own origins.
		$domains = array_unique(
			[
				wp_parse_url( site_url(), PHP_URL_HOST ),
				wp_parse_url( home_url(), PHP_URL_HOST ),
			]
		);

		foreach ( $domains as $domain ) {
			$subdomain = self::get_amp_cache_subdomain( $domain );

			foreach ( self::get_amp_cache_domains() as $cache_domain ) {
				$hosts[] = sprintf( '%s.%s', $subdomain, $cache_domain );
			}
		}

		return $hosts;
	}

	/**
	 * Convert a domain into the subdomain segment used in AMP caches URLs.
	 *
	 * From AMP docs:
	 * "When possible, the Google AMP Cache will create a subdomain for each AMP document's domain by first converting it
	 * from IDN (punycode) to UTF-8. The caches replaces every - (dash) with -- (2 dashes) and replace every . (dot) with
	 * - (dash). For example, pub.com will map to pub-com.cdn.ampproject.org."
	 *
	 * @since 1.3
	 * @link https://amp.dev/documentation/guides-and-tutorials/learn/amp-caches-and-cors/amp-cache-urls/
	 *
	 * @param string $domain Origin domain.
	 * @return string Subdomain segment used under an AMP Cache domain.
	 */
	public static function get_amp_cache_subdomain( $domain ) {
		if ( function_exists( 'idn_to_utf8' ) ) {
			// The third parameter is set explicitly to prevent issues with newer PHP versions compiled with an old ICU version.
			// phpcs:ignore PHPCompatibility.Constants.RemovedConstants.intl_idna_variant_2003Deprecated
			$domain = idn_to_utf8( $domain, IDNA_DEFAULT, defined( 'INTL_IDNA_VARIANT_UTS46' ) ? INTL_IDNA_VARIANT_UTS46 : INTL_IDNA_VARIANT_2003 );
		}
		return str_replace( [ '-', '.' ], [ '--', '-' ], $domain );
	}

	/**
	 * Get the AMP Cache URL for a given URL.
	 *
	 * @since 1.3
	 * @link https://amp.dev/documentation/guides-and-tutorials/learn/amp-caches-and-cors/amp-cache-urls/
	 *
	 * @param string $url              URL.
	 * @param string $amp_cache_domain AMP Cache domain. Defaults to cdn.ampproject.org.
	 * @return string AMP Cache URL.
	 */
	public static function get_amp_cache_url( $url, $amp_cache_domain = 'cdn.ampproject.org' ) {
		$parsed_url = wp_parse_url( $url );
		if ( ! $parsed_url ) {
			return null;
		}
		if ( ! isset( $parsed_url['host'] ) ) {
			$parsed_url['host'] = wp_parse_url( home_url(), PHP_URL_HOST );
		}
		$subdomain = self::get_amp_cache_subdomain( $parsed_url['host'] );

		$cache_url  = sprintf( 'https://%s.%s', $subdomain, $amp_cache_domain );
		$cache_url .= '/c'; // Fetch AMP document.
		if ( isset( $parsed_url['scheme'] ) && 'https' === $parsed_url['scheme'] ) {
			$cache_url .= '/s';
		}
		$cache_url .= '/' . $parsed_url['host'];
		if ( isset( $parsed_url['path'] ) ) {
			$cache_url .= $parsed_url['path'];
		}
		if ( isset( $parsed_url['query'] ) ) {
			$cache_url .= '?' . $parsed_url['query'];
		}
		if ( isset( $parsed_url['fragment'] ) ) {
			$cache_url .= '#' . $parsed_url['fragment'];
		}
		return $cache_url;
	}

	/**
	 * Send cors headers.
	 *
	 * From the AMP docs:
	 * Restrict requests to source origins
	 * In all fetch requests, the AMP Runtime passes the "__amp_source_origin" query parameter, which contains
	 * the value of the source origin (for example, "https://publisher1.com").
	 *
	 * To restrict requests to only source origins, check that the value of the "__amp_source_origin" parameter
	 * is within a set of the Publisher's own origins.
	 *
	 * Access-Control-Allow-Origin: <origin>
	 * This header is a W3 CORS Spec requirement, where origin refers to the requesting origin that was allowed
	 * via the CORS Origin request header (for example, "https://<publisher's subdomain>.cdn.ampproject.org").
	 *
	 * Although the W3 CORS spec allows the value of * to be returned in the response, for improved security, you should:
	 *
	 * - If the Origin header is present, validate and echo the value of the Origin header.
	 * - If the Origin header isn't present, validate and echo the value of the "__amp_source_origin".
	 *
	 * (Otherwise, no Access-Control-Allow-Origin header is sent.)
	 *
	 * AMP-Access-Control-Allow-Source-Origin: <source-origin>
	 * This header allows the specified source-origin to read the authorization response. The source-origin is
	 * the value specified and verified in the "__amp_source_origin" URL parameter (for example, "https://publisher1.com").
	 *
	 * Access-Control-Expose-Headers: AMP-Access-Control-Allow-Source-Origin
	 * This header simply allows the CORS response to contain the AMP-Access-Control-Allow-Source-Origin header.
	 *
	 * @link https://www.ampproject.org/docs/fundamentals/amp-cors-requests
	 * @since 1.0
	 */
	public static function send_cors_headers() {
		$origin        = null;
		$source_origin = null;
		if ( isset( $_SERVER['HTTP_ORIGIN'] ) ) {
			$origin = wp_validate_redirect( wp_sanitize_redirect( esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) ) );
		}
		if ( isset( self::$purged_amp_query_vars['__amp_source_origin'] ) ) {
			$source_origin = wp_validate_redirect( wp_sanitize_redirect( esc_url_raw( self::$purged_amp_query_vars['__amp_source_origin'] ) ) );
		}
		if ( ! $origin ) {
			$origin = $source_origin;
		}

		if ( $origin ) {
			self::send_header( 'Access-Control-Allow-Origin', $origin, [ 'replace' => false ] );
			self::send_header( 'Access-Control-Allow-Credentials', 'true' );
			self::send_header( 'Vary', 'Origin', [ 'replace' => false ] );
		}
		if ( $source_origin ) {
			self::send_header( 'AMP-Access-Control-Allow-Source-Origin', $source_origin );
			self::send_header( 'Access-Control-Expose-Headers', 'AMP-Access-Control-Allow-Source-Origin', [ 'replace' => false ] );
		}
	}

	/**
	 * Hook into a POST form submissions, such as the comment form or some other form submission.
	 *
	 * @since 0.7.0
	 * @since 1.0 Moved to AMP_HTTP class. Extracted some logic to send_cors_headers method.
	 */
	public static function handle_xhr_request() {
		$is_amp_xhr = (
			! empty( self::$purged_amp_query_vars[ self::ACTION_XHR_CONVERTED_QUERY_VAR ] )
			&&
			( ! empty( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] )
		);
		if ( ! $is_amp_xhr ) {
			return;
		}

		// Intercept POST requests which redirect.
		add_filter( 'wp_redirect', [ __CLASS__, 'intercept_post_request_redirect' ], PHP_INT_MAX );

		// Add special handling for redirecting after comment submission.
		add_filter( 'comment_post_redirect', [ __CLASS__, 'filter_comment_post_redirect' ], PHP_INT_MAX, 2 );

		// Add die handler for AMP error display, most likely due to problem with comment.
		$handle_wp_die = static function () {
			return [ __CLASS__, 'handle_wp_die' ];
		};
		add_filter( 'wp_die_json_handler', $handle_wp_die );
		add_filter( 'wp_die_handler', $handle_wp_die ); // Needed for WP<5.1.
	}

	/**
	 * Intercept the response to a POST request.
	 *
	 * @since 0.7.0
	 * @since 1.0 Moved to AMP_HTTP class.
	 * @see wp_redirect()
	 *
	 * @param string $location The location to redirect to.
	 */
	public static function intercept_post_request_redirect( $location ) {

		// Make sure relative redirects get made absolute.
		$parsed_location = array_merge(
			[
				'scheme' => 'https',
				'host'   => wp_parse_url( home_url(), PHP_URL_HOST ),
				'path'   => isset( $_SERVER['REQUEST_URI'] ) ? strtok( wp_unslash( $_SERVER['REQUEST_URI'] ), '?' ) : '/',
			],
			wp_parse_url( $location )
		);

		$absolute_location = '';
		if ( 'https' === $parsed_location['scheme'] ) {
			$absolute_location .= $parsed_location['scheme'] . ':';
		}
		$absolute_location .= '//' . $parsed_location['host'];
		if ( isset( $parsed_location['port'] ) ) {
			$absolute_location .= ':' . $parsed_location['port'];
		}
		$absolute_location .= $parsed_location['path'];
		if ( isset( $parsed_location['query'] ) ) {
			$absolute_location .= '?' . $parsed_location['query'];
		}
		if ( isset( $parsed_location['fragment'] ) ) {
			$absolute_location .= '#' . $parsed_location['fragment'];
		}

		self::send_header( 'AMP-Redirect-To', $absolute_location );
		self::send_header( 'Access-Control-Expose-Headers', 'AMP-Redirect-To', [ 'replace' => false ] );

		wp_send_json(
			[
				'message'     => __( 'Redirecting…', 'amp' ),
				'redirecting' => true, // Make sure that the submit-success doesn't get styled as success since redirection _could_ be to error page.
			],
			200
		);
	}

	/**
	 * New error handler for AMP form submission.
	 *
	 * @since 0.7.0
	 * @since 1.0 Moved to AMP_HTTP class.
	 * @see wp_die()
	 *
	 * @param WP_Error|string  $error The error to handle.
	 * @param string|int       $title Optional. Error title. If `$message` is a `WP_Error` object,
	 *                                error data with the key 'title' may be used to specify the title.
	 *                                If `$title` is an integer, then it is treated as the response
	 *                                code. Default empty.
	 * @param string|array|int $args {
	 *     Optional. Arguments to control behavior. If `$args` is an integer, then it is treated
	 *     as the response code. Default empty array.
	 *
	 *     @type int $response The HTTP response code. Default 200 for Ajax requests, 500 otherwise.
	 * }
	 * @global string $pagenow
	 */
	public static function handle_wp_die( $error, $title = '', $args = [] ) {
		global $pagenow;
		if ( is_int( $title ) ) {
			$status_code = $title;
		} elseif ( is_int( $args ) ) {
			$status_code = $args;
		} elseif ( is_array( $args ) && isset( $args['response'] ) ) {
			$status_code = $args['response'];
		} else {
			$status_code = 500;
		}

		/*
		 * Handle apparent defect in core where invalid comment form submissions return with a 200 status code.
		 * Successful requests to wp-comments-post.php should always end up doing a redirect after applying the
		 * comment_post_redirect filter, and as such the \AMP_HTTP::filter_comment_post_redirect() method will
		 * ensure that redirect works in AMP. When there is no comment_post_redirect then the alternative is a wp_die()
		 * scenario which should always be considered an error. This workaround is important because otherwise an error
		 * case will get rendered unexpectedly in the div[submit-success] element, when it should be rendered in the
		 * div[submit-error] element. For a fix to the core defect which will make this unnecessary,
		 * see <https://core.trac.wordpress.org/ticket/47393>.
		 */
		if ( 200 === $status_code && isset( $pagenow ) && 'wp-comments-post.php' === $pagenow ) {
			$status_code = 400;
		}

		if ( is_wp_error( $error ) ) {
			$error = $error->get_error_message();
		}

		// Message will be shown in template defined by AMP_Theme_Support::amend_comment_form().
		wp_send_json(
			[
				'message' => amp_wp_kses_mustache( $error ),
			],
			$status_code
		);
	}

	/**
	 * Handle comment_post_redirect to ensure page reload is done when comments_live_list is not supported, while sending back a success message when it is.
	 *
	 * @since 0.7.0
	 * @since 1.0 Moved to AMP_HTTP class.
	 *
	 * @param string     $url     Comment permalink to redirect to.
	 * @param WP_Comment $comment Posted comment.
	 *
	 * @return string|null URL if redirect to be done; otherwise function will exist.
	 */
	public static function filter_comment_post_redirect( $url, $comment ) {
		$theme_support = AMP_Theme_Support::get_theme_support_args();

		// Cause a page refresh if amp-live-list is not implemented for comments via add_theme_support( AMP_Theme_Support::SLUG, array( 'comments_live_list' => true ) ).
		if ( empty( $theme_support['comments_live_list'] ) ) {
			/*
			 * Add the comment ID to the URL to force AMP to refresh the page.
			 * This is ideally a temporary workaround to deal with https://github.com/ampproject/amphtml/issues/14170
			 */
			$url = add_query_arg( 'comment', $comment->comment_ID, $url );

			// Pass URL along to wp_redirect().
			return $url;
		}

		// Create a success message to display to the user.
		if ( '1' === (string) $comment->comment_approved ) {
			$message = __( 'Your comment has been posted.', 'amp' );
		} else {
			$message = __( 'Your comment is awaiting moderation.', 'amp' );
		}

		/**
		 * Filters the message when comment submitted success message when
		 *
		 * @since 0.7
		 */
		$message = apply_filters( 'amp_comment_posted_message', $message, $comment );

		// Message will be shown in template defined by AMP_Theme_Support::amend_comment_form().
		wp_send_json(
			[
				'message' => amp_wp_kses_mustache( $message ),
			],
			200
		);

		return null;
	}

	/**
	 * Get the Content-Type for the response.
	 *
	 * @since 1.2
	 *
	 * @return string Content type.
	 */
	public static function get_response_content_type() {
		$content_type = ini_get( 'default_mimetype' );
		foreach ( headers_list() as $header ) {
			list( $name, $value ) = explode( ':', $header, 2 );
			if ( 'content-type' === strtolower( $name ) ) {
				$content_type = trim( $value );
				break;
			}
		}
		return $content_type;
	}
}

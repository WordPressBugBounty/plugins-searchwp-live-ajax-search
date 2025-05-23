<?php

use SearchWP_Live_Search_Utils as Utils;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SearchWP_Live_Search_Form.
 *
 * The SearchWP Live Ajax Search search-form and it's configuration
 *
 * @since 1.0
 */
class SearchWP_Live_Search_Form {

	/**
	 * The default configuration.
	 *
	 * Developers can add their own configs using the searchwp_live_search_configs filter which is applied at runtime.
	 * You are responsible for keeping the $configs array intact, and either substituting your own customizations in
	 * the existing data, or adding your own by appending your own array key with values based on the default
	 *
	 * To use: set the data-swpconfig attribute value on your search form input to be the config you want to use
	 *
	 * @since 1.0
	 *
	 * @var array All configurations available for use at runtime
	 */
	public $configs = [
		'default' => [                      // 'default' config
			'engine'  => 'default',         // Search engine to use (if SearchWP is available).
			'input'   => [
				'delay'     => 300,         // Impose delay (in milliseconds) before firing a search.
				'min_chars' => 3,           // Wait for at least 3 characters before triggering a search.
			],
			'results' => [
				'position' => 'bottom',     // Where to position the results (bottom|top).
				'width'    => 'auto',       // Whether the width should automatically match the input (auto|css).
				'offset'   => [
					'x' => 0,               // X offset (in pixels).
					'y' => 5,               // Y offset (in pixels).
				],
			],
            'spinner' => [                                          // Powered by https://spin.js.org/.
                'lines'     => 12,                                  // The number of lines to draw.
                'length'    => 8,                                   // The length of each line.
                'width'     => 3,                                   // The line thickness.
                'radius'    => 8,                                   // The radius of the inner circle.
                'scale'     => 1,                                   // Scales overall size of the spinner.
                'corners'   => 1,                                   // Corner roundness (0..1).
                'color'     => '#424242',                           // CSS color or array of colors.
                'fadeColor' => 'transparent',                       // CSS color or array of colors.
                'speed'     => 1,                                   // Rounds per second.
                'rotate'    => 0,                                   // The rotation offset.
                'animation' => 'searchwp-spinner-line-fade-quick',  // The CSS animation name for the lines.
                'direction' => 1,                                   // 1: clockwise, -1: counterclockwise
                'zIndex'    => 2e9,                                 // The z-index (defaults to 2000000000).
                'className' => 'spinner',                           // The CSS class to assign to the spinner.
                'top'       => '50%',                               // Top position relative to parent.
                'left'      => '50%',                               // Left position relative to parent.
                'shadow'    => '0 0 1px transparent',               // Box-shadow for the lines.
                'position'  => 'absolute',                          // Element positioning.
			],
		],
	];

	/**
	 * Equivalent of __construct() — implement our hooks.
	 *
	 * @since 1.0
	 *
	 * @uses add_action() to trigger asset enqueue and output base styles in the footer
	 * @uses add_filter() to filter search forms generated by get_search_form()
	 * @uses apply_filters() to ensure developer can filter the configs array via searchwp_live_search_configs filter
	 */
	public function setup() {

        $this->apply_settings();

		$this->hooks();

		/**
		 * Filter to allow developers to add their own custom configurations.
		 * The configs store all the various configuration arrays that can be used at runtime.
		 *
		 * @since 1.0
		 *
		 * @param array $configs The default configurations.
		 */
		$this->configs = apply_filters( 'searchwp_live_search_configs', $this->configs );
	}

	/**
	 * Hooks.
	 *
	 * @since 1.7.0
	 */
    private function hooks() {

	    add_action( 'wp_enqueue_scripts', [ $this, 'assets' ] );

	    add_filter( 'get_search_form', [ $this, 'get_search_form' ], 999, 1 );
	    add_action( 'wp_footer', [ $this, 'base_styles' ] );

	    // Gutenberg integration.
	    add_action( 'wp_footer', [ $this, 'gutenberg_integration' ] );
    }

	/**
     * Apply settings to the form.
     *
	 * @since 1.7.0
	 */
	private function apply_settings() {

		$settings_api = searchwp_live_search()->get( 'Settings_Api' );

		if ( ! $settings_api->get( 'enable-live-search' ) ) {
            add_filter( 'searchwp_live_search_hijack_get_search_form', '__return_false' );
            add_filter( 'searchwp_live_search_hijack_search_form_block', '__return_false' );
		}

		$include_css = $settings_api->get( 'include-frontend-css' );

		if ( $include_css === 'position' ) {
			$this->dequeue_styles();
		}

		if ( $include_css === 'none' ) {
			$this->dequeue_styles();
			$this->dequeue_base_styles();
		}

		$this->configs['default']['results']['position'] = $settings_api->get( 'results-pane-position' );
		$this->configs['default']['results']['width']    = empty( $settings_api->get( 'results-pane-auto-width' ) ) ? 'css' : 'auto';

		$min_chars = $settings_api->get( 'swp-min-chars' );
		if ( ! empty( $min_chars ) ) {
			$this->configs['default']['input']['min_chars'] = absint( $min_chars );
		}
	}

	/**
	 * Dequeue visual form styles.
	 *
	 * @since 1.7.0
	 */
	private function dequeue_styles() {

		add_action(
			'wp_enqueue_scripts',
			function () {
				wp_dequeue_style( 'searchwp-live-search' );
			},
			20
		);
	}

	/**
	 * Dequeue base (positioning) form styles.
	 *
	 * @since 1.7.0
	 */
	private function dequeue_base_styles() {

		add_filter( 'searchwp_live_search_base_styles', '__return_false' );
	}

	/**
	 * Take over search blocks by adding body class.
	 *
	 * @since 1.0
	 */
	public function gutenberg_integration() {

		/**
		 * Filter to allow disabling the hijack of search blocks.
		 *
		 * @since 1.0
		 *
		 * @param bool $apply Whether to apply the hijack.
		 */
		if ( ! apply_filters( 'searchwp_live_search_hijack_search_form_block', true ) ) {
            return;
        }

		/**
		 * Filter to set the default SearchWP search engine for search forms.
		 *
		 * @since 1.0
		 *
		 * @param string $engine The default SearchWP search engine.
		 */
        $engine = apply_filters( 'searchwp_live_search_get_search_form_engine', 'default' );

		/**
		 * Filter to set the default config to use for search forms.
		 *
		 * @since 1.0
		 *
		 * @param string $config The default config to use.
		 */
        $config = apply_filters( 'searchwp_live_search_get_search_form_config', 'default' );

        // Allow for block-specific.

		/**
		 * Filter to set the default SearchWP search engine for search blocks.
		 *
		 * @since 1.0
		 *
		 * @param string $engine The default SearchWP search engine.
		 */
        $engine = apply_filters( 'searchwp_live_search_get_search_form_engine_blocks', $engine );

		/**
		 * Filter to set the default config to use for search blocks.
		 *
		 * @since 1.0
		 *
		 * @param string $config The default config to use.
		 */
        $config = apply_filters( 'searchwp_live_search_get_search_form_config_blocks', $config );

        ?>
        <script>
            var _SEARCHWP_LIVE_AJAX_SEARCH_BLOCKS = true;
            var _SEARCHWP_LIVE_AJAX_SEARCH_ENGINE = '<?php echo esc_js( $engine ); ?>';
            var _SEARCHWP_LIVE_AJAX_SEARCH_CONFIG = '<?php echo esc_js( $config ); ?>';
        </script>
        <?php
	}

	/**
	 * Register, localize, and enqueue all necessary JavaScript and CSS.
	 *
	 * @since 1.0
	 *
	 * @uses wp_enqueue_style() to enqueue CSS
	 * @uses wp_enqueue_script() to enqueue JavaScript
	 * @uses wp_register_script() to register JavaScript
	 * @uses wp_localize_script() to pass PHP variables to JavaScript at runtime
	 * @uses json_encode() to prepare the (potentially filtered) configs array
	 */
	public function assets() {

		// If WP is in script debug, or we pass ?script_debug in a URL - set debug to true.
		$debug = Utils::get_debug_assets_suffix();

		wp_enqueue_style(
			'searchwp-live-search',
			SEARCHWP_LIVE_SEARCH_PLUGIN_URL . "assets/styles/style{$debug}.css",
			null,
			SEARCHWP_LIVE_SEARCH_VERSION
		);

		wp_enqueue_script( 'jquery' );

		wp_register_script(
			'swp-live-search-client',
			SEARCHWP_LIVE_SEARCH_PLUGIN_URL . "assets/javascript/dist/script{$debug}.js",
			[ 'jquery' ],
			SEARCHWP_LIVE_SEARCH_VERSION,
			true
		);

		$ajaxurl = admin_url( 'admin-ajax.php' );

		/**
		 * Filter to allow direct search (e.g. avoid admin-ajax.php).
		 *
		 * @since 1.7.0
		 *
		 * @param bool $direct_search Whether to use direct search.
		 */
		if ( apply_filters( 'searchwp_live_search_direct_search', false ) ) {
			$ajaxurl = SEARCHWP_LIVE_SEARCH_PLUGIN_URL . 'direct.php';
		}

		// Set up our parameters.
		$params = [
			'ajaxurl'             => esc_url( $ajaxurl ),
			'origin_id'           => get_queried_object_id(),
			'config'              => $this->configs,
			'msg_no_config_found' => esc_html__( 'No valid SearchWP Live Search configuration found!', 'searchwp-live-ajax-search' ),
			'aria_instructions'   => esc_html__( 'When autocomplete results are available use up and down arrows to review and enter to go to the desired page. Touch device users, explore by touch or with swipe gestures.' , 'searchwp-live-ajax-search' ),
		];

		// We need to JSON encode the configs.
		$encoded_data = [
			'l10n_print_after' => 'searchwp_live_search_params = ' . wp_json_encode( $params ) . ';',
		];

		// Localize and enqueue the script with all the variable goodness.
		wp_localize_script( 'swp-live-search-client', 'searchwp_live_search_params', $encoded_data );
		wp_enqueue_script( 'swp-live-search-client' );

		// Add inline styles.
		wp_add_inline_style( 'searchwp-live-search', self::get_inline_styles() );
	}

	/**
	 * Get inline styles.
	 *
	 * @since 1.8.0
	 *
	 * @return string
	 */
	private static function get_inline_styles() {

		$settings = searchwp_live_search()->get( 'Settings_Api' )->get();

		$css_array = [];

		// Title.
		$title_selector = '.searchwp-live-search-result .searchwp-live-search-result--title a';
		if ( ! empty( $settings['swp-title-color'] ) ) {
			self::set_css( $css_array, "{$title_selector}/color", esc_html( $settings['swp-title-color'] ), '/' );
		}
		if ( ! empty( $settings['swp-title-font-size'] ) ) {
			self::set_css( $css_array, "{$title_selector}/font-size", absint( $settings['swp-title-font-size'] ) . 'px', '/' );
		}

		// E-Commerce Price.
		$price_selector = '.searchwp-live-search-result .searchwp-live-search-result--price';
		if ( ! empty( $settings['swp-price-color'] ) ) {
			self::set_css( $css_array, "{$price_selector}/color", esc_html( $settings['swp-price-color'] ), '/' );
		}
		if ( ! empty( $settings['swp-price-font-size'] ) ) {
			self::set_css( $css_array, "{$price_selector}/font-size", absint( $settings['swp-price-font-size'] ) . 'px', '/' );
		}

		// E-Commerce Add to Cart.
		$add_to_cart_selector = '.searchwp-live-search-result .searchwp-live-search-result--add-to-cart .button';
		if ( ! empty( $settings['swp-add-to-cart-background-color'] ) ) {
			self::set_css( $css_array, "{$add_to_cart_selector}/background-color", esc_html( $settings['swp-add-to-cart-background-color'] ), '/' );
		}
		if ( ! empty( $settings['swp-add-to-cart-font-color'] ) ) {
			self::set_css( $css_array, "{$add_to_cart_selector}/color", esc_html( $settings['swp-add-to-cart-font-color'] ), '/' );
		}
		if ( ! empty( $settings['swp-add-to-cart-font-size'] ) ) {
			self::set_css( $css_array, "{$add_to_cart_selector}/font-size", absint( $settings['swp-add-to-cart-font-size'] ) . 'px', '/' );
		}

		return self::css_array_to_css( $css_array );
	}

	/**
	 * Set an array item to a given value using "dot" notation.
	 *
	 * If no key is given to the method, the entire array will be replaced.
	 *
	 * @since 1.8.0
	 *
	 * @param array       $data          The array to modify.
	 * @param string|null $key           The key to set.
	 * @param mixed       $value         The value to set.
	 * @param string      $key_separator The key separator.
	 *
	 * @return array
	 */
	public static function set_css( &$data, $key, $value, $key_separator = '.' ) {

		if ( is_null( $key ) ) {
			$data = $value;

			return $data;
		}

		$keys = explode( $key_separator, $key );

		foreach ( $keys as $i => $_key ) {
			if ( count( $keys ) === 1 ) {
				break;
			}

			unset( $keys[ $i ] );

			// If the key doesn't exist at this depth, we will just create an empty array
			// to hold the next value, allowing us to create the arrays to hold final
			// values at the correct depth. Then we'll keep digging into the array.
			if ( ! isset( $data[ $_key ] ) || ! is_array( $data[ $_key ] ) ) {
				$data[ $_key ] = [];
			}

			$data = &$data[ $_key ];
		}

		$data[ array_shift( $keys ) ] = $value;

		return $data;
	}

	/**
	 * Recursive function that generates from a multidimensional array of CSS rules, a valid CSS string.
	 *
	 * @since 1.8.0
	 *
	 * @param array $rules  CSS rules array.
	 *   An array of CSS rules in the form of:
	 *   array('selector'=>array('property' => 'value')). Also supports selector
	 *   nesting, e.g.,
	 *   array('selector' => array('selector'=>array('property' => 'value'))).
	 * @param int   $indent Indentation level.
	 *
	 * @return string A CSS string of rules. This is not wrapped in <style> tags.
	 * @source http://matthewgrasmick.com/article/convert-nested-php-array-css-string
	 */
	private static function css_array_to_css( $rules, $indent = 0 ) {

		$css    = '';
		$prefix = str_repeat( '  ', $indent );

		foreach ( $rules as $key => $value ) {
			if ( is_array( $value ) ) {
				$selector   = $key;
				$properties = $value;

				$css .= $prefix . "$selector {\n";
				$css .= $prefix . self::css_array_to_css( $properties, $indent + 1 );
				$css .= $prefix . "}\n";
			} else {
				$property = $key;
				$css     .= $prefix . "$property: $value;\n";
			}
		}

		return $css;
	}

	/**
	 * Callback to the get_search_form filter, allows us to automagically enable live search on form fields
	 * generated using get_search_form().
	 *
	 * @since 1.0
	 *
	 * @param string $html The generated markup for the search form.
	 *
	 * @uses apply_filters() to allow devs to disable this functionality
	 * @uses apply_filters() to allow devs to set the default SearchWP search engine
	 * @uses apply_filters() to allow devs to set the default config to use
	 * @uses str_replace() to inject our HTML5 data attributes where we want them
	 * @uses esc_attr() to escape the search engine and config name
	 *
	 * @return string Markup for the search form
	 */
	public function get_search_form( $html ) {

		/**
		 * Filter to allow disabling the hijack of get_search_form().
		 *
		 * @since 1.0
		 *
		 * @param bool $apply Whether to apply the hijack.
		 */
		if ( ! apply_filters( 'searchwp_live_search_hijack_get_search_form', true ) ) {
			return $html;
		}

		/**
		 * Filter to allow developers to set the default SearchWP search engine.
		 *
		 * @since 1.0
		 *
		 * @param string $engine The default SearchWP search engine.
		 */
		$engine = apply_filters( 'searchwp_live_search_get_search_form_engine', 'default' );

		/**
		 * Filter to allow developers to set the default config to use.
		 *
		 * @since 1.0
		 *
		 * @param string $config The default config to use.
		 */
		$config = apply_filters( 'searchwp_live_search_get_search_form_config', 'default' );
		// We're going to use 'name="s"' as our anchor for replacement.
		$html = str_replace( 'name="s"', 'name="s" data-swplive="true" data-swpengine="' . esc_attr( $engine ) . '" data-swpconfig="' . esc_attr( $config ) . '"', $html );

		return $html;
	}

	/**
	 * Output the base styles (absolutely minimal) necessary to properly set up the results wrapper.
	 *
	 * @since 1.0
	 *
	 * @uses apply_filters() to allow devs to disable this functionality
	 */
	public function base_styles() {

		/**
		 * Filter to allow disabling the base styles.
		 *
		 * @since 1.0
		 *
		 * @param bool $apply Whether to apply the base styles.
		 */
		if ( ! apply_filters( 'searchwp_live_search_base_styles', true ) ) {
            return;
        }

        ?>
        <style>
            .searchwp-live-search-results {
                opacity: 0;
                transition: opacity .25s ease-in-out;
                -moz-transition: opacity .25s ease-in-out;
                -webkit-transition: opacity .25s ease-in-out;
                height: 0;
                overflow: hidden;
                z-index: 9999995; /* Exceed SearchWP Modal Search Form overlay. */
                position: absolute;
                display: none;
            }

            .searchwp-live-search-results-showing {
                display: block;
                opacity: 1;
                height: auto;
                overflow: auto;
            }

            .searchwp-live-search-no-results {
                padding: 3em 2em 0;
                text-align: center;
            }

            .searchwp-live-search-no-min-chars:after {
                content: "<?php esc_attr_e( 'Continue typing', 'searchwp-live-ajax-search' ); ?>";
                display: block;
                text-align: center;
                padding: 2em 2em 0;
            }
        </style>
        <?php
	}
}

<?php
/**
 * Online Payment Platform Gateway
 *
 * @package   online-payment-platform-gateway
 * @author    Ganga Kafle <kafleg.work@gmail.com>
 * @copyright 2023 Online Payment Platform Gateway
 * @license   MIT
 * @link      https://the-dev-company.com
 */

declare( strict_types = 1 );

namespace OnlinePaymentPlatformGateway\App\Frontend;

use OnlinePaymentPlatformGateway\Common\Abstracts\Base;

/**
 * Class Templates
 *
 * @package OnlinePaymentPlatformGateway\App\Frontend
 * @since 1.0.0
 */
class Templates extends Base {
	/**
	 * Internal use only: Store located template paths.
	 *
	 * @var array
	 */
	private $path_cache = array();

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		/**
		 * This frontend class is only being instantiated in the frontend as requested in the Bootstrap class
		 *
		 * @see Requester::isFrontend()
		 * @see Bootstrap::__construct
		 *
		 * Add plugin code here for template specific functions
		 */
	}

	/**
	 * Retrieves the template file path for a given slug and name, and optionally loads it.
	 *
	 * @param string      $slug The template slug.
	 * @param string|null $name The template variation name (optional).
	 * @param array       $args Additional arguments for template retrieval (optional).
	 * @param bool        $load Whether to load the located template file (optional, default: true).
	 * @return string|bool The located template file path if found, or false if not found.
	 */
	public function get( $slug, $name = null, $args = array(), $load = true ): string {
		// Execute code for this part.
		do_action( 'get_template_part_' . $slug, $slug, $name, $args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'online_payment_platform_gateway_get_template_part_' . $slug, $slug, $name, $args );
		// Get files names of templates, for given slug and name.
		$templates = $this->getFileNames( $slug, $name, $args );
		// Return the part that is found.
		return $this->locate( $templates, $load, false, $args );
	}

	/**
	 * Retrieves an array of file names for a given template slug, name, and arguments.
	 *
	 * @param string      $slug The template slug.
	 * @param string|null $name The template variation name (optional).
	 * @param array       $args Additional arguments for template retrieval.
	 * @return array An array of template file names in the order of most specific to least specific.
	 */
	protected function getFileNames( $slug, $name, $args ): array {
		$templates = array();
		if ( isset( $name ) ) {
			$templates[] = $slug . '-' . $name . '.php';
		}
		$templates[] = $slug . '.php';
		/**
		 * Allow template choices to be filtered.
		 *
		 * The resulting array should be in the order of most specific first, to least specific last.
		 * e.g. 0 => recipe-instructions.php, 1 => recipe.php
		 *
		 * @param array $templates Names of template files that should be looked for, for given slug and name.
		 * @param string $slug Template slug.
		 * @param string $name Template variation name.
		 * @since 1.0.0
		 */
		return apply_filters( 'online_payment_platform_gateway_get_template_part', $templates, $slug, $name, $args );
	}

	/**
	 * Locates a template file based on the provided template names and loads it if specified.
	 *
	 * @param string|string[] $template_names The template names to locate.
	 * @param bool            $load Whether to load the located template file (optional, default: false).
	 * @param bool            $require_once Whether to require the located template file only once (optional, default: true).
	 * @param array           $args Additional arguments for template loading (optional).
	 * @return string|bool The located template file path if found, or false if not found.
	 */
	public function locate( $template_names, $load = false, $require_once = true, $args = array() ): string {
		// Use $template_names as a cache key - either first element of array or the variable itself if it's a string.
		$cache_key = is_array( $template_names ) ? $template_names[0] : $template_names;
		// If the key is in the cache array, we've already located this file.
		if ( isset( $this->path_cache[ $cache_key ] ) ) {
			$located = $this->path_cache[ $cache_key ];
		} else {
			// No file found yet.
			$located = false;
			// Remove empty entries.
			$template_names = array_filter( (array) $template_names );
			$template_paths = $this->getPaths();
			// Try to find a template file.
			foreach ( $template_names as $template_name ) {
				// Trim off any slashes from the template name.
				$template_name = ltrim( $template_name, '/' );
				// Try locating this template file by looping through the template paths.
				foreach ( $template_paths as $template_path ) {
					if ( file_exists( $template_path . $template_name ) ) {
						$located = $template_path . $template_name;
						// Store the template path in the cache.
						$this->path_cache[ $cache_key ] = $located;
						break 2;
					}
				}
			}
		}
		if ( $load && $located ) {
			load_template( $located, $require_once, $args );
		}
		return $located;
	}

	/**
	 * Return a list of paths to check for template locations, modified version of:
	 *
	 * @url https://github.com/GaryJones/Gamajo-Template-Loader
	 *
	 * Default is to check in a child theme (if relevant) before a parent theme, so that themes which inherit from a
	 * parent theme can just overload one file. If the template is not found in either of those, it looks in the
	 * theme-compat folder last.
	 *
	 * @return mixed|void
	 * @since 1.0.0
	 */
	protected function getPaths(): array {
		$theme_directory = trailingslashit( $this->plugin->extTemplateFolder() );

		$file_paths = array(
			10  => trailingslashit( get_template_directory() ) . $theme_directory,
			100 => $this->plugin->templatePath(),
		);
		// Only add this conditionally, so non-child themes don't redundantly check active theme twice.
		if ( get_stylesheet_directory() !== get_template_directory() ) {
			$file_paths[1] = trailingslashit( get_stylesheet_directory() ) . $theme_directory;
		}
		/**
		 * Allow ordered list of template paths to be amended.
		 *
		 * @param array $var Default is directory in child theme at index 1, parent theme at 10, and plugin at 100.
		 * @since 1.0.0
		 */
		$file_paths = apply_filters( 'online_payment_platform_gateway_template_paths', $file_paths );
		// Sort the file paths based on priority.
		ksort( $file_paths, SORT_NUMERIC );
		return array_map( 'trailingslashit', $file_paths );
	}
}

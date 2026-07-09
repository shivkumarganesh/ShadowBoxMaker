<?php
/**
 * Self-hosted update checker (GitHub-backed).
 *
 * @package WCBarcodePro\Updater
 */

namespace WCBarcodePro\Updater;

defined( 'ABSPATH' ) || exit;

class PluginUpdater {

	private static ?PluginUpdater $instance = null;

	private const METADATA_URL = 'https://raw.githubusercontent.com/shivkumarganesh/ShadowBoxMaker/main/releases/update-info.json';
	private const CACHE_KEY     = 'wcbp_update_check';
	private const CACHE_TTL     = 12 * HOUR_IN_SECONDS;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api_details' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'purge_cache' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( $this, 'filter_row_meta' ), 10, 2 );
	}

	private function plugin_basename(): string {
		return plugin_basename( WCBP_PLUGIN_FILE );
	}

	private function get_remote_metadata(): ?array {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$response = wp_remote_get( self::METADATA_URL, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::CACHE_KEY, array(), 5 * MINUTE_IN_SECONDS );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['version'] ) ) {
			set_transient( self::CACHE_KEY, array(), 5 * MINUTE_IN_SECONDS );
			return null;
		}

		set_transient( self::CACHE_KEY, $body, self::CACHE_TTL );
		return $body;
	}

	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$metadata = $this->get_remote_metadata();
		if ( null === $metadata ) {
			return $transient;
		}

		$basename = $this->plugin_basename();

		if ( version_compare( $metadata['version'], WCBP_VERSION, '>' ) ) {
			$item = (object) array(
				'id'            => $basename,
				'slug'          => 'woo-barcode-pro',
				'plugin'        => $basename,
				'new_version'   => $metadata['version'],
				'url'           => $metadata['homepage'] ?? '',
				'package'       => $metadata['download_url'] ?? '',
				'icons'         => array(),
				'banners'       => array(),
				'banners_rtl'   => array(),
				'tested'        => $metadata['tested'] ?? '',
				'requires'      => $metadata['requires'] ?? '',
				'requires_php'  => $metadata['requires_php'] ?? '',
				'compatibility' => new \stdClass(),
			);

			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}
			$transient->response[ $basename ] = $item;
			unset( $transient->no_update[ $basename ] );
		} else {
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$transient->no_update[ $basename ] = (object) array(
				'id'     => $basename,
				'slug'   => 'woo-barcode-pro',
				'plugin' => $basename,
				'new_version' => WCBP_VERSION,
			);
			unset( $transient->response[ $basename ] );
		}

		return $transient;
	}

	public function plugins_api_details( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'woo-barcode-pro' !== $args->slug ) {
			return $result;
		}

		$metadata = $this->get_remote_metadata();
		if ( null === $metadata ) {
			return $result;
		}

		return (object) array(
			'name'          => $metadata['name'] ?? 'WooBarcode Pro',
			'slug'          => 'woo-barcode-pro',
			'version'       => $metadata['version'],
			'author'        => $metadata['author'] ?? '',
			'homepage'      => $metadata['homepage'] ?? '',
			'requires'      => $metadata['requires'] ?? '',
			'tested'        => $metadata['tested'] ?? '',
			'requires_php'  => $metadata['requires_php'] ?? '',
			'last_updated'  => $metadata['last_updated'] ?? '',
			'download_link' => $metadata['download_url'] ?? '',
			'sections'       => array(
				'description' => $metadata['sections']['description'] ?? '',
				'changelog'   => $metadata['sections']['changelog'] ?? '',
			),
		);
	}

	public function purge_cache( $upgrader, $options ): void {
		if ( isset( $options['action'], $options['type'] ) && 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			delete_transient( self::CACHE_KEY );
		}
	}

	public function filter_row_meta( array $plugin_meta, string $plugin_file ): array {
		if ( $this->plugin_basename() === $plugin_file ) {
			$metadata = $this->get_remote_metadata();
			if ( null !== $metadata && ! empty( $metadata['homepage'] ) ) {
				$plugin_meta[] = '<a href="' . esc_url( $metadata['homepage'] ) . '" target="_blank">' . esc_html__( 'Changelog', 'woo-barcode-pro' ) . '</a>';
			}
		}
		return $plugin_meta;
	}
}

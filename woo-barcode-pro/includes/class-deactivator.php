<?php
/**
 * Plugin deactivation.
 *
 * @package WCBarcodePro
 */

namespace WCBarcodePro;

defined( 'ABSPATH' ) || exit;

class Deactivator {
	public static function deactivate(): void {
		delete_transient( 'wcbp_just_activated' );
	}
}

<?php
/**
 * REST API endpoints under /wcbp/v1/.
 *
 * @package WCBarcodePro\REST
 */

namespace WCBarcodePro\REST;

defined( 'ABSPATH' ) || exit;

class RestApi {

	private static ?RestApi $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		$ns = 'wcbp/v1';

		// GET /wcbp/v1/barcode/{product_id}
		register_rest_route( $ns, '/barcode/(?P<id>[\\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_barcode' ),
			'permission_callback' => array( $this, 'can_read' ),
			'args'                => array(
				'id'           => array( 'type' => 'integer', 'required' => true ),
				'variation_id' => array( 'type' => 'integer', 'default' => 0 ),
				'format'       => array( 'type' => 'string',  'default' => 'svg', 'enum' => array( 'svg', 'data_uri' ) ),
			),
		) );

		// GET /wcbp/v1/queue
		register_rest_route( $ns, '/queue', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_queue' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args'                => array(
				'status' => array( 'type' => 'string', 'default' => 'pending' ),
			),
		) );

		// POST /wcbp/v1/queue
		register_rest_route( $ns, '/queue', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'add_to_queue' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args'                => array(
				'product_id'        => array( 'type' => 'integer', 'required' => true ),
				'quantity'          => array( 'type' => 'integer', 'default' => 1 ),
				'variation_id'      => array( 'type' => 'integer', 'default' => 0 ),
				'label_template_id' => array( 'type' => 'integer', 'default' => 0 ),
				'order_id'          => array( 'type' => 'integer', 'default' => 0 ),
			),
		) );

		// DELETE /wcbp/v1/queue/{id}
		register_rest_route( $ns, '/queue/(?P<id>[\\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'remove_from_queue' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args'                => array(
				'id' => array( 'type' => 'integer', 'required' => true ),
			),
		) );

		// GET /wcbp/v1/templates/label
		register_rest_route( $ns, '/templates/label', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_label_templates' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );

		// GET /wcbp/v1/templates/price
		register_rest_route( $ns, '/templates/price', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_price_templates' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
	}

	// ── Permission callbacks ───────────────────────────────────────────────────

	public function can_read(): bool {
		// Public read (barcode SVG) allowed only if front-end display is on.
		$s = \WCBarcodePro\wcbp_settings();
		return $s['show_single'] || \WCBarcodePro\wcbp_current_user_can_manage();
	}

	public function can_manage(): bool {
		return \WCBarcodePro\wcbp_current_user_can_manage();
	}

	// ── Route handlers ────────────────────────────────────────────────────────

	public function get_barcode( \WP_REST_Request $request ): \WP_REST_Response {
		$product_id   = (int) $request['id'];
		$variation_id = (int) $request['variation_id'];
		$format       = $request['format'];

		$gen   = \WCBarcodePro\Barcode\BarcodeGenerator::get_instance();
		$value = \WCBarcodePro\wcbp_barcode_value( $product_id, $variation_id );
		$s     = \WCBarcodePro\wcbp_settings();
		$type  = $s['symbology'];

		if ( 'data_uri' === $format ) {
			$out = $gen->generate_data_uri( $value, $type );
		} else {
			$out = $gen->generate_svg( $value, $type );
		}

		return rest_ensure_response( array(
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'barcode'      => $value,
			'format'       => $format,
			'output'       => $out,
		) );
	}

	public function get_queue( \WP_REST_Request $request ): \WP_REST_Response {
		$status = sanitize_key( $request['status'] );
		$items  = \WCBarcodePro\Admin\PrintQueue::get_instance()->get_all( $status );
		return rest_ensure_response( $items );
	}

	public function add_to_queue( \WP_REST_Request $request ): \WP_REST_Response {
		$queue = \WCBarcodePro\Admin\PrintQueue::get_instance();
		$ok    = $queue->add(
			(int) $request['product_id'],
			max( 1, (int) $request['quantity'] ),
			(int) $request['variation_id'],
			(int) $request['label_template_id'],
			(int) $request['order_id']
		);
		return rest_ensure_response( array( 'added' => $ok, 'count' => $queue->get_count() ) );
	}

	public function remove_from_queue( \WP_REST_Request $request ): \WP_REST_Response {
		$ok = \WCBarcodePro\Admin\PrintQueue::get_instance()->remove( (int) $request['id'] );
		return rest_ensure_response( array( 'removed' => $ok ) );
	}

	public function get_label_templates( \WP_REST_Request $request ): \WP_REST_Response {
		$rows = \WCBarcodePro\Admin\LabelTemplates::get_instance()->get_all();
		foreach ( $rows as &$row ) {
			$row['fields'] = json_decode( $row['fields'], true );
		}
		return rest_ensure_response( $rows );
	}

	public function get_price_templates( \WP_REST_Request $request ): \WP_REST_Response {
		$rows = \WCBarcodePro\Admin\PriceTemplates::get_instance()->get_all();
		foreach ( $rows as &$row ) {
			$row['category_ids'] = json_decode( $row['category_ids'] ?? '[]', true );
			$row['tag_ids']      = json_decode( $row['tag_ids']      ?? '[]', true );
			$row['attributes']   = json_decode( $row['attributes']   ?? '{}', true );
		}
		return rest_ensure_response( $rows );
	}
}

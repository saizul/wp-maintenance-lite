<?php
/**
 * Result of the gate.
 *
 * @package Saizul\MaintenanceLite
 */

namespace Saizul\MaintenanceLite;

defined( 'ABSPATH' ) || exit;

/**
 * Whether to show the maintenance page, why, and with which status code.
 */
final class Decision {

	/**
	 * Show the maintenance page.
	 *
	 * @var bool
	 */
	public $block;

	/**
	 * Machine-readable reason, for example `disabled`, `role`, `ip`, `preview`, `path:login` or `blocked`.
	 *
	 * @var string
	 */
	public $reason;

	/**
	 * HTTP status code of the page (503 or 200).
	 *
	 * @var int
	 */
	public $status;

	/**
	 * Seconds for the Retry-After header, 0 for none.
	 *
	 * @var int
	 */
	public $retry_after;

	/**
	 * Constructor.
	 *
	 * @param bool   $block       Show the page.
	 * @param string $reason      Reason.
	 * @param int    $status      Status code.
	 * @param int    $retry_after Retry-After seconds.
	 */
	public function __construct( bool $block, string $reason, int $status = 200, int $retry_after = 0 ) {
		$this->block       = $block;
		$this->reason      = $reason;
		$this->status      = $status;
		$this->retry_after = $retry_after;
	}

	/**
	 * Let the request through.
	 *
	 * @param string $reason Reason.
	 */
	public static function allow( string $reason ): self {
		return new self( false, $reason );
	}
}

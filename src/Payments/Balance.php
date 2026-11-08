<?php

namespace ModwellLabs\OrderBalance\Payments;

defined( 'ABSPATH' ) || exit;

final class Balance {

	/** @var float */
	public $total;

	/** @var float */
	public $paid;

	/** @var float */
	public $requested;

	/** @var float */
	public $due;

	/** @var bool */
	public $tracked;

	public function __construct( float $total, float $paid, float $requested, bool $tracked ) {
		$decimals        = wc_get_price_decimals();
		$this->total     = round( $total, $decimals );
		$this->paid      = round( $paid, $decimals );
		$this->requested = round( $requested, $decimals );
		$this->due       = round( $total - $paid, $decimals );
		$this->tracked   = $tracked;
	}

	public function unrequested(): float {
		return $this->due > 0 ? max( 0.0, round( $this->due - $this->requested, wc_get_price_decimals() ) ) : $this->due;
	}

	public function is_settled(): bool {
		return abs( $this->due ) < $this->epsilon();
	}

	public function is_owed(): bool {
		return $this->due >= $this->epsilon();
	}

	public function is_overpaid(): bool {
		return $this->due <= -$this->epsilon();
	}

	public function within( float $threshold ): bool {
		return ! $this->is_settled() && $threshold > 0 && abs( $this->due ) <= $threshold + $this->epsilon() / 2;
	}

	private function epsilon(): float {
		return 1 / ( 10 ** wc_get_price_decimals() );
	}
}

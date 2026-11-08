<?php

namespace ModwellLabs\OrderBalance;

use ModwellLabs\OrderBalance\Payments\BalanceCalculator;
use ModwellLabs\OrderBalance\Payments\BalanceService;
use ModwellLabs\OrderBalance\Payments\PaymentActions;
use ModwellLabs\OrderBalance\Payments\PaymentRepository;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var array<string, object> */
	private $services = array();

	/** @var bool */
	private $booted = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$modules = apply_filters(
			'obal_modules',
			array(
				Settings::class,
				Payments\PaymentType::class,
				Payments\PaidTracker::class,
				Payments\LaterPayments::class,
				Payments\StatusSync::class,
				Payments\AutoAdjustment::class,
				Payments\GatewayLookup::class,
				Admin\AdminPage::class,
				Admin\SetupWizard::class,
				Admin\PaymentScreen::class,
				Admin\OrderMetabox::class,
				Admin\Ajax::class,
				Emails\Emails::class,
				Frontend\Account::class,
				Frontend\PayGuard::class,
			)
		);

		foreach ( array_unique( $modules ) as $class ) {
			$module = $this->get( $class );
			if ( $module instanceof Module ) {
				$module->register( $this );
			}
		}

		do_action( 'obal_loaded', $this );
	}

	/**
	 * @template T
	 * @param class-string<T> $class
	 * @return T
	 */
	public function get( string $class ) {
		if ( ! isset( $this->services[ $class ] ) ) {
			$this->services[ $class ] = new $class( $this );
		}
		return $this->services[ $class ];
	}

	public function settings(): Settings {
		return $this->get( Settings::class );
	}

	public function payments(): PaymentRepository {
		return $this->get( PaymentRepository::class );
	}

	public function balance(): BalanceCalculator {
		return $this->get( BalanceCalculator::class );
	}

	public function service(): BalanceService {
		return $this->get( BalanceService::class );
	}

	public function actions(): PaymentActions {
		return $this->get( PaymentActions::class );
	}
}

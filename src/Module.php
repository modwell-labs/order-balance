<?php

namespace ModwellLabs\OrderBalance;

defined( 'ABSPATH' ) || exit;

interface Module {

	public function register( Plugin $plugin ): void;
}

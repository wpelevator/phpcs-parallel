<?php
/**
 * Sample file using WordPress Coding Standards (WPCS).
 *
 * @package WPElevator\RunParallel\Example
 */

namespace WPElevator\RunParallel\Example;

/**
 * Sample class using WordPress Coding Standards (WPCS).
 */
class Package_WPCS {
	/**
	 * Setup the package.
	 *
	 * @param string $root_dir Path to the root directory of the package.
	 *
	 * @return void
	 */
	public function __construct(
		public readonly string $root_dir,
	) {
	}

	/**
	 * Get the label.
	 *
	 * @return string
	 */
	public function label(): string {
		return basename( $this->root_dir );
	}
}

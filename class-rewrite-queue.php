<?php

/**
 * Rewrite Rule Queue
 */

if ( ! class_exists( 'Rewrite_Queue' ) ) :

class Rewrite_Queue {

	private static $instance;

	protected $cached_rules = array();

	protected $queue = array();

	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Rewrite_Queue;
			self::$instance->setup();
		}
		return self::$instance;
	}

	public function setup() {
		$this->load_cached_queue();
		add_action( 'wp_loaded', array( $this, 'do_enqueue_rewrites' ), 10 );
		add_action( 'wp_loaded', array( $this, 'check_enqueued_rewrites' ), 15 );
	}

	/**
	 * Enqueue a set of rewrite rules.
	 *
	 * @param  string $name    A reference key for your rules. This must be
	 *                         unique across all enqueued rewrites. When rules
	 *                         are being generated, the action
	 *                         "build_rewrite_rules_{$name}"
	 * @param  mixed $version  A version identifier. This will most often be an
	 *                         integer, but it could also be a float (e.g. 1.2)
	 *                         or a string (e.g. 'rc3'). The version exists to
	 *                         tell WordPress that the rules under $name have
	 *                         changed.
	 * @param  string $after   The placement for your rewrite rules. This can be
	 *                         'top', 'bottom', or the $name of any other
	 *                         enqueued rewrites.
	 */
	public function enqueue( $name, $version = null, $after = 'bottom' ) {
		$this->queue[ $after ][ $name ] = $version;
	}

	/**
	 * Load the last generate queue from the options API. This is used to check
	 * for new changes.
	 */
	protected function load_cached_queue() {
		$this->cached_rules = get_option( 'rewrite_queue', array() );
	}

	/**
	 * Fire an action to enqueue rewrites. This offers a bit more granularity
	 * for rule order, since plugins can hook in to this action at any priority.
	 *
	 * If this were to ever become part or core, it could fire on its own (as
	 * opposed to firing via wp_loaded).
	 */
	public function do_enqueue_rewrites() {
		do_action( 'enqueue_rewrites' );
	}

	/**
	 * Check the enqueued rules for any changes. Eventually we would identify
	 * what has changed, and selectively regenerate the rules which changed.
	 * Changes that would be considered are:
	 *
	 * 1. Addition/removal of rules
	 * 2. Rule version numbers
	 * 3. Rule order
	 */
	public function check_enqueued_rewrites() {
		if ( $this->cached_rules !== $this->queue ) {
			// There has been a change! For now, we'll just rebuild all rules.
			$this->build_rules();
		}
	}

	/**
	 * This is a temporary method for the POC. In the final version, we'd want
	 * to only build the rules that changed, were added, or were removed.
	 */
	protected function build_rules() {
		update_option( 'rewrite_queue', $this->queue );

		$this->reorder_queue();

		foreach ( $this->queue as $name ) {
			do_action( 'build_rewrite_rules_' . $name );
			do_action( 'build_rewrite_rules_component', $name );
		}

		flush_rewrite_rules();
	}

	/**
	 * Put the queue in the order in which the rules should be built.
	 */
	protected function reorder_queue() {
		// Set aside top rules
		$ordered_rules = $this->dequeue_rules( 'top' );

		// Set aside bottom rules
		$bottom_rules = $this->dequeue_rules( 'bottom' );

		// Set aside the rest in the order they were received
		$placements = array_keys( $this->queue );
		foreach ( $placements as $placement ) {
			$ordered_rules = array_merge( $ordered_rules, $this->dequeue_rules( $placement ) );
		}

		// Add the bottom rules to the rest
		$ordered_rules = array_merge( $ordered_rules, $bottom_rules );

		// Ensure that all the rules are unique and add back to $this->queue.
		$this->queue = array_unique( $ordered_rules );
	}

	/**
	 * Recursively crawl a rule placement to put rules in the desired order.
	 *
	 * Given a placement, its rule queue is crawled. At each name, the main
	 * queue is checked to see if any rules should come after it, and those are
	 * then processed in the order in which they were added. Each of those rules
	 * is then checked for others which should come after them, and so on.
	 *
	 * @param  string $placement The name of the queue placement to process.
	 * @return array Ordered rules from the given queue placement.
	 */
	protected function dequeue_rules( $placement ) {
		$return = array();
		if ( ! empty( $this->queue[ $placement ] ) ) {
			$queue = $this->queue[ $placement ];
			unset( $this->queue[ $placement ] );

			foreach ( $queue as $name => $version ) {
				$return[] = $name;
				$return = array_merge( $return, $this->dequeue_rules( $name ) );
			}
		}

		return $return;
	}
}

function Rewrite_Queue() {
	return Rewrite_Queue::instance();
}
Rewrite_Queue();

endif;
<?php

/**
 * Rewrite Rule Queue
 */

if ( ! class_exists( 'Rewrite_Queue' ) ) :

class Rewrite_Queue {

	/**
	 * Hold the singleton instance.
	 *
	 * @access private
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * The option key in which to store the rewrite register.
	 *
	 * @access protected
	 *
	 * @var string
	 */
	protected $option = 'rewrite_register';

	/**
	 * The most recently generated register, as stored in the options.
	 *
	 * This is used to compare against the current register to see if anything
	 * has changed.
	 *
	 * @access protected
	 *
	 * @var array
	 */
	protected $previous_register = array();

	/**
	 * The current register.
	 *
	 * As rule sets are registered, they're stored in this register. This is then
	 * compared against the previous register, and becomes the foundation for
	 * the rule queue should the rules need to be rebuilt.
	 *
	 * @access protected
	 *
	 * @var array
	 */
	protected $register = array();

	/**
	 * The rewrite rule queue used when building rewrites.
	 *
	 * The rule queue is an ordered set of rule set names. When building
	 * rewrites, this queue is looped through and each name becomes part of a
	 * fired action.
	 *
	 * @access protected
	 *
	 * @var array
	 */
	protected $queue = array();

	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	/**
	 * Load the singleton instance.
	 *
	 * @return object
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Rewrite_Queue;
			self::$instance->setup();
		}
		return self::$instance;
	}

	/**
	 * Setup the singleton. This is essentially a constructor and is only ever
	 * run once.
	 */
	public function setup() {
		$this->load_cached_register();
		add_action( 'wp_loaded', array( $this, 'do_register_rewrites' ), 10 );
		add_action( 'wp_loaded', array( $this, 'check_registered_rewrites' ), 15 );
	}

	/**
	 * Register a set of rewrite rules.
	 *
	 * @param  string $name    A reference key for your rules. This must be
	 *                         unique across all registered rewrites. When rules
	 *                         are being generated, the action
	 *                         "build_rewrite_rules_{$name}"
	 * @param  mixed $version  A version identifier. This will most often be an
	 *                         integer, but it could also be a float (e.g. 1.2)
	 *                         or a string (e.g. 'rc3'). The version exists to
	 *                         tell WordPress that the rules under $name have
	 *                         changed.
	 * @param  string $after   The placement for your rewrite rules. This can be
	 *                         'top', 'bottom', or the $name of any other
	 *                         registered rewrites.
	 */
	public function register( $name, $version = null, $after = 'bottom' ) {
		$this->register[ $after ][ $name ] = $version;
	}

	/**
	 * Load the last generate queue from the options API. This is used to check
	 * for new changes.
	 */
	protected function load_cached_register() {
		$this->previous_register = get_option( $this->option, array() );
	}

	/**
	 * Fire an action to register rewrites. This offers a bit more granularity
	 * for rule order, since plugins can hook in to this action at any priority.
	 *
	 * If this were to ever become part or core, it could fire on its own (as
	 * opposed to firing via wp_loaded).
	 */
	public function do_register_rewrites() {
		do_action( 'register_rewrites' );
	}

	/**
	 * Check the registered rules for any changes. Eventually we would identify
	 * what has changed, and selectively regenerate the rules which changed.
	 * Changes that would be considered are:
	 *
	 * 1. Addition/removal of rules
	 * 2. Rule version numbers
	 * 3. Rule order
	 */
	public function check_registered_rewrites() {
		if ( $this->previous_register !== $this->register ) {
			// There has been a change! For now, we'll just rebuild all rules.
			$this->build_rules();
		}
	}

	/**
	 * This is a temporary method for the POC. In the final version, we'd want
	 * to only build the rules that changed, were added, or were removed.
	 */
	protected function build_rules() {
		update_option( $this->option, $this->register );

		$this->order_queue();

		foreach ( $this->queue as $name ) {
			do_action( 'build_rewrite_rules_' . $name );
			do_action( 'build_rewrite_rules_component', $name );
		}

		flush_rewrite_rules();
	}

	/**
	 * Put the queue in the order in which the rules should be built.
	 */
	protected function order_queue() {
		// Set aside top rules
		$ordered_rules = $this->dequeue_rules( 'top' );

		// Set aside bottom rules
		$bottom_rules = $this->dequeue_rules( 'bottom' );

		// Set aside the rest in the order they were received
		$placements = array_keys( $this->register );
		foreach ( $placements as $placement ) {
			$ordered_rules = array_merge( $ordered_rules, $this->dequeue_rules( $placement ) );
		}

		// Add the bottom rules to the rest
		$ordered_rules = array_merge( $ordered_rules, $bottom_rules );

		// Ensure that all the rules are unique and store in the queue.
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
		if ( ! empty( $this->register[ $placement ] ) ) {
			$queue = $this->register[ $placement ];
			unset( $this->register[ $placement ] );

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
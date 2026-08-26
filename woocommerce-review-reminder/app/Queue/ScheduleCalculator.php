<?php
/**
 * Computes when a review request should be sent.
 *
 * @package WooCommerceReviewReminder\Queue
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Queue;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Class ScheduleCalculator
 */
final class ScheduleCalculator {

	/**
	 * Seconds per supported delay unit.
	 *
	 * @var array<string, int>
	 */
	private const UNIT_SECONDS = array(
		'minutes' => 60,
		'hours'   => 3600,
		'days'    => 86400,
		'weeks'   => 604800,
	);

	/**
	 * Compute the schedule timestamp.
	 *
	 * @param int    $delay   Delay amount.
	 * @param string $unit    minutes|hours|days|weeks.
	 * @param string $send_time Preferred daily send time "H:i" or empty.
	 * @param string $from    Reference datetime (site timezone), default now.
	 * @return string MySQL datetime in the site timezone.
	 */
	public function calculate( int $delay, string $unit, string $send_time = '', string $from = '' ): string {
		$timezone = $this->site_timezone();
		$from     = $from ? $from : current_time( 'mysql' );

		try {
			$base = new DateTimeImmutable( $from, $timezone );
		} catch ( \Exception $e ) {
			$base = new DateTimeImmutable( 'now', $timezone );
		}

		$seconds = self::UNIT_SECONDS[ $unit ] ?? self::UNIT_SECONDS['days'];
		$target  = $base->modify( '+' . ( $delay * $seconds ) . ' seconds' );

		// Apply preferred send time only for day/week granularity.
		if ( '' !== $send_time && in_array( $unit, array( 'days', 'weeks' ), true ) ) {
			$target = $this->apply_send_time( $target, $send_time, $base, $timezone );
		}

		return $target->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Set the target datetime to the preferred send time, rolling to the next
	 * day when that time has already passed on the target day.
	 *
	 * @param DateTimeImmutable $target Target datetime.
	 * @param string            $send_time "H:i".
	 * @param DateTimeImmutable $base    Reference datetime.
	 * @param DateTimeZone      $timezone Site timezone.
	 * @return DateTimeImmutable
	 */
	private function apply_send_time( DateTimeImmutable $target, string $send_time, DateTimeImmutable $base, DateTimeZone $timezone ): DateTimeImmutable {
		$parts = explode( ':', $send_time );
		$hour  = isset( $parts[0] ) ? max( 0, min( 23, (int) $parts[0] ) ) : 0;
		$min   = isset( $parts[1] ) ? max( 0, min( 59, (int) $parts[1] ) ) : 0;

		$adjusted = $target->setTime( $hour, $min, 0 );

		if ( $adjusted <= $base ) {
			$adjusted = $adjusted->modify( '+1 day' );
		}

		return $adjusted;
	}

	/**
	 * The WordPress configured timezone as a DateTimeZone.
	 *
	 * @return DateTimeZone
	 */
	private function site_timezone(): DateTimeZone {
		$tz = wp_timezone_string();
		try {
			return new DateTimeZone( $tz );
		} catch ( \Exception $e ) {
			return new DateTimeZone( 'UTC' );
		}
	}
}

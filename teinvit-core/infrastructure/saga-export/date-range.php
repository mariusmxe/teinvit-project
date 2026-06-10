<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TeInvit_Saga_Export_Date_Range {
    public static function options() {
        return [
            'current_month' => 'Luna curenta',
            'current_year' => 'Anul curent',
            'last_7_days' => 'Ultimele 7 zile',
            'last_30_days' => 'Ultimele 30 de zile',
            'custom' => 'Interval',
        ];
    }

    public static function from_request( $source ) {
        $source = is_array( $source ) ? $source : [];
        $period = sanitize_key( wp_unslash( $source['period'] ?? 'current_month' ) );
        if ( ! array_key_exists( $period, self::options() ) ) {
            $period = 'current_month';
        }

        $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
        $now = new DateTimeImmutable( 'now', $timezone );

        if ( $period === 'current_year' ) {
            $start = $now->setDate( (int) $now->format( 'Y' ), 1, 1 )->setTime( 0, 0, 0 );
            $end = $now->setTime( 23, 59, 59 );
        } elseif ( $period === 'last_7_days' ) {
            $start = $now->modify( '-6 days' )->setTime( 0, 0, 0 );
            $end = $now->setTime( 23, 59, 59 );
        } elseif ( $period === 'last_30_days' ) {
            $start = $now->modify( '-29 days' )->setTime( 0, 0, 0 );
            $end = $now->setTime( 23, 59, 59 );
        } elseif ( $period === 'custom' ) {
            $start_raw = sanitize_text_field( wp_unslash( $source['date_start'] ?? '' ) );
            $end_raw = sanitize_text_field( wp_unslash( $source['date_end'] ?? '' ) );
            $start = self::parse_date( $start_raw, $timezone, true );
            $end = self::parse_date( $end_raw, $timezone, false );
            if ( ! $start ) {
                $start = $now->setTime( 0, 0, 0 );
            }
            if ( ! $end ) {
                $end = $now->setTime( 23, 59, 59 );
            }
        } else {
            $start = $now->setDate( (int) $now->format( 'Y' ), (int) $now->format( 'm' ), 1 )->setTime( 0, 0, 0 );
            $end = $now->setTime( 23, 59, 59 );
        }

        if ( $start->getTimestamp() > $end->getTimestamp() ) {
            $tmp = $start;
            $start = $end->setTime( 0, 0, 0 );
            $end = $tmp->setTime( 23, 59, 59 );
        }

        return [
            'period' => $period,
            'start' => $start,
            'end' => $end,
            'start_ts' => $start->getTimestamp(),
            'end_ts' => $end->getTimestamp(),
            'start_date' => $start->format( 'Y-m-d' ),
            'end_date' => $end->format( 'Y-m-d' ),
        ];
    }

    private static function parse_date( $value, DateTimeZone $timezone, $start_of_day ) {
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value ) ) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value . ( $start_of_day ? ' 00:00:00' : ' 23:59:59' ), $timezone );
        return $date instanceof DateTimeImmutable ? $date : null;
    }
}

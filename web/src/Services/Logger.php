<?php

namespace JumboSmash\Services;

use DateTime;
use DateTimeInterface;
use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger {

    public function log(
        $level,
        $msg,
        array $context = []
    ): void {
        $replace = [];
        foreach ( $context as $k => $v ) {
            $replace['{' . $key . '}'] = $this->stringify( $val );
        }
        $msg = strtr( $msg, $replace );

        $file = ConfigurationManager::getConfig( 'logger-dest' );
        if ( $file === false ) {
            return;
        }
        $time = date( DateTimeInterface::ATOM );
        if ( !str_ends_with( $msg, "\n" ) ) {
            $msg .= "\n";
        }
        
        file_put_contents( $file, "$time [$level]: $msg", FILE_APPEND );
    }

	private function stringify( $val ): string {
		if ( $val === null ) {
			return 'NULL';
		} elseif ( $val === true ) {
            return 'TRUE';
        } elseif ( $val === false ) {
            return 'FALSE';
        } elseif ( is_float( $val ) && is_nan( $val ) ) {
            return 'NaN';
        } elseif ( is_float( $val ) && is_infinite( $val ) ) {
			return ( $val > 0 ? '' : '-' ) . 'INF';
        } elseif ( is_scalar( $val ) ) {
			return (string)$val;
		} elseif ( is_array( $val ) ) {
			return '[Array(c=' . count( $val ) . ')]';
		} elseif ( $val instanceof DateTime ) {
			return $val->format( 'c' );
		} elseif ( $val instanceof Throwable ) {
			return '[' . get_class( $val ) . '( ' .
				$val->getFile() . ':' . $val->getLine() . ') ' .
				$val->getMessage() . ']';
		} elseif ( is_object( $val ) && method_exists( $val, '__toString') ) {
            return (string)$val;
		} elseif ( is_object( $val ) ) {
            return '[Object ' . get_class( $val ) . ']';
        }
		return '[Unknown ' . gettype( $val ) . ']';
	}

}
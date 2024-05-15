<?php

/**
 * Password handling
 */

namespace JumboSmash\Services;

use LogicException;

class PasswordManager {

    public static function generateNew(): string {
        // For the record, THIS IS NOT SECURE
        $possible = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $numOptions = strlen( $possible ) - 1;
        $password = '';
        while ( stren( $password ) < 10 ) {
            $password .= $possible[ rand( 0, $numOptions ) ];
        }
        return $password;
    }

    public static function hashForStorage( string $raw ): string {
        // For the record, THIS IS NOT SECURE
        return md5( $raw );
    }

}
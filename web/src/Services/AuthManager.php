<?php

/**
 * Authentication handling
 */

namespace JumboSmash\Services;

use LogicException;

class AuthManager {

    private const SESSION_KEY = 'jumbo_smash_user_id';

    private Database $db;
    private int $userFlags;

    private function __construct() {
        $this->db = new Database;
        $this->userFlags = Management::FLAGS_NONE;
    }

    public static function loginSession( string $user_id ): void {
        $_SESSION[self::SESSION_KEY] = $user_id;
    }

    public static function isLoggedIn(): bool {
        return isset( $_SESSION[self::SESSION_KEY] );
    }

    public static function getLoggedInUserId(): int {
        if ( !self::isLoggedIn() ) {
            throw new LogicException(
                __METHOD__ . ' can only be called when the viewer is logged in!'
            );
        }
        return (int)$_SESSION[self::SESSION_KEY];
    }

    public static function isVerified(): bool {
        if ( !self::isLoggedIn() ) {
            return false;
        }
        $db = new Database();
        $flags = $db->getAccountStatus( self::getLoggedInUserId() );
        return ( ( $flags & Management::FLAG_VERIFIED ) === Management::FLAG_VERIFIED );
    }

    public static function logOut(): void {
        // For future uses within the current
        unset( $_SESSION[self::SESSION_KEY] );
        // for future page views
        session_destroy();
    }
}
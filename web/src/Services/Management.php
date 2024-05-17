<?php

/**
 * User *management* from the front end
 */

namespace JumboSmash\Services;

class Management {

    public const FLAG_NONE = 0;
    public const FLAG_VERIFIED = 1;
    public const FLAG_DISABLED = 2;

    // Shouldn't ever get that high
    public const FLAG_MANAGER = 512;

    // Value for manager = manager + verified
    private const MANAGER_STATUS_VALUE = self::FLAG_VERIFIED | self::FLAG_MANAGER;

    public const MANAGER_TUFTS_NAME = '~jumbo~smash~management~';
    public const MANAGER_EMAIL = 'jumbosmash2024@gmail.com';

    private Database $db;
    private Logger $logger;

    public function __construct() {
        $this->db = new Database();
        $this->logger = new Logger();
    }

    public static function doSetup() {
        $manager = new Management();
        $superId = $manager->ensureAccountExists();
        $manager->ensureAccountPassword( $superId );
        $manager->ensurePermissions( $superId );
    }

    private function ensureAccountExists(): int {
        $res = $this->db->findAccountByPersonal( self::MANAGER_EMAIL );
        if ( $res ) {
            return (int)( $res->user_id );
        }
        $created = $this->db->createAccount(
            self::MANAGER_TUFTS_NAME,
            self::MANAGER_EMAIL,
            PasswordManager::hashForStorage(
                ConfigurationManager::getSecret( 'manager-pass' )
            )
        );
        $this->logger->notice(
            'Management account created, has ID #{id}',
            [ 'id' => $created ]
        );
        return (int)$created;
    }

    private function ensureAccountPassword( int $superId ): void {
        // In case the password ever leaks, we always use the latest manager-pass
        $correctHash = PasswordManager::hashForStorage(
            ConfigurationManager::getSecret( 'manager-pass' )
        );
        if ( $this->db->ensurePassword( $superId, $correctHash ) ) {
            $this->logger->notice( 'Management account password reset' );
        }
    }

    private function ensurePermissions( int $superId ): void {
        $currStatus = $this->db->getAccountStatus( $superId );
        if ( $currStatus === self::MANAGER_STATUS_VALUE ) {
            // already set up
            return;
        }
        $this->db->setAccountStatus( $superId, self::MANAGER_STATUS_VALUE );
        $this->logger->notice(
            'Management account flags changed, from {old} to {new}',
            [ 'old' => $currStatus, 'new' => self::MANAGER_STATUS_VALUE ]
        );
    }

    public static function hasFlag( int $value, int $flag ): bool {
        return ( ( $value & $flag ) === $flag );
    }

}
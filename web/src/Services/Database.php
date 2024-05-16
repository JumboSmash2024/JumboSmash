<?php

/**
 * Database handling
 */

namespace JumboSmash\Services;

use JumboSmash\MissingUserException;
use stdClass;
use mysqli;

class Database {

    public const VALUE_RESPONSE_PASS = 0;
    public const VALUE_RESPONSE_SMASH = 1;

    private mysqli $db;
    private string $sqlDir;

    public function __construct() {
        $this->db = new mysqli(
            JUMBO_SMASH_DB_HOST,
            JUMBO_SMASH_DB_USER,
            JUMBO_SMASH_DB_PASS,
            JUMBO_SMASH_DB_NAME
        );
        $this->sqlDir = dirname( __DIR__, 2 ) . '/sql/';
    }

    public function __destruct() {
        // Close the connection
        $this->db->close();
    }

    private function ensureTable( string $tableName, string $patchFile ) {
        $result = $this->db->query( "SHOW TABLES LIKE '$tableName';" );
        if ( $result->num_rows !== 0 ) {
            // Already created
            return;
        }
        $patchContents = file_get_contents( $this->sqlDir . $patchFile );
        $result = $this->db->query( $patchContents );
    }
    public function ensureDatabase() {
        $this->ensureTable( 'users', 'users-table.sql' );
        $this->ensureTable( 'responses', 'responses-table.sql' );
        $this->ensureTable( 'profiles', 'profiles-table.sql' );
    }

    public function clearTables() {
        $this->db->query( 'DROP TABLE users' );
        $this->db->query( 'DROP TABLE responses' );
        $this->db->query( 'DROP TABLE profiles' );
        // On the next page view ensureDatabase() will recreate the tables
    }

    public static function doSetup() {
        // So that the constructor can select the database without errors when
        // it doesn't exist (on docker)
        $mysqli = new mysqli(
            JUMBO_SMASH_DB_HOST,
            JUMBO_SMASH_DB_USER,
            JUMBO_SMASH_DB_PASS
        );
        $mysqli->query(
            "CREATE DATABASE IF NOT EXISTS " . JUMBO_SMASH_DB_NAME
        );
        // close the connection
        $mysqli->close();
        $db = new Database;
        // TODO remove this...
        // $db->clearTables();
        $db->ensureDatabase();
    }

    public function findAccountByTufts( string $tuftsName ): ?stdClass {
        $query = $this->db->prepare(
            'SELECT user_id, user_pass_hash, user_tufts_name, ' .
            'user_personal_email, user_status FROM users WHERE user_tufts_name = ?'
        );
        $query->bind_param(
            's',
            ...[ strtolower( $tuftsName ) ]
        );
        $query->execute();
        $result = $query->get_result();
        $rows = $result->fetch_all( MYSQLI_ASSOC );
        if ( count( $rows ) === 0 ) {
            return null;
        }
        return (object)($rows[0]);
    }
    public function findAccountByPersonal( string $personalEmail ): ?stdClass {
        $query = $this->db->prepare(
            'SELECT user_id, user_pass_hash, user_tufts_name, user_status ' .
            'user_personal_email FROM users WHERE user_personal_email = ?'
        );
        $query->bind_param(
            's',
            ...[ strtolower( $personalEmail ) ]
        );
        $query->execute();
        $result = $query->get_result();
        $rows = $result->fetch_all( MYSQLI_ASSOC );
        if ( count( $rows ) === 0 ) {
            return null;
        }
        return (object)($rows[0]);
    }
    public function getAccountById( int $userId ): stdClass {
        $query = $this->db->prepare(
            'SELECT user_id, user_tufts_name, user_personal_email, user_status ' .
            ' FROM users WHERE user_id = ?'
        );
        $query->bind_param( 'd', ...[ $userId ] );
        $query->execute();
        $result = $query->get_result();
        $rows = $result->fetch_all( MYSQLI_ASSOC );
        if ( count( $rows ) === 0 ) {
            // We should only fetch by ID for valid users
            throw new MissingUserException( "Missing user with ID: $userId" );
        }
        return (object)($rows[0]);
    }

    public function createAccount(
        string $tuftsName,
        string $personalEmail,
        string $passHash
    ): string {
        $query = $this->db->prepare(
            'INSERT INTO users (user_tufts_name, user_personal_email, user_pass_hash, user_status) ' .
            'VALUES (?, ?, ?, ?)'
        );
        $query->bind_param(
            'sssd',
            ...[
                strtolower( $tuftsName ),
                strtolower( $personalEmail ),
                $passHash,
                Management::FLAG_NONE,
            ]
        );
        $query->execute();
        return (string)( $this->db->insert_id );
    }

    public function getUnresponded( int $userId ): array {
        $query = $this->db->prepare(
            'SELECT user_id, user_tufts_name, user_status FROM users WHERE NOT EXISTS ' .
            '(SELECT resp_value FROM responses WHERE resp_target = user_id AND ' .
                'resp_user = ?) ' .
            'AND user_id != ? ' .
            // Not disabled
            'AND (user_status & 2 = 0) ' .
            // Verified
            'AND (user_status & 1 = 1)'
        );
        $query->bind_param( 'dd', ...[ $userId, $userId ] );
        $query->execute();
        $result = $query->get_result();
        $rows = $result->fetch_all( MYSQLI_ASSOC );
        return array_map(
            static fn ( $arr ) => (object)( $arr ),
            $rows
        );
    }

    public function recordResponse(
        int $userId,
        int $jumboId,
        int $responseValue
    ): int {
        $query = $this->db->prepare(
            'INSERT INTO responses (resp_user, resp_target, resp_value) ' .
            'VALUES (?, ?, ?)'
        );
        $query->bind_param(
            'sss',
            ...[ $userId, $jumboId, $responseValue ]
        );
        $query->execute();
        return (int)(string)( $this->db->insert_id );
    }

    public function getResponses( int $userId ): array {
        $query = $this->db->prepare(
            'SELECT resp_value, user_tufts_name FROM responses ' .
            'JOIN users ON resp_target = user_id WHERE resp_user = ?'
        );
        $query->bind_param( 'd', ...[ $userId ] );
        $query->execute();
        $result = $query->get_result();
        $rows = $result->fetch_all( MYSQLI_ASSOC );
        return array_map(
            static fn ( $arr ) => (object)( $arr ),
            $rows
        );
    }

    public function getMatchInfo( int $userId ): array {
        $query = $this->db->prepare(
            'SELECT my_response.resp_target, their_response.resp_value, ' .
            'user_tufts_name, user_personal_email ' .
            'FROM responses my_response LEFT JOIN responses their_response ON ' .
            'my_response.resp_target = their_response.resp_user AND ' .
            'my_response.resp_user = their_response.resp_target ' .
            'LEFT JOIN users ON my_response.resp_target = user_id ' .
            'WHERE my_response.resp_user = ?'
        );
        $query->bind_param( 'd', ...[ $userId ] );
        $query->execute();
        $result = $query->get_result();
        $rows = $result->fetch_all( MYSQLI_ASSOC );
        return array_map(
            static function ( $arr ) {
                $row = (object)( $arr );
                if ( $row->resp_value === NULL ) {
                    // redact if no match
                    $row->user_personal_email = '';
                }
                return $row;
            },
            $rows
        );
    }

    public function getAccountStatus( int $userId ): int {
        $account = $this->getAccountById( $userId );
        return (int)( $account->user_status );
    }

    public function setAccountStatus( int $userId, int $status ): void {
        $query = $this->db->prepare(
            'UPDATE users SET user_status = ? WHERE user_id = ?'
        );
        $query->bind_param( 'dd', ...[ $status, $userId ] );
        $query->execute();
    }

    public function getUsersForManagement(): array {
        $query = $this->db->prepare(
            'SELECT user_id, user_tufts_name, user_personal_email, user_status FROM users'
        );
        $query->execute();
        $result = $query->get_result();
        $rows = $result->fetch_all( MYSQLI_ASSOC );
        return array_map(
            static fn ( $arr ) => (object)( $arr ),
            $rows
        );
    }

    // Result true if we needed to change the password
    public function ensurePassword( int $userId, string $correctHash ): bool {
        $query = $this->db->prepare(
            'SELECT user_pass_hash FROM users WHERE user_id = ?'
        );
        $query->bind_param( 'd', ...[ $userId ] );
        $query->execute();
        $result = $query->get_result();
        $rows = $result->fetch_all( MYSQLI_ASSOC );
        $currHash = $rows[0]['user_pass_hash'];

        if ( $currHash === $correctHash ) {
            return false;
        }

        $setter = $this->db->prepare(
            'UPDATE users SET user_pass_hash = ? WHERE user_id = ?'
        );
        $setter->bind_param( 'sd', ...[ $correctHash, $userId ] );
        $setter->execute();
        return true;
    }

    private function getProfileTextRaw( int $userId ): ?string {
        // Doesn't care about unverified/disabled accounts
        $query = $this->db->prepare(
            'SELECT profile_text FROM profiles WHERE profile_user = ?'
        );
        $query->bind_param( 'd', ...[ $userId ] );
        $query->execute();
        $result = $query->get_result();
        $rows = $result->fetch_all( MYSQLI_ASSOC );
        if ( count( $rows ) === 0 ) {
            return null;
        }
        return $rows[0]['profile_text'];   
    }

    public function getProfileText( int $userId ): ?string {
        $userStatus = $this->getAccountStatus( $userId );
        // Text is ignored for disabled/unverified accounts
        if ( Management::hasFlag( $userStatus, Management::FLAG_DISABLED ) ) {
            return null;
        }
        if ( !Management::hasFlag( $userStatus, Management::FLAG_VERIFIED ) ) {
            return null;
        }
        return $this->getProfileTextRaw( $userId );
    }

    public function setProfileText( int $userId, string $profile ): void {
        if ( $this->getProfileTextRaw( $userId ) === null ) {
            // No current profile
            $query = $this->db->prepare(
                'INSERT INTO profiles (profile_user, profile_text) VALUES (?, ?)'
            );
            $query->bind_param(
                'ds',
                ...[ $userId, $profile ]
            );
        } else {
            // Have current text
            $query = $this->db->prepare(
                'UPDATE profiles SET profile_text = ? WHERE profile_user = ?'
            );
            $query->bind_param(
                'sd',
                ...[ $profile, $userId ]
            );
        }
        $query->execute();  
    }

}
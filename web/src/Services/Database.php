<?php

/**
 * Database handling
 */

namespace JumboSmash\Services;

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
    }

    public function clearTables() {
        $this->db->query( 'DROP TABLE users' );
        $this->db->query( 'DROP TABLE responses' );
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
            'user_personal_email FROM users WHERE user_tufts_name = ?'
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
            'SELECT user_id, user_pass_hash, user_tufts_name, ' .
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
    public function getAccountById( int $userId ): array {
        $query = $this->db->prepare(
            'SELECT user_id, user_tufts_name, user_personal_email ' .
            ' FROM users WHERE user_id = ?'
        );
        $query->bind_param( 'd', ...[ $userId ] );
        $query->execute();
        $result = $query->get_result();
        $rows = $result->fetch_all( MYSQLI_ASSOC );
        return $rows[0];
    }

    public function createAccount(
        string $tuftsName,
        string $personalEmail,
        string $passHash
    ): string {
        $query = $this->db->prepare(
            'INSERT INTO users (user_tufts_name, user_personal_email, user_pass_hash) ' .
            'VALUES (?, ?, ?)'
        );
        $query->bind_param(
            'sss',
            ...[ strtolower( $tuftsName ), strtolower( $personalEmail ), $passHash ]
        );
        $query->execute();
        return (string)( $this->db->insert_id );
    }

    public function getUnresponded( int $userId ): array {
        $query = $this->db->prepare(
            'SELECT user_id, user_tufts_name FROM users WHERE NOT EXISTS ' .
            '(SELECT resp_value FROM responses WHERE resp_target = user_id AND ' .
                'resp_user = ?) ' .
            'AND user_id != ?'
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

}
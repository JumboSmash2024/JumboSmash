<?php

namespace JumboSmash\Services;

use InvalidArgumentException;
use RuntimeException;

class ConfigurationManager {

	private static ?array $secrets = null;

	/**
	 * Config values (not secret); key is the setting name, value is what to you
	 */
	private const KNOWN_SETTINGS = [
		'logger-dest' => '/var/www/html/log.txt',
	];

	public static function getConfig( string $settingName ) {
		if ( !array_key_exists( $settingName, self::KNOWN_SETTINGS ) ) {
			throw new InvalidArgumentException( "Unknown setting '$settingName'" );
		}
		return self::KNOWN_SETTINGS[ $settingName ];
	}

    public static function getSecret( string $secretName ) {
        self::ensureSecretsLoaded();

        if ( !array_key_exists( $secretName, self::$secrets ) ) {
			throw new InvalidArgumentException( "Unknown secret '$secretName'" );
		}
		return self::$secrets[ $secretName ];
	}

	private static function ensureSecretsLoaded(): void {
        if ( self::$secrets !== null ) {
            return;
        }
		// from web/src/Services/ConfigurationManager.php, want web/secrets.php
		$fileName = dirname( __DIR__, 2 ) . '/secrets.php';
		if ( !file_exists( $fileName ) ) {
			throw new RuntimeException( "Missing secrets file '$fileName'" );
		}
		$secrets = require $fileName;
		if ( !is_array( $secrets ) ) {
			throw new RuntimeException(
				"Secrets file '$fileName' did not return an array"
			);
		}
		self::$secrets = $secrets;
	}
}

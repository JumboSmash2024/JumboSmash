<?php

/**
 * Mail handling
 */

namespace JumboSmash\Services;

use LogicException;

class Mailer {

    public static function sendPasswordParts(
        string $email1,
        string $pass1,
        string $email2,
        string $pass2
    ) {
        Logger::log(
            "Password sending: `$pass1` to `$email1`, `$pass2` to `$email2"
        );
    }

}
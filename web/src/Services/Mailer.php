<?php

/**
 * Mail handling
 */

namespace JumboSmash\Services;

use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class Mailer {

    public const SENDER_EMAIL = 'jumbosmash2024@gmail.com';

    public static function sendMail( string $to, string $subj, string $msg ) {
        $log = new Logger();
        $log->debug(
            'Sending message to {to} with subject {subj}: {msg}',
            [ 'to' => $to, 'subj' => $subj, 'msg' => $msg ]
        );

        $dsn = 'ses+smtp://';
        $dsn .= urlencode( ConfigurationManager::getSecret( 'mail-dsn-user' ) );
        $dsn .= ':';
        $dsn .= urlencode( ConfigurationManager::getSecret( 'mail-dsn-pass' ) );
        $dsn .= '@default?region=';
        $dsn .= ConfigurationManager::getSecret( 'mail-dsn-region' );

        $transport = Transport::fromDsn( $dsn );
        $mailer = new SymfonyMailer( $transport );
        
        $email = (new Email())
            ->from( self::SENDER_EMAIL )
            ->to( $to )
            ->bcc( self::SENDER_EMAIL )
            ->subject( $subj )
            ->text( $msg );
        $mailer->send( $email );
    }

}
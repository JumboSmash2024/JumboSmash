<?php

require_once __DIR__ . '/src/setup.php';

\JumboSmash\Services\Mailer::sendMail(
    \JumboSmash\Services\Mailer::SENDER_EMAIL,
    'Test with Mailer service (subject)',
    'Test with Mailer service (message)'
);
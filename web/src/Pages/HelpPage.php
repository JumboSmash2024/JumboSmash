<?php

namespace JumboSmash\Pages;

use JumboSmash\HTML\HTMLBuilder;

class HelpPage extends BasePage {

    public function __construct() {
        parent::__construct( 'Help' );
        $this->addStyleSheet( 'help-styles.css' );
    }

    protected function getBodyElements(): array {
        $elems = [
            HTMLBuilder::element( 'h1', 'Help' ),
            HTMLBuilder::element(
                'p',
                'Its JumboSmash! See the links above for navigation.'
            ),
            HTMLBuilder::element(
                'p',
                <<<END
When you sign up, you provide a Tufts email and a personal email. The name from
the Tufts email is visible to other people and is what they see when choosing if
they want to connect - this is used to allow people to make sure they are connecting
with the person that they mean. If you both decide to connect, then in the matches page
you will see each others' personal emails to actually connect with each other.
END
            ),
            HTMLBuilder::element(
                'p',
                <<<END
If you both decide to connect, anything you do after that is up to you - we are
all adults here. A few reminders though:
END
            ),
        ];
        $reminders = [
            'You can only decide to connect ("smash") with people who have registered accounts.',
            'As a result, you should keep checking back for new accounts that you want to connect with.',
            'Matching just means you want to connect, it does not imply consent to anything! NO MEANS NO.',
            'You will not receive a notification when you have a new match.',
            'If you do end up doing anything (I leave what that might mean to your imagination, 😏), use protection.',
            [
                'If you have any problems with the interface or account, please contact ',
                HTMLBuilder::element( 'code', 'jumbosmash2024@gmail.com' ),
            ],
            'Do not reuse passwords - I wrote this in under 24 hours and it should not be treated as secure!!!',
            'Account registration emails may take a while to arrive, and will probably be in your spam folder.',
        ];
        $reminderList = HTMLBuilder::element(
            'ul',
            array_map(
                static fn ( $rem ) => HTMLBuilder::element( 'li', $rem ),
                $reminders
            )
        );
        $elems[] = $reminderList;

        $email = HTMLBuilder::element( 'code', 'jumbosmash2024@gmail.com' );
        $regSteps = [
            [ 'Send an email to ', $email, ' from your TUFTS email and CC the personal email' ],
            [ 'REPLY ALL from your personal email to confirm' ],
            [ 'Wait for management to verify your account' ],
        ];
        $registration = HTMLBuilder::element(
            'ol',
            array_map(
                static fn ( $step ) => HTMLBuilder::element( 'li', $step ),
                $regSteps
            )
        );
        $elems[] = HTMLBuilder::element( 'hr' );
        $elems[] = HTMLBuilder::element(
            'p',
            'To register your account, do the following:'
        );
        $elems[] = $registration;
        return $elems;
    }
}
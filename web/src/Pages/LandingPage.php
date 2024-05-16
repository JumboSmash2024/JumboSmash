<?php

namespace JumboSmash\Pages;

use JumboSmash\HTML\HTMLBuilder;
use JumboSmash\Services\AuthManager;

class LandingPage extends BasePage {

    public function __construct() {
        parent::__construct( 'Landing' );
    }

    protected function getBodyElements(): array {
        if ( !AuthManager::isLoggedIn() ) {
            return [
                HTMLBuilder::element( 'h1', 'About' ),
                HTMLBuilder::element(
                    'p',
                    'Its JumboSmash! Log in to participate.'
                )
            ];
        }
        return [
            HTMLBuilder::element( 'h1', 'Welcome' ),
            HTMLBuilder::element(
                'p',
                'Its JumboSmash! See the links above for navigation.'
            ),
        ];
    }
}
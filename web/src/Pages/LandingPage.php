<?php

/**
 * Used to create the output page
 */

namespace JumboSmash\Pages;

use JumboSmash\HTML\HTMLBuilder;
use JumboSmash\HTML\HTMLElement;

class LandingPage extends BasePage {
    public function __construct() {
        parent::__construct( 'Landing' );
    }

    protected function getBodyElements(): array {
        return [
            HTMLBuilder::element( 'h1', 'About' ),
        ];
    }
}
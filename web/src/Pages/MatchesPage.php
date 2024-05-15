<?php

namespace JumboSmash\Pages;

use JumboSmash\HTML\{HTMLBuilder, HTMLElement, HTMLPage};
use JumboSmash\Services\AuthManager;
use JumboSmash\Services\Database;

class MatchesPage extends BasePage {

    private const RESPONSE_SKIP = 'Skip';
    private const RESPONSE_SMASH = 'Smash';
    private const RESPONSE_PASS = 'Pass';

    public function __construct() {
        parent::__construct( 'Matches' );
        $this->addStyleSheet( 'matches-styles.css' );
    }

    protected function getBodyElements(): array {
        return [
            HTMLBuilder::element(
                'div',
                [
                    HTMLBuilder::element( 'h1', 'Your matches' ),
                    ...$this->getMainDisplay(),
                ]
            ),
        ];
    }

    private function getMainDisplay(): array {
        if ( !AuthManager::isLoggedIn() ) {
            return [ $this->mustLogInError() ];
        }
        return $this->getMatches();
    }

    private function getMatches(): array {
        $db = new Database;
        $matches = $db->getMatchInfo( AuthManager::getLoggedInUserId() );

        if ( !$matches ) {
            return [
                HTMLBuilder::element(
                    'p',
                    'No responses yet'
                ),
            ];
        }

        // Split up into two groups, those with a match and those unknown/pass
        $yesMatch = [];
        $noMatch = [];

        foreach ( $matches as $row ) {
            if ( $row->user_personal_email ) {
                $yesMatch[] = HTMLBuilder::element(
                    'li',
                    $row->user_tufts_name . ' (' . $row->user_personal_email . ')'
                );
            } else {
                $noMatch[] = HTMLBuilder::element(
                    'li',
                    $row->user_tufts_name
                );
            }
        }
        $output = [];
        if ( $yesMatch ) {
            $output[] = HTMLBuilder::element( 'p', 'Your matches:' );
            $output[] = HTMLBuilder::element( 'ul', $yesMatch );
        } else {
            $output[] = HTMLBuilder::element(
                'p',
                'No matches yet (probably because people have not answered...)'
            );
        }

        if ( $noMatch ) {
            $output[] = HTMLBuilder::element( 'p', 'No match (yet) with:' );
            $output[] = HTMLBuilder::element( 'ul', $noMatch );
        } else {
            $output[] = HTMLBuilder::element( 'p', 'Wow, 100% match rate!' );
        }

        return $output;
    }

    private function mustLogInError(): HTMLElement {
        return HTMLBuilder::element(
            'div',
            'ERROR: Must be logged in!',
            [ 'class' => 'js-error' ]
        );
    }

}
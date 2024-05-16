<?php

namespace JumboSmash\Pages;

use JumboSmash\HTML\{HTMLBuilder, HTMLElement};
use JumboSmash\Services\AuthManager;
use JumboSmash\Services\Database;

class HistoryPage extends BasePage {

    private const RESPONSE_SKIP = 'Skip';
    private const RESPONSE_SMASH = 'Smash';
    private const RESPONSE_PASS = 'Pass';

    public function __construct() {
        parent::__construct( 'History' );
        $this->addStyleSheet( 'history-styles.css' );
    }

    protected function getBodyElements(): array {
        return [
            HTMLBuilder::element(
                'div',
                [
                    HTMLBuilder::element( 'h1', 'History' ),
                    ...$this->getMainDisplay(),
                ]
            ),
        ];
    }

    private function getMainDisplay(): array {
        $authError = $this->getAuthError();
        if ( $authError ) {
            return [ $authError ];
        }
        return $this->getPriorResponses();
    }

    private function getPriorResponses(): array {
        $db = new Database;
        $responses = $db->getResponses( AuthManager::getLoggedInUserId() );

        if ( !$responses ) {
            return [
                HTMLBuilder::element(
                    'p',
                    'No responses yet'
                ),
            ];
        }

        $responseRows = array_map(
            static fn ( $resp ) => HTMLBuilder::tableRow(
                [
                    HTMLBuilder::link(
                        '/profile.php?user-id=' . $resp->user_id,
                        $resp->user_tufts_name
                    ),
                    $resp->resp_value === Database::VALUE_RESPONSE_SMASH ? 'Smash' : 'Pass'
                ],
                [ 'class' => 'js-history-response' ]
            ),
            $responses
        );
        $table = HTMLBuilder::element(
            'table',
            [
                HTMLBuilder::element(
                    'thead',
                    HTMLBuilder::element(
                        'tr',
                        [
                            HTMLBuilder::element( 'th', 'Jumbo' ),
                            HTMLBuilder::element( 'th', 'Response' ),
                        ]
                    )
                ),
                HTMLBuilder::element(
                    'tbody',
                    $responseRows
                ),
            ],
            [ 'id' => 'js-history-response-table' ]
        );

        $intro = HTMLBuilder::element(
            'p',
            [
                'Your current responses can be viewed here. To change them, visit ',
                HTMLBuilder::link( './connect.php', 'connect.php' ),
                'and update the rows in the bottom section.'
            ]
        );
        return [ $intro, HTMLBuilder::element( 'br' ), $table ];
    }

}
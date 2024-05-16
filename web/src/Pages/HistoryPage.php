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
        if ( !AuthManager::isLoggedIn() ) {
            return [ $this->mustLogInError() ];
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
                    $resp->user_tufts_name,
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
        return [ $table ];
    }

    private function mustLogInError(): HTMLElement {
        return HTMLBuilder::element(
            'div',
            'ERROR: Must be logged in!',
            [ 'class' => 'js-error' ]
        );
    }

    private function getRowForJumbo( string $jumboDisplay, int $userId ): HTMLElement {
        // ID is used so that we can be sure to have a unique and valid identifier
        $options = [ 'Skip', 'Smash', 'Pass' ];
        $optRadios = array_map(
            fn ( $opt ) => array_reverse( HTMLBuilder::labeledInput(
                'radio',
                [
                    'id' => "js-sp-for-$userId-$opt",
                    'name' => 'js-sp-for-' . $userId,
                    'value' => $opt,
                    ...( $opt === 'Skip' ? [ 'checked' => true ] : [] ),
                ],
                $opt
            ) ),
            $options
        );
        $fields = array_merge( ...$optRadios );
        $fields[] = HTMLBuilder::element(
            'span',
            'How do you feel about: ' . $jumboDisplay . '?'
        );
        return HTMLBuilder::element(
            'div',
            $fields,
            [ 'class' => 'js-response-form-row' ]
        );
    }

    private function getForm(): HTMLElement {
        $fields = $this->getFormFields();
        if ( !$fields ) {
            return HTMLBuilder::element(
                'div',
                'No new Jumbos to respond to'
            );
        };
        return HTMLBuilder::element(
            'form',
            $fields,
            [
                'id' => 'js-submit-response',
                'action' => './response.php',
                'method' => 'POST',
            ]
        );
    }

    private function getFormFields(): array {
        $db = new Database;
        $unresponded = $db->getUnresponded( AuthManager::getLoggedInUserId() );

        if ( !$unresponded ) {
            return [];
        }

        $fields = array_map(
            fn ( $row ) => $this->getRowForJumbo( $row->user_tufts_name, (int)$row->user_id ),
            $unresponded
        );
        $fields[] = HTMLBuilder::element(
            'button',
            'Submit',
            [ 'type' => 'submit', 'id' => 'js-responses-submit', 'class' => 'js-form-button' ]
        );
        return $fields;
    }
}
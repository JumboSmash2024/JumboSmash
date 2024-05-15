<?php

namespace JumboSmash\Pages;

use JumboSmash\HTML\{HTMLBuilder, HTMLElement, HTMLPage};
use JumboSmash\Services\AuthManager;
use JumboSmash\Services\Database;

class ResponsePage extends BasePage {

    private const RESPONSE_SKIP = 'Skip';
    private const RESPONSE_SMASH = 'Smash';
    private const RESPONSE_PASS = 'Pass';

    public function __construct() {
        parent::__construct( 'Response' );
        $this->addStyleSheet( 'response-styles.css' );
    }

    protected function getBodyElements(): array {
        return [
            HTMLBuilder::element(
                'div',
                [
                    HTMLBuilder::element( 'h1', 'Your responses' ),
                    ...$this->getMainDisplay(),
                ],
                [ 'class' => 'center-table' ]
            ),
        ];
    }

    private function getMainDisplay(): array {
        if ( !AuthManager::isLoggedIn() ) {
            return [ $this->mustLogInError() ];
        }
        $isPost = ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST';
        if ( !$isPost ) {
            return [ $this->getForm() ];
        }
        return $this->trySubmit();
    }

    private function trySubmit(): array {
        $db = new Database;
        $unresponded = $db->getUnresponded( AuthManager::getLoggedInUserId() );

        $newResponses = [];
        $errors = [];
        foreach ( $unresponded as $jumbo ) {
            $jumboId = (int)$jumbo->user_id;
            $value = $_POST["js-sp-for-$jumboId"] ?? self::RESPONSE_SKIP;
            if ( $value === self::RESPONSE_SKIP ) {
                continue;
            }
            if ( $value !== self::RESPONSE_SMASH &&
                $value !== self::RESPONSE_PASS
            ) {
                // Invalid?
                $errors[] = HTMLBuilder::element(
                    'p',
                    'Invalid value received for ' . $jumbo->user_tufts_name
                        . ': ' . $value
                );
                continue;
            }
            $dbValue = ( $value === self::RESPONSE_SMASH
                ? Database::VALUE_RESPONSE_SMASH
                : Database::VALUE_RESPONSE_PASS
            );
            // Avoid non-consecutive numeric keys
            $newResponses[] = [ $jumboId, $dbValue ];
        }
        if ( $errors ) {
            return [
                ...$errors,
                $this->getForm(),
            ];
        }
        $user = AuthManager::getLoggedInUserId();
        foreach ( $newResponses as [ $jumboId, $dbValue ] ) {
            $db->recordResponse(
                $user,
                $jumboId,
                $dbValue
            );
        }
        return [
            // HTMLBuilder::element( 'p', var_export( $_POST, true ) ),
            HTMLBuilder::element(
                'div',
                [
                    'Responses recorded!',
                    HTMLBuilder::element( 'br' ),
                    HTMLBuilder::link(
                        './history.php',
                        'View response history'
                    ),
                ]
            ),
        ];
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
                'No new Jumbos to respond to :('
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
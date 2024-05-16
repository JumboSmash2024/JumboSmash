<?php

namespace JumboSmash\Pages;

use JumboSmash\HTML\{HTMLBuilder, HTMLElement};
use JumboSmash\Services\AuthManager;
use JumboSmash\Services\Database;
use JumboSmash\Services\Logger;

class ConnectPage extends BasePage {

    private const RESPONSE_SKIP = 'Skip';
    private const RESPONSE_SMASH = 'Smash';
    private const RESPONSE_PASS = 'Pass';

    private const ACCEPTING_RESPONSES = false;

    public function __construct() {
        parent::__construct( 'Connect' );
        $this->addStyleSheet( 'connect-styles.css' );
    }

    protected function getBodyElements(): array {
        return [
            HTMLBuilder::element(
                'div',
                [
                    HTMLBuilder::element( 'h1', 'Your connections' ),
                    ...$this->getMainDisplay(),
                ],
                [ 'class' => 'center-table' ]
            ),
        ];
    }

    private function getMainDisplay(): array {
        $authError = $this->getAuthError();
        if ( $authError ) {
            return [ $authError ];
        }
        $isPost = ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST';
        if ( !$isPost ) {
            return [ $this->getForm() ];
        }
        if ( !self::ACCEPTING_RESPONSES ) {
            // Someone manually messed with the HTML, sneaky :)
            return [ $this->getForm() ];
        }
        return $this->trySubmit();
    }

    private function trySubmit(): array {
        $db = new Database;
        $currUser = AuthManager::getLoggedInUserId();

        $unresponded = $db->getUnresponded( $currUser );
        $priorResponses = $db->getResponses( $currUser );

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
        $changedResponses = [];
        foreach ( $priorResponses as $prior ) {
            $jumboId = (int)$prior->user_id;
            $priorVal = (
                (int)($prior->resp_value) === Database::VALUE_RESPONSE_SMASH
                    ? self::RESPONSE_SMASH
                    : self::RESPONSE_PASS
            );
            $value = $_POST["js-sp-for-$jumboId"] ?? $priorVal;
            if ( $value === $priorVal ) {
                continue;
            }
            if ( $value !== self::RESPONSE_SMASH &&
                $value !== self::RESPONSE_PASS
            ) {
                if ( $value === self::RESPONSE_SKIP ) {
                    $errors[] = HTMLBuilder::element(
                        'p',
                        'Cannot skip an old answer, only change it (makes the DB easier)'
                    );
                    continue;
                }
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
            $changedResponses[] = [ $jumboId, $dbValue ];
        }
        if ( $errors ) {
            return [
                ...$errors,
                $this->getForm(),
            ];
        }
        $user = AuthManager::getLoggedInUserId();
        $logger = new Logger();
        foreach ( $newResponses as [ $jumboId, $dbValue ] ) {
            $logger->info(
                'Recording response by user #{user} about user #{target}: {value}',
                [
                    'user' => $user,
                    'target' => $jumboId,
                    'value' => ( $dbValue === Database::VALUE_RESPONSE_SMASH ? 'smash' : 'pass' ),
                ]
            );
            $db->recordResponse(
                $user,
                $jumboId,
                $dbValue
            );
        }
        foreach ( $changedResponses as [ $jumboId, $dbValue ] ) {
            $logger->info(
                'Updating response by user #{user} about user #{target}: {value}',
                [
                    'user' => $user,
                    'target' => $jumboId,
                    'value' => ( $dbValue === Database::VALUE_RESPONSE_SMASH ? 'smash' : 'pass' ),
                ]
            );
            $db->updateResponse(
                $user,
                $jumboId,
                $dbValue
            );
        }
        return [
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

    private function getRowForJumbo(
        string $jumboDisplay,
        int $userId,
        string $currResponse
    ): HTMLElement {
        // ID is used so that we can be sure to have a unique and valid identifier
        $options = [
            self::RESPONSE_SKIP,
            self::RESPONSE_SMASH,
            self::RESPONSE_PASS,
        ];
        // If we already have a current response then skipping is disabled
        $skipOpt = (
            $currResponse === self::RESPONSE_SKIP
                ? []
                : [ 'disabled' => true]
        );
        $optRadios = array_map(
            fn ( $opt ) => array_reverse( HTMLBuilder::labeledInput(
                'radio',
                [
                    'id' => "js-sp-for-$userId-$opt",
                    'name' => 'js-sp-for-' . $userId,
                    'value' => $opt,
                    ...( $opt === $currResponse ? [ 'checked' => true ] : [] ),
                    ...( $opt === self::RESPONSE_SKIP ? $skipOpt : [] ),
                    ...( self::ACCEPTING_RESPONSES ? [] : [ 'disabled' => true ] ),
                ],
                $opt
            ) ),
            $options
        );
        $fields = array_merge( ...$optRadios );
        $jumboDisplayLink = HTMLBuilder::link(
            '/profile.php?user-id=' . $userId,
            $jumboDisplay
        );
        $fields[] = HTMLBuilder::element(
            'span',
            [ 'How do you feel about: ', $jumboDisplayLink, '?' ]
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
                'No Jumbos to connect with :('
            );
        };
        return HTMLBuilder::element(
            'form',
            $fields,
            [
                'id' => 'js-submit-response',
                'action' => './connect.php',
                'method' => 'POST',
            ]
        );
    }

    private function getFormFields(): array {
        $db = new Database;
        $currUser = AuthManager::getLoggedInUserId();

        $unresponded = $db->getUnresponded( $currUser );
        $priorResponses = $db->getResponses( $currUser );

        if ( !$unresponded && !$priorResponses ) {
            return [];
        }

        $fields = [];

        if ( $unresponded ) {
            $fields[] = HTMLBuilder::element( 'p', 'New jumbos to connect with:' );
            $fields = array_merge(
                $fields,
                array_map(
                    fn ( $row ) => $this->getRowForJumbo(
                        $row->user_tufts_name,
                        (int)$row->user_id,
                        self::RESPONSE_SKIP
                    ),
                    $unresponded
                )
            );
        }
        if ( $unresponded && $priorResponses ) {
            $fields[] = HTMLBuilder::element( 'hr' );
        }

        if ( $priorResponses ) {
            $fields[] = HTMLBuilder::element( 'p', 'Change old answers:' );
            $fields = array_merge(
                $fields,
                array_map(
                    fn ( $row ) => $this->getRowForJumbo(
                        $row->user_tufts_name,
                        (int)$row->user_id,
                        (
                            (int)$row->resp_value === Database::VALUE_RESPONSE_SMASH
                                ? self::RESPONSE_SMASH
                                : self::RESPONSE_PASS
                        )
                    ),
                    $priorResponses
                )
            );
        }
        
        if ( self::ACCEPTING_RESPONSES ) {
            $fields[] = HTMLBuilder::element(
                'button',
                'Submit',
                [ 'type' => 'submit', 'id' => 'js-responses-submit', 'class' => 'js-form-button' ]
            );
        } else {
            array_unshift( $fields, HTMLBuilder::element( 'br' ) );
            array_unshift(
                $fields,
                HTMLBuilder::element(
                    'p',
                    'Not accepting responses yet. Follow @jumbosmash on Sidechat for updates.'
                )
            );
        }
        return $fields;
    }
}
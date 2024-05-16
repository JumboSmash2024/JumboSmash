<?php

namespace JumboSmash\Pages;

use JumboSmash\HTML\{HTMLBuilder, HTMLElement};
use JumboSmash\MissingUserException;
use JumboSmash\Services\AuthManager;
use JumboSmash\Services\Database;
use JumboSmash\Services\Logger;
use JumboSmash\Services\Management;

class ProfilePage extends BasePage {

    private Database $db;
    private Logger $logger;

    private const MAX_LENGTH = 500;

    public function __construct() {
        parent::__construct( 'Profile' );
        $this->addStyleSheet( 'profile-styles.css' );
        $this->db = new Database();
        $this->logger = new Logger();
    }

    protected function getBodyElements(): array {
        return [
            HTMLBuilder::element(
                'div',
                [
                    HTMLBuilder::element( 'h1', 'Profile' ),
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
        $submitRes = $this->maybeSubmit();
        if ( $submitRes ) {
            return [ $submitRes, ...( $this->getForm() ) ];
        }
        return $this->getForm();
    }

    private function maybeSubmit(): ?HTMLElement {
        $isPost = ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST';
        if ( !$isPost || !isset( $_POST['action'] ) || $_POST['action'] !== 'edit' ) {
            return null;
        }
        if ( !isset( $_POST['target-user'] ) ) {
            return HTMLBuilder::element(
                'p',
                'Missing target user?'
            );
        }
        $targetUser = $_POST['target-user'];
        $currUser = AuthManager::getLoggedInUserId();
        if ( !is_numeric( $targetUser ) || (int)$targetUser !== $currUser ) {
            return HTMLBuilder::element(
                'p',
                'Wrong target user?'
            );
        }

        $profileText = $_POST['new-profile'];
        if ( strlen( $profileText ) > self::MAX_LENGTH ) {
            $len = strlen( $profileText );
            $maxLen = self::MAX_LENGTH;
            return HTMLBuilder::element(
                'p',
                "Profile too long; $len is more than maximum of $maxLen characters"
            );
        }
        $this->db->setProfileText( $currUser, $profileText );
        $this->logger->info(
            'User #{id} updated profile text, now: ```{text}```',
            [ 'id' => $currUser, 'text' => $profileText ]
        );
        return HTMLBuilder::element( 'p', 'Profile updated!' );
    }

    private function getForm(): array {
        $currUser = AuthManager::getLoggedInUserId();
        $targetUser = (int)( $_GET['user-id'] ?? $currUser );

        $isMyProfile = ( $currUser === $targetUser );

        if ( $isMyProfile && ( $_GET['action'] ?? false ) === 'edit' ) {
            return $this->getEditForm();
        }

        try {
            $targetUserRow = $this->db->getAccountById( $targetUser );
        } catch ( MissingUserException $e ) {
            return [
                HTMLBuilder::element(
                    'p',
                    'There is no account with user ID ' . $targetUser
                ),
            ];
        }
        $targetUserStatus = (int)( $targetUserRow->user_status );
        if ( !Management::hasFlag( $targetUserStatus, Management::FLAG_VERIFIED ) ) {
            return [
                HTMLBuilder::element(
                    'p',
                    'The account with the given ID is not yet verified'
                ),
            ];
        }
        if ( Management::hasFlag( $targetUserStatus, Management::FLAG_DISABLED ) ) {
            return [
                HTMLBuilder::element(
                    'p',
                    'The account with the given ID is disabled'
                ),
            ];
        }

        $profileText = $this->db->getProfileText( $targetUser );

        $elems = [];

        $who = ( $isMyProfile ? 'Your' : 'This user\'s' );
        $elems[] = HTMLBuilder::element(
            'p',
            $who . ' Tufts name is: ' . $targetUserRow->user_tufts_name
        );
        if ( $profileText === null ) {
            $who = ( $isMyProfile ? 'You have' : 'This user has' );
            $elems[] = HTMLBuilder::element(
                'p',
                $who . ' not created a profile yet :('
            );
            if ( $isMyProfile ) {
                $elems[] = HTMLBuilder::element(
                    'p',
                    'Please create one (and consider adding a URL with images'
                );
            }
        } else {
            $profIntro = 'Profile text:';
            if ( $isMyProfile ) {
                $profIntro = 'Profile text (consider adding a URL with images)';
            }
            $elems[] = HTMLBuilder::element( 'p', $profIntro );
            $elems[] = HTMLBuilder::element(
                'pre',
                $profileText,
                [ 'class' => 'js-profile-text' ]
            );
        }

        if ( $isMyProfile ) {
            $editForm = HTMLBuilder::element(
                'form',
                [
                    HTMLBuilder::hidden( 'action', 'edit' ),
                    HTMLBuilder::element(
                        'button',
                        'Edit',
                        [ 'type' => 'submit' ]
                    )
                ],
                [ 'method' => 'GET' ]
            );
            $elems[] = $editForm;
        }
        return $elems;
    }
    
    private function getEditForm(): array {
        $currUser = AuthManager::getLoggedInUserId();
        $profileText = $this->db->getProfileText( $currUser );
        $profileText ??= '';

        $currentElems = [];
        if ( $profileText ) {
            $currentDiv = HTMLBuilder::element(
                'div',
                [
                    HTMLBuilder::element( 'span', 'Current profile:' ),
                    HTMLBuilder::element( 'br' ),
                    HTMLBuilder::element(
                        'pre',
                        $profileText,
                        [ 'class' => 'js-profile-text' ]
                    ),
                ]
            );
            $currentElems[] = $currentDiv;
        }

        $maxLen = self::MAX_LENGTH;
        $form = HTMLBuilder::element(
			'form',
			[
                ...$currentElems,
				HTMLBuilder::element( 'br' ),
				HTMLBuilder::element( 'span', "New profile (max length: $maxLen characters):" ),
				HTMLBuilder::hidden( 'action', 'edit' ),
				HTMLBuilder::hidden( 'target-user', $currUser ),
				HTMLBuilder::element(
					'textarea',
					$profileText,
					[
                        'name' => 'new-profile',
                        'id' => 'new-profile',
                        'maxlength' => self::MAX_LENGTH
                    ]
				),
				HTMLBuilder::element(
					'button',
					'Submit',
					[ 'type' => 'submit' ]
				)
			],
			[ 'method' => 'POST' ]
		);
        return [ $form ];
    }

}
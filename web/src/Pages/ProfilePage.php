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

    private const MAX_LENGTH_LINK = 150;
    private const MAX_LENGTH_TEXT = 500;

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

        $profileText = $_POST['new-profile-text'];
        $profileLink = $_POST['new-profile-link'];
        if ( strlen( $profileText ) > self::MAX_LENGTH_TEXT ) {
            $len = strlen( $profileText );
            $maxLen = self::MAX_LENGTH_TEXT;
            return HTMLBuilder::element(
                'p',
                "Profile too long; $len is more than maximum of $maxLen characters"
            );
        }
        if ( strlen( $profileLink ) > self::MAX_LENGTH_LINK ) {
            $len = strlen( $profileLink );
            $maxLen = self::MAX_LENGTH_LINK;
            return HTMLBuilder::element(
                'p',
                "Link too long; $len is more than maximum of $maxLen characters"
            );
        }

        $currProfile = $this->db->getProfile( $currUser );
        $currProfileText = $currProfile ? $currProfile->profile_text : null;
        $currProfileText ??= '';
        $currProfileLink = $currProfile ? $currProfile->profile_link : null;
        $currProfileLink ??= '';

        $changed = false;
        if ( $currProfileText !== $profileText ) {
            $this->db->setProfileText( $currUser, $profileText );
            $this->logger->info(
                'User #{id} updated profile text, now: ```{text}```',
                [ 'id' => $currUser, 'text' => $profileText ]
            );
            $changed = true;
        }
        if ( $currProfileLink !== $profileLink ) {
            $this->db->setProfileLink( $currUser, $profileLink );
            $this->logger->info(
                'User #{id} updated profile link, now: `{link}`',
                [ 'id' => $currUser, 'link' => $profileLink ]
            );
            $changed = true;
        }

        $msg = $changed ? 'Profile updated!' : 'No changes made';
        return HTMLBuilder::element(
            'div',
            [
                HTMLBuilder::element( 'p', $msg ),
                HTMLBuilder::element( 'br' ),
            ]
        );
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

        $profile = $this->db->getProfile( $targetUser );
        $profileText = $profile ? $profile->profile_text : null;
        $profileLink = $profile ? $profile->profile_link : null;

        $elems = [];

        $whoseIs = ( $isMyProfile ? 'Your' : 'This user\'s' );
        $whoHas = ( $isMyProfile ? 'You have' : 'This user has' );

        $elems[] = HTMLBuilder::element(
            'p',
            $whoseIs . ' Tufts name is: ' . $targetUserRow->user_tufts_name
        );

        if ( $profileLink ) {
            $elems[] = HTMLBuilder::element(
                'p',
                [
                    'User-configured link: ',
                    HTMLBuilder::link(
                        $profileLink,
                        $profileLink,
                        [ 'target' => '_blank' ]
                    ),
                ]
            );
        } else {
            $elems[] = HTMLBuilder::element(
                'p',
                $whoHas . ' not added a profile link yet'
            );
        }

        if ( !$profileText ) {
            $elems[] = HTMLBuilder::element(
                'p',
                $whoHas . ' not created a profile yet :('
            );
            if ( $isMyProfile ) {
                $elems[] = HTMLBuilder::element(
                    'p',
                    'Please create one (and consider adding a URL with images)'
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
        
        $profile = $this->db->getProfile( $currUser );
        $profileText = $profile ? $profile->profile_text : '';
        $profileText ??= '';
        $profileLink = $profile ? $profile->profile_link : '';
        $profileLink ??= '';

        $currentElems = [];
        if ( $profileLink ) {
            $currentDiv = HTMLBuilder::element(
                'div',
                [
                    HTMLBuilder::element( 'span', 'Current link:' ),
                    HTMLBuilder::element( 'br' ),
                    HTMLBuilder::link( $profileLink, $profileLink ),
                ]
            );
            $currentElems[] = $currentDiv;
        }
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

        $maxLenText = self::MAX_LENGTH_TEXT;
        $maxLenLink = self::MAX_LENGTH_LINK;
        $form = HTMLBuilder::element(
			'form',
			[
                ...$currentElems,
				HTMLBuilder::element( 'br' ),
				HTMLBuilder::element( 'span', "Link editor (max length: $maxLenLink characters):" ),
				HTMLBuilder::element(
					'textarea',
					$profileLink,
					[
                        'name' => 'new-profile-link',
                        'id' => 'new-profile-link',
                        'maxlength' => self::MAX_LENGTH_LINK,
                    ]
				),
                HTMLBuilder::element( 'br' ),
				HTMLBuilder::element( 'span', "Profile editor (max length: $maxLenText characters):" ),
				HTMLBuilder::element(
					'textarea',
					$profileText,
					[
                        'name' => 'new-profile-text',
                        'id' => 'new-profile-text',
                        'maxlength' => self::MAX_LENGTH_TEXT
                    ]
				),
				HTMLBuilder::element(
					'button',
					'Submit',
					[ 'type' => 'submit' ]
                ),
				HTMLBuilder::hidden( 'action', 'edit' ),
				HTMLBuilder::hidden( 'target-user', $currUser ),
			],
			[ 'method' => 'POST' ]
		);
        return [ $form ];
    }

}
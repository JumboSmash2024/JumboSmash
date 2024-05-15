<?php

namespace JumboSmash\Pages;

use JumboSmash\HTML\{HTMLBuilder, HTMLElement, HTMLPage};
use JumboSmash\Services\AuthManager;
use JumboSmash\Services\Database;
use JumboSmash\Services\PasswordManager;

class SignUpPage extends BasePage {
    private string $signUpError;

    public function __construct() {
        parent::__construct( 'SignUp' );
        $this->addStyleSheet( 'form-styles.css' );
        $this->signUpError = '';
    }

    protected function getBodyElements(): array {
        return [
            HTMLBuilder::element(
                'div',
                [
                    HTMLBuilder::element( 'h1', 'Sign up' ),
                    ...$this->getMainDisplay(),
                ],
                [ 'class' => 'center-table' ]
            ),
        ];
    }

    protected function onBeforePageDisplay(): void {
        if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST'
            && !AuthManager::isLoggedIn()
        ) {
            $this->signUpError = $this->trySubmit();
        }
    }

    private function getMainDisplay(): array {
        $isPost = ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST';
        if ( !$isPost ) {
            if ( AuthManager::isLoggedIn() ) {
                return [ $this->getAlreadyLoggedInError() ];
            }
            return [ $this->getForm() ];
        }
        $submitError = $this->signUpError;
        if ( $this->signUpError !== '' ) {
            return [ $this->getForm() ];
        }
        return [
            HTMLBuilder::element(
                'p',
                'Account successfully created!'
            ),
        ];
    }

    private function trySubmit(): string {
        $personalEmail = $_POST['js-personal-email'];
        $tuftsEmail = $_POST['js-tufts-email'];
        $pass = $_POST['js-password'];
        $passConfirm = $_POST['js-password-confirm'];
        if ( $personalEmail === '' ) {
            return 'Missing personal email';
        } else if ( !filter_var( $personalEmail, FILTER_VALIDATE_EMAIL ) ) {
            return 'Invalid personal email';
        } else if ( $tuftsEmail === '' ) {
            return 'Missing tufts email';
        } else if ( !str_ends_with( $tuftsEmail, '@tufts.edu' ) ) {
            return 'Tufts email must end with `@tufts.edu`';
        } else if ( !filter_var( $tuftsEmail, FILTER_VALIDATE_EMAIL ) ) {
            return 'Invalid tufts email';
        } else if ( $pass === '' || $passConfirm === '' ) {
            return 'Missing password';
        } else if ( $pass !== $passConfirm ) {
            return 'Passwords do not match';
        }

        $tuftsName = substr(
            $tuftsEmail,
            0,
            - strlen( '@tufts.edu' )
        );
        $db = new Database;
        if ( $db->findAccountByTufts( $tuftsName ) ) {
            return 'Tufts email already used';
        }
        if ( $db->findAccountByPersonal( $personalEmail ) ) {
            return 'Personal email already used';
        }
        $hash = PasswordManager::hashForStorage( $pass );
        $id = $db->createAccount( $tuftsName, $personalEmail, $hash );
        AuthManager::loginSession( $id );
        return '';
    }

    private function getAlreadyLoggedInError(): HTMLElement {
        return HTMLBuilder::element(
            'div',
            'ERROR: Already logged in to an account!',
            [ 'class' => 'js-error' ]
        );
    }

    private function getForm(): HTMLElement {
        return HTMLBuilder::element(
            'form',
            $this->getFormFields(),
            [
                'id' => 'js-create-account',
                'action' => './signup.php',
                'method' => 'POST',
            ]
        );
    }

    private function getFormFields(): array {
        $br = HTMLBuilder::element( 'br' );
        $fields = [
            clone $br,
            HTMLBuilder::labeledInput(
                'email',
                [ 'id' => 'js-tufts-email', 'placeholder' => 'Tufts email' ],
                'Tufts email:'
            ),
            clone $br,
            clone $br,
            HTMLBuilder::labeledInput(
                'email',
                [ 'id' => 'js-personal-email', 'placeholder' => 'Personal email' ],
                'Personal email:'
            ),
            clone $br,
            clone $br,
            HTMLBuilder::labeledInput(
                'password',
                [ 'id' => 'js-password', 'placeholder' => 'password' ],
                'Password:'
            ),
            clone $br,
            clone $br,
            HTMLBuilder::labeledInput(
                'password',
                [ 'id' => 'js-password-confirm', 'placeholder' => 'Confirm password' ],
                'Confirm password:'
            ),
            clone $br,
            clone $br,
        ];
        if ( $this->signUpError != '' ) {
            $fields[] = HTMLBuilder::element('div', [], ['class' => 'half-space']);
            $fields[] = HTMLBuilder::element(
                'p',
                $this->signUpError,
                [ 'class' => 'js-error ' ]
            );
            $fields[] = HTMLBuilder::element('div', [], ['class' => 'half-space']);
        } else {
            $fields[] = HTMLBuilder::element('div', [], ['class' => 'space']);
        }
        $fields[] = HTMLBuilder::element(
            'button',
            'Create account',
            [ 'type' => 'submit',
                'id' => 'js-create-account-submit', 'class' => 'js-form-button' ]
        );
        $fieldArrs = array_map(
            fn ( $v ) => is_array( $v ) ? $v : [ $v ],
            $fields
        );
        return array_merge( ...$fieldArrs );
    }
}
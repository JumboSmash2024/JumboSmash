<?php

namespace JumboSmash\Pages;

use JumboSmash\HTML\{HTMLBuilder, HTMLElement};
use JumboSmash\Services\AuthManager;
use JumboSmash\Services\Database;
use JumboSmash\Services\PasswordManager;

class LoginPage extends BasePage {

    private string $loginError;

    public function __construct() {
        parent::__construct( 'Login' );
        $this->addStyleSheet( 'form-styles.css' );
        $this->loginError = '';
    }

    protected function getBodyElements(): array {
        return [
            HTMLBuilder::element(
                'div',
                [
                    HTMLBuilder::element( 'h1', 'Login' ),
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
            $this->loginError = $this->trySubmit();
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
        if ( $this->loginError !== '' ) {
            return [ $this->getForm() ];
        }
        return [
            HTMLBuilder::element(
                'p',
                'Account login successful!'
            ),
            HTMLBuilder::element( 'br' ),
            HTMLBuilder::element( 'br' ),
            HTMLBuilder::element( 'br' ),
            HTMLBuilder::link(
                './index.php',
                HTMLBuilder::element(
                    'button',
                    'Go Home',
                    [ 'class' => 'js-form-redirect' ]
                )
            ),
        ];
    }

    private function trySubmit(): string {
        $email = $_POST['js-personal-email'];
        $pass = $_POST['js-password'];
        if ( $email === '' ) {
            return 'Missing email';
        } else if ( $pass === '' ) {
            return 'Missing password';
        }
        $db = new Database;
        $accountInfo = $db->findAccountByPersonal( $email );
        if ( $accountInfo === null ) {
            return 'Email not associated with an account';
        }
        $hash = PasswordManager::hashForStorage( $pass );
        if ( $hash !== $accountInfo->user_pass_hash ) {
            return 'Incorrect password';
        }
        AuthManager::loginSession( $accountInfo->user_id );
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
                'id' => 'js-login',
                'action' => './login.php',
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
                [ 'id' => 'js-personal-email', 'placeholder' => 'email' ],
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
        ];
        if ( $this->loginError != '' ) {
            $fields[] = HTMLBuilder::element('div', [], ['class' => 'half-space']);
            $fields[] = HTMLBuilder::element(
                'p',
                $this->loginError,
                [ 'class' => 'js-error ' ]
            );
            $fields[] = HTMLBuilder::element('div', [], ['class' => 'half-space']);
        } else {
            $fields[] = HTMLBuilder::element('div', [], ['class' => 'space']);
        }
        $fields[] = HTMLBuilder::element(
            'button',
            'Login',
            [ 'type' => 'submit', 'id' => 'js-login-submit', 'class' => 'js-form-button' ]
        );
        $fields[] = clone $br;
        $fields[] = clone $br;
        $fields[] = HTMLBuilder::link(
            './signup.php',
            'Create account',
            [ 'id' => 'js-login-signup', 'class' => 'js-form-button' ]
        );
        $fieldArrs = array_map(
            fn ( $v ) => is_array( $v ) ? $v : [ $v ],
            $fields
        );
        return array_merge( ...$fieldArrs );
    }
}
<?php

namespace JumboSmash\Pages;

use JumboSmash\HTML\{HTMLBuilder, HTMLPage, HTMLElement};
use JumboSmash\Services\AuthManager;

abstract class BasePage {

    private HTMLPage $page;
    private bool $loadedBodyContent = false;

    protected function __construct( string $pageTitle ) {
        $this->page = new HTMLPage();
        $this->page->addHeadElement(
            HTMLBuilder::element( 'title', $pageTitle )
        );
        // Prevent trying to read a favicon that we don't have
        $this->page->addHeadElement(
            HTMLBuilder::element(
                'link',
                [],
                [ 'rel' => 'icon', 'href' => 'data:,' ]
            )
        );
        // Always add global-styles.css
        $this->addStyleSheet( 'global-styles.css' );

        // Body from getBodyElements() is added in getOutput() so that subclass
        // constructor code after calling this parent constructor can take
        // effect
    }

    protected function addScript( string $fileName ): void {
        $this->page->addHeadElement(
            HTMLBuilder::element(
                'script',
                [],
                [ 'src' => "./resources/{$fileName}" ]
            )
        );
    }
    protected function addStyleSheet( string $fileName ): void {
        $this->page->addHeadElement(
            HTMLBuilder::element(
                'link',
                [],
                [
                    'rel' => 'stylesheet',
                    'type' => 'text/css',
                    'href' => "./resources/{$fileName}",
                ]
            )
        );
    }

    // Some pages (Login and Logout) need to be able to do stuff session-related
    // *before* the sidebar is created, if nothing is needed just leave empty
    protected function onBeforePageDisplay(): void {
        // No-op by default
    }

    public function getOutput(): string {
        // Don't load multiple times
        if ( !$this->loadedBodyContent ) {
            $this->onBeforePageDisplay();
            $this->loadedBodyContent = true;

            $this->page->addBodyElement(
                HTMLBuilder::element(
                    'div',
                    array_values( $this->getTopNavElements() ),
                    [ 'id' => 'js-top-nav' ]
                )
            );
            $this->page->addBodyElement(
                HTMLBuilder::element(
                    'div',
                    $this->getBodyElements(),
                    [ 'class' => 'body-content-wrapper' ]
                )
            );
        }
        return $this->page->getPageOutput();
    }

    abstract protected function getBodyElements(): array;

    protected function getTopNavElements(): array {
        $helpPageLink = HTMLBuilder::element(
            'div',
            HTMLBuilder::link(
                './help.php',
                'Help',
                [ 'id' => 'js-nav-help' ]
            ),
            [ 'id' => 'js-nav-help-wrapper' ]
        );
        if ( !AuthManager::isLoggedIn() ) {
            return [
                $helpPageLink,
                HTMLBuilder::element(
                    'div',
                    HTMLBuilder::link(
                        './login.php',
                        'Log in',
                        [ 'id' => 'js-nav-login' ]
                    ),
                    [ 'id' => 'js-nav-login-wrapper' ]
                ),
                HTMLBuilder::element(
                    'div',
                    HTMLBuilder::link(
                        './signup.php',
                        'Sign up',
                        [ 'id' => 'js-nav-signup' ]
                    ),
                    [ 'id' => 'js-nav-signup-wrapper' ]
                ),
            ];
        }
        $elems = [];
        $elems['manage'] = HTMLBuilder::element(
            'div',
            HTMLBuilder::link(
                './manage.php',
                'Manage',
                [ 'id' => 'js-nav-manage' ]
            ),
            [ 'id' => 'js-nav-manage-wrapper' ]
        );
        $elems['help'] = $helpPageLink;
        $elems['connect'] = HTMLBuilder::element(
            'div',
            HTMLBuilder::link(
                './connect.php',
                'Connect',
                [ 'id' => 'js-nav-connect' ]
            ),
            [ 'id' => 'js-nav-connect-wrapper' ]
        );
        $elems['history'] = HTMLBuilder::element(
            'div',
            HTMLBuilder::link(
                './history.php',
                'History',
                [ 'id' => 'js-nav-history' ]
            ),
            [ 'id' => 'js-nav-history-wrapper' ]
        );
        $elems['matches'] = HTMLBuilder::element(
            'div',
            HTMLBuilder::link(
                './matches.php',
                'Matches',
                [ 'id' => 'js-nav-matches' ]
            ),
            [ 'id' => 'js-nav-matches-wrapper' ]
        );
        $elems['logout'] = HTMLBuilder::element(
            'div',
            HTMLBuilder::link(
                './logout.php',
                'Log out',
                [ 'id' => 'js-nav-logout' ]
            ),
            [ 'id' => 'js-nav-logout-wrapper' ]
        );
        return $elems;
    }

    protected function getAuthError(): ?HTMLElement {
        if ( !AuthManager::isLoggedIn() ) {
            return HTMLBuilder::element(
                'div',
                'ERROR: Must be logged in!',
                [ 'class' => 'js-error' ]
            ); 
        }
        if ( !AuthManager::isVerified() ) {
            return HTMLBuilder::element(
                'div',
                'ERROR: Account is not verified! See help page for details.',
                [ 'class' => 'js-error' ]
            );
        }
        return null;
    }

}
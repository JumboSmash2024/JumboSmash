<?php

/**
 * Used to create the output page
 */

namespace JumboSmash\Pages;

use JumboSmash\HTML\{HTMLBuilder, HTMLElement, HTMLPage};
use JumboSmash\Services\AuthManager;
use JumboSmash\Services\Logger;

abstract class BasePage {
    protected HTMLPage $page;
    private bool $loadedBodyContent = false;

    protected function __construct( string $pageTitle ) {
        ( new Logger )->debug( 'Starting new request to: ' . $_SERVER['REQUEST_URI'] );
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
        if ( !AuthManager::isLoggedIn() ) {
            return [
                HTMLBuilder::element(
                    'div',
                    HTMLBuilder::link(
                        './login.php',
                        'Log in',
                        [ 'id' => 'js-nav-login' ]
                    ),
                    [ 'id' => 'js-nav-login-wrapper' ]
                ),
            ];
        }
        $elems = [];
        $elems['respond'] = HTMLBuilder::element(
            'div',
            HTMLBuilder::link(
                './response.php',
                'Respond',
                [ 'id' => 'js-nav-respond' ]
            ),
            [ 'id' => 'js-nav-respond-wrapper' ]
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
}
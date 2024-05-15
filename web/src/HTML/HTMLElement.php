<?php

/**
 * Utility for HTML elements that have been built and the contents should not
 * be escaped.
 */

namespace JumboSmash\HTML;

class HTMLElement {

    public function __construct( private string $contents ) {
    }

    /** @return string */
    public function toString(): string {
        return $this->contents;
    }

}
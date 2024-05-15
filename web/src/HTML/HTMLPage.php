<?php

/**
 * Generic HTML output
 */

namespace JumboSmash\HTML;

class HTMLPage {
    /** @var HTMLElement[] */
    private array $headItems = [];

    /** @var HTMLElement[] */
    private array $bodyItems = [];

    /** No-op but explicit */
    public function __construct() {
    }

    public function addHeadElement( HTMLElement $elem ) {
        $this->headItems[] = $elem;
        return $this;
    }

    public function addBodyElement( HTMLElement $elem ) {
        $this->bodyItems[] = $elem;
        return $this;
    }

    public function getPageOutput(): string {
        $html = HTMLBuilder::element(
            'html',
            [
                HTMLBuilder::element( 'head', $this->headItems ),
                HTMLBuilder::element( 'body', $this->bodyItems ),
            ]
        );
        $docType = "<!DOCTYPE html>\n";
        return $docType . $html->toString();
    }
}
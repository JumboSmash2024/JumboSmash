<?php

require_once __DIR__ . '/src/setup.php';

$page = new \JumboSmash\Pages\MatchesPage();
echo $page->getOutput();


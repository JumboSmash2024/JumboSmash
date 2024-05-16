<?php

require_once __DIR__ . '/src/setup.php';

$page = new \JumboSmash\Pages\HelpPage();
echo $page->getOutput();


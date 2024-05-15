<?php

require_once __DIR__ . '/src/setup.php';

$page = new JumboSmash\Pages\LoginPage();

echo $page->getOutput();
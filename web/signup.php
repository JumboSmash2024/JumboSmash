<?php

require_once __DIR__ . '/src/setup.php';

$page = new JumboSmash\Pages\SignUpPage();

echo $page->getOutput();
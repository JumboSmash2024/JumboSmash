<?php

require_once __DIR__ . '/src/setup.php';

// Alias for help.php - old LandingPage class removed
$page = new \JumboSmash\Pages\HelpPage();
echo $page->getOutput();


#!/usr/bin/env php
<?php
$root = __DIR__;
$ns = 'SNOWGIRL_SHOP';
$app = require $root . '/vendor/snowgirl-core/boot.php';
$app->runCmd($argv);

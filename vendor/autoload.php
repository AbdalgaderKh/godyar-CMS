<?php
// vendor/autoload.php

// لو فيه composer autoload (لو استخدمت composer مستقبلاً)
$composerAutoload = __DIR__ . '/autoload_composer.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

// Autoloader الداخلي لجويار
$godyarAutoload = __DIR__ . '/../includes/autoload.php';
if (is_file($godyarAutoload)) {
    require_once $godyarAutoload;
}

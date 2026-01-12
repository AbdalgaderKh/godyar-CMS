<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
session_destroy();
header("Location: /godyar/");

<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;

$firebase = (new Factory)
    ->withServiceAccount(__DIR__ . '/firebase-service-account.json');

$firebaseAuth = $firebase->createAuth();

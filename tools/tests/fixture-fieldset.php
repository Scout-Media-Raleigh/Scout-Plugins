<?php
// Test fixture, CI only: copied into mu-plugins on the fresh install, together
// with fixture-fieldset-register.php.
require_once __DIR__ . '/fixture-fieldset-register.php';
add_action( 'scout_core_register', 'scout_core_test_register_fieldset' );

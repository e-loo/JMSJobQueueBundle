<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

call_user_func(function (): void {
    if (!is_file($autoloadFile = __DIR__.'/../vendor/autoload.php')) {
        throw new \LogicException('Could not find vendor/autoload.php. Did you run "composer install --dev"?');
    }
});

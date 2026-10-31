<?php

namespace App;

/**
 * Application-wide context.
 *
 * Adds the parsed configuration array to the framework's shared context so
 * controllers and services can reach it through `AppContext::instance()`.
 */
class AppContext extends \Azera\AppContext
{
    public array $Config = [];
}

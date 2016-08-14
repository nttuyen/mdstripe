<?php
/**
 * Copyright (C) Mijn Presta - All Rights Reserved
 *
 * Unauthorized copying of this file, via any medium is strictly prohibited
 *
 * @author    Michael Dekker <prestashopaddons@mijnpresta.nl>
 * @copyright 2015-2016 Mijn Presta
 * @license   proprietary
 * Intellectual Property of Mijn Presta
 */

spl_autoload_register(
    function ($className) {
        if (file_exists(dirname(__FILE__).DIRECTORY_SEPARATOR.$className.'.php')) {
            require_once $className.'.php';

            return true;
        }

        return false;
    }
);

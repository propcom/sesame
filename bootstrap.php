<?php

\Autoloader::add_namespace( 'Sesame', __DIR__ . '/classes/' );
\Autoloader::alias_to_namespace('Sesame\Sesame');
\Autoloader::alias_to_namespace('Sesame\ACL');

$module_paths = \Config::get('module_paths');
$module_paths[] = __DIR__ . '/modules/';
\Config::set('module_paths', $module_paths);

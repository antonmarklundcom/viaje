<?php
// Repo-root front controller, so the repository can be deployed as-is by hPanel → Advanced → GIT.
// (tools/build.php still produces the classic dist/ layout with site/ next to engine/.)
define('VJ_SITE', __DIR__ . '/sites/viaje.com.py');
require __DIR__ . '/engine/bootstrap.php';

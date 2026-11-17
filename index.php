<?php
/**
 * Site root entry point.
 *
 * The public site lives in customer/nav.html and the admin console in
 * admin/index.php. Before this file existed, "/" had no index document, so
 * Apache answered with a 403 (and, before the Options -Indexes fix, with a
 * directory listing). Send visitors to the real homepage instead.
 *
 * 302 rather than 301: the project currently deploys under /pdrsystem/, and a
 * cached permanent redirect would be painful to undo when that changes.
 */

header('Location: customer/nav.html', true, 302);
exit();

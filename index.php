<?php

declare(strict_types=1);

/**
 * Fallback front controller.
 *
 * The Apache vhost in the Dockerfile serves public/ as the document root, so
 * this file is never reached in a correctly built image. It exists so the
 * application still boots when the container is served from the application
 * directory instead, which is what happens if the document root is
 * misconfigured. The real front controller stays public/index.php.
 */

require __DIR__.'/public/index.php';

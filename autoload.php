<?php

declare(strict_types=1);

// Para quem não usa Composer: require 'mozesms-sdk/php/autoload.php';
spl_autoload_register(static function (string $class): void {
    $prefix = 'MozeSMS\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

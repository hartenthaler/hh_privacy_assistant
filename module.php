<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\PrivacyAssistant;

use Composer\Autoload\ClassLoader;
use Hartenthaler\Webtrees\Module\PrivacyAssistant\PrivacyAssistantModule;

$loader = new ClassLoader();
$loader->addPsr4('Hartenthaler\\Webtrees\\Module\\PrivacyAssistant\\', __DIR__ . DIRECTORY_SEPARATOR . 'src');
$loader->register();

return new PrivacyAssistantModule();

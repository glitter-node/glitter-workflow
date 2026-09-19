<?php

namespace Modules\Glitter\Workflow\Upgrades;

use App\Extension\AbstractUpgradeStep;

/**
 * Canonicalize the legacy empty JSON array written by the 0.8.0 installer.
 *
 * @upgrade-path A
 */
class Upgrade_0_8_1 extends AbstractUpgradeStep {}

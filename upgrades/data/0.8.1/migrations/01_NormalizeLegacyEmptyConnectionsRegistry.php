<?php

namespace App\Upgrades\Data\Ext\Modules\GlitterWorkflow\V0_8_1\Migrations;

use App\Extension\Upgrade\DataMigration;
use App\Extension\UpgradeContext;
use App\Support\ExtensionStoragePath;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Rewrites only the exact legacy empty-array representation (`[]`) to `{}`.
 * Existing non-empty connection maps and malformed files are deliberately left
 * untouched so an update cannot discard a connection definition.
 */
class NormalizeLegacyEmptyConnectionsRegistry implements DataMigration
{
    public function name(): string
    {
        return 'NormalizeLegacyEmptyConnectionsRegistry';
    }

    public function run(UpgradeContext $context): void
    {
        $path = ExtensionStoragePath::module('glitter-workflow', 'settings').'/connections.json';

        if (! File::isFile($path)) {
            $context->logger->info('[workflow:0.8.1] connections registry absent — normalization skipped');

            return;
        }

        if (! $this->isLegacyEmptyArray(File::get($path))) {
            $context->logger->info('[workflow:0.8.1] connections registry is not legacy empty array — normalization skipped');

            return;
        }

        if (File::put($path, "{}\n") === false) {
            throw new RuntimeException('Unable to canonicalize Glitter Workflow connections registry.');
        }

        $context->logger->info('[workflow:0.8.1] legacy empty connections registry canonicalized');
    }

    private function isLegacyEmptyArray(string $contents): bool
    {
        $decoded = json_decode($contents);

        return is_array($decoded) && $decoded === [];
    }
}

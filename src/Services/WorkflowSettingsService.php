<?php

namespace Modules\Glitter\Workflow\Services;

use App\Contracts\Extension\ModuleSettingsInterface;
use App\Contracts\Extension\StorageInterface;
use Illuminate\Support\Arr;
use Modules\Glitter\Workflow\Support\WorkflowLimits;

final class WorkflowSettingsService implements ModuleSettingsInterface
{
    public function __construct(private readonly StorageInterface $storage) {}

    public function getSettingsDefaultsPath(): ?string
    {
        $path = dirname(__DIR__, 2).'/config/settings/defaults.json';
        return is_file($path) ? $path : null;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, WorkflowLimits::DEFAULTS)) $key = 'limits.'.$key;
        return Arr::get($this->getAllSettings(), $key, $default);
    }

    public function setSetting(string $key, mixed $value): bool
    {
        if (in_array($key, ['limits', 'connections'], true)) return $this->saveSettings([$key => $value]);
        $field = str_starts_with($key, 'limits.') ? substr($key, 7) : $key;
        return $this->saveSettings(['limits' => [$field => $value]]);
    }

    public function getAllSettings(): array
    {
        $path = $this->getSettingsDefaultsPath();
        $schema = $path === null ? null : json_decode((string) file_get_contents($path), true);
        $defaults = WorkflowLimits::normalize(is_array($schema['defaults']['limits'] ?? null) ? $schema['defaults']['limits'] : []);
        $limits = json_decode((string) ($this->storage->get('settings', 'limits.json') ?? ''), true);
        $connections = json_decode((string) ($this->storage->get('settings', 'connections.json') ?? ''), true);
        return [
            'limits' => WorkflowLimits::normalize(is_array($limits) ? $limits : [], $defaults),
            'connections' => $this->normalizeConnections(is_array($connections) ? $connections : []),
        ];
    }

    public function getSettings(string $category): array
    {
        return in_array($category, ['limits', 'connections'], true) ? $this->getAllSettings()[$category] : [];
    }

    /**
     * Initialize the two module-owned settings files before G7's generic
     * initializer can lose the JSON-object type of an empty connections map.
     */
    public function initializeDefaults(): bool
    {
        if ($this->storage->exists('settings', 'limits.json') || $this->storage->exists('settings', 'connections.json')) return true;

        $path = $this->getSettingsDefaultsPath();
        $schema = $path === null ? null : json_decode((string) file_get_contents($path), true);
        $defaults = WorkflowLimits::normalize(is_array($schema['defaults']['limits'] ?? null) ? $schema['defaults']['limits'] : []);

        return $this->storage->put('settings', 'limits.json', json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))
            && $this->storage->put('settings', 'connections.json', $this->encodeConnections([]));
    }

    public function saveSettings(array $settings): bool
    {
        if (array_diff(array_keys($settings), ['limits', 'connections']) !== [] || $settings === []) return $settings === [];
        if (array_key_exists('limits', $settings) && (! is_array($settings['limits']) || ! $this->validLimits($settings['limits']))) return false;
        if (array_key_exists('connections', $settings) && (! is_array($settings['connections']) || $this->normalizeConnections($settings['connections']) !== $settings['connections'])) return false;
        $saved = true;
        if (array_key_exists('limits', $settings)) $saved = $this->storage->put('settings', 'limits.json', json_encode(array_replace($this->getSettings('limits'), $settings['limits']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if ($saved && array_key_exists('connections', $settings)) $saved = $this->storage->put('settings', 'connections.json', $this->encodeConnections($settings['connections']));
        if ($saved) g7_refresh_module_settings_config('glitter-workflow');
        return $saved;
    }

    public function maxActionsPerWorkflow(): int { return $this->getAllSettings()['limits']['max_actions_per_workflow']; }
    public function conditionDepth(): int { return $this->getAllSettings()['limits']['max_condition_depth']; }
    public function recursionDepth(): int { return 0; }
    /** @return array{url: string}|null */ public function webhookConnection(string $key): ?array { return $this->getSettings('connections')[$key] ?? null; }
    public function getFrontendSettings(): array { return []; }

    private function validLimits(array $limits): bool { foreach ($limits as $key => $value) if (! WorkflowLimits::accepts((string) $key, $value)) return false; return true; }
    /** @param array<string, array{url: string}> $connections */
    private function encodeConnections(array $connections): string
    {
        return json_encode($connections === [] ? new \stdClass() : $connections, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
    /** @param array<mixed> $connections @return array<string, array{url: string}> */
    private function normalizeConnections(array $connections): array
    {
        $normalized = [];
        foreach ($connections as $key => $connection) {
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $key) !== 1 || ! is_array($connection) || array_keys($connection) !== ['url'] || ! is_string($connection['url']) || strlen($connection['url']) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $connection['url']) === 1) return [];
            $parts = parse_url($connection['url']);
            if ($parts === false || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) return [];
            $normalized[$key] = ['url' => $connection['url']];
        }
        ksort($normalized, SORT_STRING);
        return $normalized;
    }
}

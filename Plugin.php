<?php

namespace Ompmega\WebpackManifest;

use October\Rain\Exception\SystemException;
use System\Classes\PluginBase;
use Cms\Classes\Theme;
use Cms\Classes\Asset;
use Carbon\Carbon;
use Config;
use Cache;
use Cms;

/**
 * Class Plugin
 *
 * @package Ompmega\WebpackManifest
 */
class Plugin extends PluginBase
{
    /**
     * {@inheritdoc}
     */
    public function pluginDetails(): array
    {
        return [
            'name'          => 'Webpack Manifest',
            'description'   => 'Support for webpack-manifest-plugin using manifest() twig function to read manifest.json.',
            'author'        => 'Ompmega',
            'icon'          => 'oc-icon-puzzle-piece',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function registerMarkupTags(): array
    {
        return [
            'functions' => [
                'manifest' => [$this, 'readManifest']
            ]
        ];
    }

    /**
     * Locate contents of generated manifest file.
     *
     * @param string $path
     * @return string
     * @throws SystemException
     */
    public function readManifest(string $path): string
    {
        $theme = Theme::getActiveTheme();
        $manifestCacheKey = sprintf('%s:%s', $theme->getDirName(), 'manifest' );

        // Skips caching when debug mode enabled
        if (Config::get('app.debug')) {
            $manifest = $this->getManifest($theme);
        }
        else {
            $manifest = Cache::get($manifestCacheKey, function () use ($theme, $manifestCacheKey) {

                $manifest = $this->getManifest($theme);

                Cache::add(
                    $manifestCacheKey,
                    $manifest,
                    Carbon::now()->addHour()
                );

                return $manifest;
            });
        }

        if (!isset($manifest[$path])) {
            throw new SystemException("Unable to locate manifest.json file: {$path}.");
        }

        return sprintf('/themes/%s/assets/public/', $theme->getDirName()) . $manifest[$path];
    }

    /**
     * Loads the manifest contents and parses to JSON.
     *
     * @param Cms\Classes\Theme $theme
     * @return array
     * @throws SystemException
     */
    protected function getManifest($theme): array
    {
        $asset = Asset::load($theme, 'public/manifest.json');

        if (!$asset || is_null($asset)) {
            throw new SystemException("The manifest.json file does not exist.");
        }

        return json_decode($asset->content, true);
    }
}

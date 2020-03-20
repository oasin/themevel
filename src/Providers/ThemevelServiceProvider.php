<?php

namespace Oasin\Themevel\Providers;

use App;
use File;
use Illuminate\Support\ServiceProvider;
use Oasin\Themevel\Console\ThemeListCommand;
use Oasin\Themevel\Contracts\ThemeContract;
use Oasin\Themevel\Managers\Theme;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;



class ThemevelServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if (!File::exists(public_path('Themes')) && config('theme.symlink') && File::exists(config('theme.theme_path'))) {
            App::make('files')->link(config('theme.theme_path'), public_path('Themes'));
        }

        $this->registerBladeDirectives();
    }

    /**
     * @var string|null $css The path to use for the css directive, or null to not apply the directive.
     */
    private $css = "css";

    /**
     * @var string|null $js The path to use for the javascript directive, or null to not apply the directive.
     */
    private $js = "js";

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->publishConfig();
        $this->registerTheme();
        $this->registerHelper();
        $this->consoleCommand();
        $this->registerMiddleware();
        $this->loadViewsFrom(__DIR__ . '/../Views', 'themevel');
    }

    protected function registerBladeDirectives()
    {
        /*--------------------------------------------------------------------------
        | Extend Blade to support Orcherstra\Asset (Asset Managment)
        |
        | Syntax:
        |
        |   @css (filename, alias, depends-on-alias)
        |   @js  (filename, alias, depends-on-alias)
        |--------------------------------------------------------------------------*/



        if ($this->css !== null) {
            Blade::directive("css", function ($parameter) {
                assert(is_string($this->css));
                $file = $this->assetify(($parameter), "css", $this->css);
                return sprintf('<link media="all" type="text/css" rel="stylesheet" href="%s">', $file);
            });
        }

        if ($this->js !== null) {
            Blade::directive("js", function ($parameter) {
                assert(is_string($this->js));
                $file = $this->assetify(($parameter), "js", $this->js);
                return sprintf('<script type="text/javascript" src="%s"></script>', $file);
            });
        }

        Blade::directive('asset', function ($file) {

            $file = str_replace(['(', ')', "'"], '', $file);
            $filename = $file;

            // Internal file
            if (!Str::startsWith($file, '//') && !Str::startsWith($file, 'http')) {
                // $version = File::lastModified(themes('js/') . '/' . $file);
                $filename = $file . '?v=' . time();
                if (!Str::startsWith($filename, '/')) {
                    $filename = themes($filename);
                }
            }

            $fileType = substr(strrchr($file, '.'), 1);

            if ($fileType == 'js') {
                return sprintf('<script type="text/javascript" src="%s"></script>', $filename);
            } else {
                return sprintf('<link media="all" type="text/css" rel="stylesheet" href="%s">', $filename);
            }
            
        });
    }


    /**
     * Convert a simple name into a full asset path.
     *
     * @param string $file The simple file name
     * @param string $type The type of asset (css/js)
     * @param string $path The path the asset is stored at
     *
     * @return string The full path to the asset
     */
    private function assetify(string $file, string $type, string $path): string
    {
        if (in_array(substr($file, 0, 1), ["'", '"'], true)) {
            $file = trim($file, "'\"");
        } else {
            return "{{ {$file} }}";
        }

        if (substr($file, 0, 8) === "https://") {
            return $file;
        }

        if (substr($file, 0, 7) === "http://") {
            return $file;
        }

        if (substr($file, 0, 1) !== "/") {
            $path = trim($path, "/");
            if (strlen($path) > 0) {
                $path = "{$path}/";
            } else {
                $path = "/";
            }
            $file = themes($path . $file);
        }

        if (substr($file, (strlen($type) + 1) * -1) !== ".{$type}") {
            $file .= ".{$type}";
        }

        return $file;
    }

    /**
     * Add Theme Types Middleware.
     *
     * @return void
     */
    public function registerMiddleware()
    {
        if (config('theme.types.enable')) {
            $themeTypes = config('theme.types.middleware');
            foreach ($themeTypes as $middleware => $themeName) {
                $this->app['router']->aliasMiddleware($middleware, '\Oasin\Themevel\Middleware\RouteMiddleware:' . $themeName);
            }
        }
    }

    /**
     * Register theme required components .
     *
     * @return void
     */
    public function registerTheme()
    {
        $this->app->singleton(ThemeContract::class, function ($app) {
            $theme = new Theme($app, $this->app['view']->getFinder(), $this->app['config'], $this->app['translator']);

            return $theme;
        });
    }

    /**
     * Register All Helpers.
     *
     * @return void
     */
    public function registerHelper()
    {
        foreach (glob(__DIR__ . '/../Helpers/*.php') as $filename) {
            require_once $filename;
        }
    }

    /**
     * Publish config file.
     *
     * @return void
     */
    public function publishConfig()
    {
        $configPath = realpath(__DIR__ . '/../../config/theme.php');

        $this->publishes([
            $configPath => config_path('theme.php'),
        ]);

        $this->mergeConfigFrom($configPath, 'themevel');
    }

    /**
     * Add Commands.
     *
     * @return void
     */
    public function consoleCommand()
    {
        $this->registerThemeGeneratorCommand();
        $this->registerThemeListCommand();
        // Assign commands.
        $this->commands(
            'theme.create',
            'theme.list'
        );
    }

    /**
     * Register generator command.
     *
     * @return void
     */
    public function registerThemeGeneratorCommand()
    {
        $this->app->singleton('theme.create', function ($app) {
            return new \Oasin\Themevel\Console\ThemeGeneratorCommand($app['config'], $app['files']);
        });
    }

    /**
     * Register theme list command.
     *
     * @return void
     */
    public function registerThemeListCommand()
    {
        $this->app->singleton('theme.list', ThemeListCommand::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}

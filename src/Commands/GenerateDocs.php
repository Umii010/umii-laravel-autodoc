<?php

namespace Umii\AutoDoc\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use Dompdf\Dompdf;
use Dompdf\Options;

class GenerateDocs extends Command
{
    protected $signature = 'autodoc:generate {--screenshots}';
    protected $description = 'Generate autodocs for the current Laravel app (routes, models, config, ERD, PDF)';

    public function handle()
    {
        $data['models'] = $this->gatherModels();
$data['database_schema'] = $this->gatherDatabaseSchema();

// 🔥 Merge model relationships into database schema
foreach ($data['models'] as $class => $m) {
    $table = $m['table'] ?? null;
    if ($table && isset($data['database_schema'][$table])) {
        foreach ($m['relationships'] as $rel) {
            $data['database_schema'][$table]['relationships'][] = [
                'type'         => $rel['type'],
                'method'       => $rel['method'],
                'related_table'=> class_basename($rel['related']),
                'local_key'    => $rel['local_key'] ?? null,
                'foreign_key'  => $rel['foreign_key'] ?? null,
            ];
        }
    }
}

        $this->info('Starting Umii AutoDoc generation...');
        $config = config('umii_autodoc', []);
        $out = $config['output_path'] ?? base_path('docs');
        if (!File::isDirectory($out)) {
            File::makeDirectory($out, 0755, true);
        }

        // Gather all data
        $data = [];
        $data['routes'] = $this->gatherRoutes();
        $data['controllers'] = $this->gatherControllers();
        $data['models'] = $this->gatherModels();
        $data['migrations'] = $this->gatherMigrations();
        $data['configs'] = $this->gatherConfigs();
        $data['policies'] = $this->gatherPolicies();
        $data['packages'] = $this->gatherPackages();
        $data['stats'] = $this->gatherStats($data);
        $data['laravel_version'] = app()->version();
        $data['database_schema'] = $this->gatherDatabaseSchema();

        // === ERD PlantUML ===
        $puml = $this->generatePlantUml($data['models']);
        File::put($out . '/erd.puml', $puml);
        $this->info('Wrote ERD PUML to ' . realpath($out . '/erd.puml'));
        $this->generatePlantUmlPng($out);

        // Render Blade → PDF
        $html = view('umii_autodoc::template', $data)->render();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $pdfPath = $out . '/docs.pdf';
        File::put($pdfPath, $dompdf->output());
        $this->info('Wrote PDF docs to ' . realpath($pdfPath));

        // Screenshots (optional)
        if ($this->option('screenshots') || ($config['take_screenshots'] ?? false)) {
            $this->info('Attempting screenshots (best-effort)...');
            $this->takeScreenshots($out, $config);
        } else {
            $this->info('Screenshots skipped.');
        }

        $this->info('Umii AutoDoc generation complete.');
    }

    /** Gather database schema */
    protected function gatherDatabaseSchema()
    {
        $schema = [];
        try {
            $tables = \DB::select('SHOW TABLES');
            foreach ($tables as $table) {
                $tableName = reset($table);
                $columns = \DB::select('DESCRIBE ' . $tableName);
                $schema[$tableName] = [
                    'columns' => array_map(function ($column) {
                        return [
                            'name' => $column->Field,
                            'type' => $column->Type,
                            'nullable' => $column->Null === 'YES',
                            'primary' => $column->Key === 'PRI',
                            'unique' => $column->Key === 'UNI',
                        ];
                    }, $columns),
                    'relationships' => []
                ];
            }
        } catch (\Exception $e) {
            $this->error('Failed to get database schema: ' . $e->getMessage());
        }
        return $schema;
    }

    /** Gather routes */
    protected function gatherRoutes()
    {
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            if (!in_array('web', $middleware) && !in_array('api', $middleware)) {
                continue;
            }
            $routes[] = [
                'uri' => $route->uri(),
                'methods' => $route->methods(),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'middleware' => $middleware,
            ];
        }
        return $routes;
    }

    /** Gather controllers */
    protected function gatherControllers()
    {
        $controllers = [];
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (strpos($action, '@') === false) continue;

            [$class, $method] = explode('@', $action);

            if (!str_starts_with($class, 'App\Http\Controllers')) continue;
            if (!class_exists($class)) continue;

            if (!isset($controllers[$class])) {
                $controllers[$class] = ['methods' => [], 'routes' => []];

                try {
                    $reflection = new ReflectionClass($class);
                    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
                    foreach ($methods as $refMethod) {
                        if ($refMethod->class === $class && !$refMethod->isConstructor()) {
                            $controllers[$class]['methods'][] = [
                                'name' => $refMethod->getName(),
                                'visibility' => 'public',
                            ];
                        }
                    }
                } catch (\ReflectionException $e) {
                }
            }

            $controllers[$class]['routes'][] = [
                'uri' => $route->uri(),
                'methods' => $route->methods(),
                'name' => $route->getName(),
                'middleware' => $route->gatherMiddleware(),
            ];
        }
        return $controllers;
    }

    /** Gather models */
    protected function gatherModels()
    {
        $models = [];
        $modelDir = app_path('Models');
        if (!File::isDirectory($modelDir)) $modelDir = app_path();

        foreach (File::allFiles($modelDir) as $file) {
            if ($file->getExtension() !== 'php') continue;

            $contents = File::get($file->getRealPath());
            $namespace = 'App';
            if (preg_match('/namespace\s+([^;]+);/m', $contents, $mns)) {
                $namespace = trim($mns[1]);
            }
            if (!preg_match('/class\s+(\w+)/m', $contents, $mc)) continue;

            $class = $namespace . '\\' . $mc[1];
            if (!class_exists($class)) continue;

            try {
                $ref = new ReflectionClass($class);
                if (!$ref->isSubclassOf('Illuminate\\Database\\Eloquent\\Model')) continue;

                $instance = $ref->newInstance();
                $models[$class] = [
                    'table' => $instance->getTable(),
                    'fillable' => $instance->getFillable(),
                    'casts' => $instance->getCasts(),
                    'relationships' => $this->detectRelationships($ref, $instance),
                ];
            } catch (\Throwable $e) {
                continue;
            }
        }
        return $models;
    }

    /** Gather migrations */
    protected function gatherMigrations()
    {
        $migrations = [];
        $dir = database_path('migrations');
        if (File::isDirectory($dir)) {
            foreach (File::files($dir) as $file) {
                if ($file->getExtension() !== 'php') continue;
                $migrations[] = [
                    'migration' => $file->getFilename(),
                    'batch' => 'N/A',
                    'table' => $this->extractTableNameFromMigration($file->getFilename()),
                ];
            }
        }
        return $migrations;
    }

    protected function extractTableNameFromMigration($migrationName)
    {
        if (preg_match('/create_(\w+)_table/', $migrationName, $matches)) return $matches[1];
        if (preg_match('/add_.+_to_(\w+)_table/', $migrationName, $matches)) return $matches[1];
        if (preg_match('/update_(\w+)_table/', $migrationName, $matches)) return $matches[1];
        return '-';
    }

    /** Gather packages */
    protected function gatherPackages()
    {
        $composerFile = base_path('composer.json');
        if (!File::exists($composerFile)) return [];
        $composer = json_decode(File::get($composerFile), true);
        return array_keys($composer['require'] ?? []);
    }

    /** Gather stats */
    protected function gatherStats($data)
    {
        $webRoutesCount = $apiRoutesCount = 0;
        foreach ($data['routes'] as $route) {
            $middleware = $route['middleware'] ?? [];
            if (in_array('web', $middleware)) $webRoutesCount++;
            if (in_array('api', $middleware)) $apiRoutesCount++;
        }

        $customControllersCount = count($data['controllers']);
        $modelsWithRelationshipsCount = 0;
        foreach ($data['models'] as $model) {
            if (!empty($model['relationships'])) $modelsWithRelationshipsCount++;
        }

        $paths = [
            app_path('Http'),
            app_path('Models'),
            base_path('routes'),
            database_path('factories'),
            database_path('migrations'),
            database_path('seeders'),
            resource_path(),
            public_path('css'),
            public_path('js'),
            base_path('tests'),
        ];

        $loc = 0;
        foreach ($paths as $path) {
            if (!File::isDirectory($path)) continue;
            foreach (File::allFiles($path) as $file) {
                $ext = $file->getExtension();
                if (in_array($ext, ['php', 'js', 'vue', 'css'])) {
                    $lines = file($file->getRealPath());
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '' || str_starts_with($line, '//') || str_starts_with($line, '#')) continue;
                        if (preg_match('/^\/\*.*\*\/$/', $line)) continue;
                        $loc++;
                    }
                }
            }
        }

        $connection = config('database.default');
        $dbConfig = config("database.connections.$connection");

        return [
            'routes_count' => count($data['routes']),
            'web_routes_count' => $webRoutesCount,
            'api_routes_count' => $apiRoutesCount,
            'controllers_count' => $customControllersCount,
            'custom_controllers_count' => $customControllersCount,
            'models_count' => count($data['models']),
            'models_with_relationships_count' => $modelsWithRelationshipsCount,
            'migrations_count' => count($data['migrations']),
            'packages_count' => count($data['packages']),
            'lines_of_code' => $loc,
            'database' => [
                'driver' => $dbConfig['driver'] ?? 'unknown',
                'database' => $dbConfig['database'] ?? 'N/A',
            ],
            'language' => 'PHP ' . PHP_VERSION . ' (Laravel ' . app()->version() . ')',
        ];
    }

    /** Detect relationships */
    protected function detectRelationships(ReflectionClass $ref, $instance)
    {
        $rels = [];
        foreach ($ref->getMethods() as $method) {
            if ($method->isStatic() || $method->getNumberOfParameters() > 0) continue;
            try {
                $result = $method->invoke($instance);
                if (is_object($result) && is_subclass_of(get_class($result), 'Illuminate\\Database\\Eloquent\\Relations\\Relation')) {
                    $rels[] = [
                        'method' => $method->getName(),
                        'type' => class_basename(get_class($result)),
                        'related' => method_exists($result, 'getRelated') ? get_class($result->getRelated()) : null,
                        'foreign_key' => method_exists($result, 'getForeignKeyName') ? $result->getForeignKeyName() : null,
                        'local_key' => method_exists($result, 'getLocalKeyName') ? $result->getLocalKeyName() : null,
                    ];
                }
            } catch (\Throwable $e) {
            }
        }
        return $rels;
    }

    /** Gather configs */
    protected function gatherConfigs()
    {
        return [
            'cache' => config('cache.default'),
            'queue' => config('queue.default'),
            'mail' => config('mail.default') ?? config('mail.mailers') ?? null,
            'app_env' => config('app.env'),
        ];
    }

    /** Gather policies */
    protected function gatherPolicies()
    {
        $policies = [];
        $authProv = app()->getProvider(\App\Providers\AuthServiceProvider::class) ?? null;
        if ($authProv) {
            try {
                $ref = new ReflectionClass($authProv);
                if ($ref->hasProperty('policies')) {
                    $prop = $ref->getProperty('policies');
                    $prop->setAccessible(true);
                    $policies = $prop->getValue($authProv) ?: [];
                }
            } catch (\Throwable $e) {
            }
        }
        return $policies;
    }

    /** Generate PlantUML source */
   /** Generate PlantUML source */
protected function generatePlantUml($models)
{
    $lines = [
        "@startuml",
        "!theme plain",
        "hide circle",
        "hide methods",
        "skinparam classAttributeIconSize 0",
        "left to right direction",
        "skinparam class {",
        "  BackgroundColor<<Entity>> White",
        "  BorderColor Black",
        "  FontColor Black",
        "  FontStyle bold",
        "  AttributeFontColor #444444",
        "  AttributeFontSize 11",
        "  BorderRoundCorner 8",
        "  HeaderBackgroundColor LightGray",
        "  HeaderFontColor Black",
        "  HeaderFontStyle bold",
        "}"
    ];

    // Generate entity classes
    foreach ($models as $class => $info) {
        $lines[] = "class " . class_basename($class) . " <<Entity>> {";
        foreach ($info['fillable'] as $field) {
            $lines[] = "  +$field";
        }
        $lines[] = "}";
    }

    // Generate relationships
    foreach ($models as $class => $info) {
        foreach ($info['relationships'] as $rel) {
            if (!empty($rel['related'])) {
                $from = class_basename($class);
                $to   = class_basename($rel['related']);
                $type = $rel['type'];

                // Add relationship with proper 1..* notation
                switch ($type) {
                    case 'HasMany':
                    case 'MorphMany':
                        $lines[] = "$from \"1\" --> \"*\" $to : {$rel['method']} ($type)";
                        break;
                    case 'BelongsTo':
                    case 'MorphTo':
                        $lines[] = "$from \"*\" --> \"1\" $to : {$rel['method']} ($type)";
                        break;
                    case 'BelongsToMany':
                        $lines[] = "$from \"*\" -- \"*\" $to : {$rel['method']} ($type)";
                        break;
                    case 'HasOne':
                        $lines[] = "$from \"1\" --> \"1\" $to : {$rel['method']} ($type)";
                        break;
                    default:
                        $lines[] = "$from --> $to : {$rel['method']} ($type)";
                }
            }
        }
    }

    $lines[] = "@enduml";
    return implode("\n", $lines);
}

    /** Generate PNG using PlantUML jar */
    protected function generatePlantUmlPng($out)
    {
        $plantumlJar = __DIR__ . '/../../resources/bin/plantuml.jar';
        if (!File::exists($plantumlJar)) {
            $this->error('PlantUML JAR not found. Please place it at resources/bin/plantuml.jar');
            return;
        }

        $pumlFile = $out . '/erd.puml';

        // Check for bundled JRE first
        $jreJava = __DIR__ . '/../../resources/bin/jre/bin/java' . (strncasecmp(PHP_OS, 'WIN', 3) === 0 ? '.exe' : '');
        if (File::exists($jreJava)) {
            $javaCmd = escapeshellarg($jreJava);
        } else {
            // fallback to system java
            $javaCmd = 'java';
        }

        $cmd = $javaCmd . ' -jar ' . escapeshellarg($plantumlJar) . ' -tpng ' . escapeshellarg($pumlFile);
        exec($cmd, $output, $return);

        if ($return === 0 && File::exists($out . '/erd.png')) {
            $this->info('ERD PNG generated: ' . realpath($out . '/erd.png'));
        } else {
            $this->error('Failed to generate ERD PNG using PlantUML (command: ' . $cmd . ')');
        }
    }

    /** Take screenshots */
    protected function takeScreenshots($out, $config)
    {
        if (class_exists('Spatie\\Browsershot\\Browsershot')) {
            foreach ($config['screenshot_urls'] ?? [] as $url => $label) {
                try {
                    $file = $out . '/screenshot-' . preg_replace('/[^a-z0-9]+/', '-', trim($label ?? $url, '/')) . '.png';
                    \Spatie\Browsershot\Browsershot::url($url)->save($file);
                    $this->info('Saved screenshot: ' . $file);
                } catch (\Throwable $e) {
                    $this->error('Screenshot failed for ' . $url . ': ' . $e->getMessage());
                }
            }
        } else {
            $this->warn('No headless browser tool detected. Install Spatie Browsershot to enable screenshots.');
        }
    }
}

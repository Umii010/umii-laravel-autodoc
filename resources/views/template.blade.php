<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <title>Automated Report Generated for Laravel Applications</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #000;
        }
        h1, h2, h3, h4, h5 {
            margin: 10px 0;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #444;
        }
        th, td {
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f0f0f0;
        }
        .section-title {
            background: #0a4;
            color: #fff;
            padding: 6px;
            font-size: 16px;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            font-size: 10px;
            border: 1px solid #ccc;
            margin-right: 2px;
            background: #eee;
        }
        pre {
            white-space: pre-wrap;
            font-size: 11px;
            background: #f8f8f8;
            padding: 6px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>

<h1>Laravel Application Documentation</h1>
<p>Generated on: {{ date('F j, Y H:i:s') }}</p>
<p>Laravel Version: {{ $laravel_version }}</p>
<p>PHP Version: {{ phpversion() }} | Environment: {{ app()->environment() }} | URL: {{ config('app.url') }} | Timezone: {{ config('app.timezone') }}</p>

<!-- SUMMARY -->
<h2 class="section-title">Project Summary</h2>
<table>
    <tr>
        <td><b>Routes</b><br>{{ $stats['routes_count'] }}<br><small>Web: {{ $stats['web_routes_count'] }}, API: {{ $stats['api_routes_count'] }}</small></td>
        <td><b>Controllers</b><br>{{ $stats['controllers_count'] }}<br><small>Custom: {{ $stats['custom_controllers_count'] }}</small></td>
        <td><b>Models</b><br>{{ $stats['models_count'] }}<br><small>With Relationships: {{ $stats['models_with_relationships_count'] }}</small></td>
        <td><b>Migrations</b><br>{{ $stats['migrations_count'] }}</td>
    </tr>
    <tr>
        <td><b>Packages</b><br>{{ $stats['packages_count'] }}</td>
        <td><b>Lines of Code</b><br>{{ number_format($stats['lines_of_code']) }}</td>
        <td><b>Database used</b><br>{{ $stats['database']['driver'] }}<br><small>{{ $stats['database']['database'] }}</small></td>
        <td><b>Language</b><br>{{ $stats['language'] }}</td>
    </tr>
</table>

<!-- ROUTES -->
<h2 class="section-title">Routes ({{ $stats['routes_count'] }})</h2>
<table>
    <tr>
        <th>Methods</th>
        <th>URI</th>
        <th>Name</th>
        <th>Action</th>
        <th>Middleware</th>
    </tr>
    @foreach($routes as $r)
        <tr>
            <td>
                @foreach($r['methods'] as $method)
                    <span class="badge">{{ $method }}</span>
                @endforeach
            </td>
            <td><code>{{ $r['uri'] }}</code></td>
            <td>{{ $r['name'] ?? '-' }}</td>
            <td><pre>{{ $r['action'] }}</pre></td>
            <td>
                @if(!empty($r['middleware']))
                    @foreach($r['middleware'] as $middleware)
                        <span class="badge">{{ $middleware }}</span>
                    @endforeach
                @else - @endif
            </td>
        </tr>
    @endforeach
</table>

<!-- CONTROLLERS -->
<h2 class="section-title">Controllers ({{ $stats['controllers_count'] }})</h2>
<table>
    <tr>
        <th>Controller</th>
        <th>Methods</th>
    </tr>
    @foreach($controllers as $controller => $c)
        <tr>
            <td>{{ class_basename($controller) }}</td>
            <td>
                <table>
                    <tr><th>Name</th><th>Visibility</th></tr>
                    @foreach($c['methods'] as $method)
                        <tr>
                            <td><code>{{ $method['name'] }}</code></td>
                            <td>{{ $method['visibility'] }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    @endforeach
</table>

<!-- MODELS -->
<h2 class="section-title">Models ({{ $stats['models_count'] }})</h2>
@foreach($models as $class => $m)
    <h3>{{ class_basename($class) }}</h3>
    <p><b>Table:</b> <code>{{ $m['table'] }}</code> | <b>Namespace:</b> {{ $class }}</p>

    <h4>Fillable Attributes</h4>
    @if(!empty($m['fillable']))
        @foreach($m['fillable'] as $field)
            <span class="badge">{{ $field }}</span>
        @endforeach
    @else <p>No fillable attributes</p>
    @endif

    <h4>Primary Key</h4>
    <p><code>{{ $m['primary_key'] ?? 'id' }}</code></p>

    <h4>Casts</h4>
    @if(!empty($m['casts']))
        <table>
            <tr><th>Attribute</th><th>Type</th></tr>
            @foreach($m['casts'] as $attr => $type)
                <tr><td><code>{{ $attr }}</code></td><td>{{ $type }}</td></tr>
            @endforeach
        </table>
    @else <p>No casts</p>
    @endif

    <h4>Relationships</h4>
    @if(!empty($m['relationships']))
        <table>
            <tr>
                <th>Relationship</th>
                <th>Defined in</th>
                <th>Related Model</th>
                <th>Cardinality</th>
                <th>Keys</th>
            </tr>
            @foreach($m['relationships'] as $rel)
                <tr>
                    <td>{{ $rel['type'] }} ({{ $rel['method'] }})</td>
                    <td>{{ class_basename($class) }}</td>
                    <td>{{ class_basename($rel['related']) }}</td>
                    <td>
                        @switch($rel['type'])
                            @case('HasOne') 1 → 1 @break
                            @case('HasMany') 1 → * @break
                            @case('BelongsTo') * → 1 @break
                            @case('BelongsToMany') * ↔ * @break
                            @case('MorphMany') 1 → * (Polymorphic) @break
                            @case('MorphTo') * → 1 (Polymorphic) @break
                            @default ?
                        @endswitch
                    </td>
                    <td>
                        {{ $rel['local_key'] ?? '-' }} → {{ $rel['foreign_key'] ?? '-' }}
                    </td>
                </tr>
                <tr>
                    <td colspan="5" style="font-size:11px; color:#555;">
                        {{ class_basename($class) }} 
                        @switch($rel['type'])
                            @case('HasMany')
                                can have many {{ class_basename($rel['related']) }}.
                                @break
                            @case('BelongsTo')
                                belongs to one {{ class_basename($rel['related']) }}.
                                @break
                            @case('BelongsToMany')
                                has many {{ class_basename($rel['related']) }} (many-to-many).
                                @break
                            @case('HasOne')
                                has exactly one {{ class_basename($rel['related']) }}.
                                @break
                            @case('MorphMany')
                                can have many {{ class_basename($rel['related']) }} (polymorphic).
                                @break
                            @case('MorphTo')
                                belongs to one parent (polymorphic).
                                @break
                            @default
                                Relationship details unknown.
                        @endswitch
                    </td>
                </tr>
            @endforeach
        </table>
    @else <p>No relationships</p>
    @endif
@endforeach

<!-- MIGRATIONS -->
<h2 class="section-title">Migrations ({{ $stats['migrations_count'] }})</h2>
<table>
    <tr><th>Migration</th><th>Table</th></tr>
    @foreach($migrations as $migration)
        <tr>
            <td><code>{{ $migration['migration'] }}</code></td>
            <td>{{ $migration['table'] ?? '-' }}</td>
        </tr>
    @endforeach
</table>

<!-- CONFIG -->
<h2 class="section-title">Configuration</h2>
@foreach($configs as $file => $config)
    <h4>{{ $file }}</h4>
    <pre>{{ json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
@endforeach

<!-- POLICIES -->
<h2 class="section-title">Policies</h2>
@foreach($policies as $model => $policy)
    <h4>{{ $model }} Policy</h4>
    <pre>{{ json_encode($policy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
@endforeach

<!-- ERD -->
<h2 class="section-title">Entity Relationship Diagram (ERD)</h2>
<p>A PNG file has been exported to <code>docs/erd.png</code></p>
<h4>Database Schema Preview</h4>
<table>
    <tr><th>Table</th><th>Columns</th></tr>
    @foreach($database_schema as $table => $schema)
        <tr>
            <td>{{ $table }}</td>
            <td>
                @foreach($schema['columns'] as $col)
                    <div><code>{{ $col['name'] }}</code> ({{ $col['type'] }})</div>
                @endforeach
            </td>
           
        </tr>
    @endforeach
</table>

<p style="text-align:center; margin-top:30px; font-size:11px;">
    Generated by Umii Automatic Documentation Generator<br>
    Documentation generated on {{ date('F j, Y \a\t H:i:s') }}
</p>

</body>
</html>

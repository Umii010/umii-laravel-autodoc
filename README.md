# Umii AutoDoc (umii/umii-autodoc)

Auto documentation generator for Laravel applications.

Features:
- Routes table (methods, URIs, controllers)
- API examples (GET/POST simple samples)
- Model list with fillable & basic relationship detection
- Middleware & policies overview (best-effort)
- Config overview (cache, queue, mail)
- ERD DOT export (docs/erd.dot)
- Screenshots: optional stub (requires a headless browser tool; the package will detect availability)

## Install (local development)

1. Place this package folder into your Laravel project's `packages/umii/umii-autodoc` directory or require via composer.
2. Add `"Umii\\AutoDoc\\UmiiAutoDocServiceProvider"` to `config/app.php` providers (if not using package discovery).
3. Run:
   ```
   php artisan vendor:publish --provider="Umii\AutoDoc\UmiiAutoDocServiceProvider" --tag=config
   php artisan autodoc:generate
   ```
4. Output will be generated in `docs/index.html` (and `docs/erd.dot`).

## Notes
- This is a starter implementation intended to be run inside a Laravel app. It uses Laravel's route and config systems.
- ERD generation produces a DOT file. Convert to SVG with Graphviz: `dot -Tsvg docs/erd.dot -o docs/erd.svg`
- Screenshots require an external tool (e.g., Browsershot / Puppeteer / Playwright). The package checks if `Spatie\Browsershot\Browsershot` exists.

## Author
Umii — umii020@hotmail.com
University: SZABIST Islamabad
BSCS 


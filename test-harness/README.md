# Test-Harness (verschoben aus dem LocalBase-Repository)

Dieses Verzeichnis enthält das gemeinsame Test-Harness, das zuvor im
Repository `localbase` unter `tests/` lag. LocalBase ist laufzeitfrei und
wurde als App-Hülle zurückgebaut; das Harness bleibt hier im Parent-Workspace
(Parent-Regel: Delivery-Skripte und zugehörige Tests liegen im Parent).

Der PHP-Namespace `OCA\LocalBase\Tests\...` bleibt vorerst unverändert, damit
bestehende App-Bootstraps nur den Pfad anpassen müssen. App-Bootstraps
auflösen den Harness-Pfad über:

```php
$harnessDir = is_dir($workspaceRoot . '/test-harness')
    ? $workspaceRoot . '/test-harness'
    : $workspaceRoot . '/parent/test-harness';
```

Lokal liegt das Harness im Parent-Root (`<workspace>/test-harness`), in der
App-CI wird der Parent-Repo-Wurzel in den Workspace ausgecheckt
(`path: '.', clean: false`), sodass derselbe Pfad greift.

Inhalt: `Support/PhpTestRunner.php`, `Support/assertions.php`,
`Support/js-runner.mjs`, `js/helpers/fake-dom.js`, `coverage/` (c8-Tooling),
sowie `bootstrap.php`, `helpers.php`, `run.php`, `run-js.mjs` als
Harness-Einstiegspunkte.

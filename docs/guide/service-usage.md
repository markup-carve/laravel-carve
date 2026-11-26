# Service usage

Inject CarveConverterInterface or CarveManager. The interface renders HTML, parses a Carve Node, and exposes output methods. CarveManager selects named profiles and provides lint, format, HTML import, and file rendering.

    $result = $manager->render($source, 'default');
    $html = $result->html;
    $tree = $manager->parse($source);
    $findings = $manager->lint($source);

To customize a profile, call CarveManager::extend with a callback that receives the Laravel CarveConverter adapter. Use setOption or registerWriter on it. The old mutable 0.1 engine converter is no longer exposed. See the [migration guide](migration.md).

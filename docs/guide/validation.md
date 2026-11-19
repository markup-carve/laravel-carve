# Validation

ValidCarve uses the Carve profile and diagnostic APIs. preset accepts full, article, comment, or minimal. lint runs the Carve rule catalog. strict treats lint findings as validation errors. maxLength counts characters for the Laravel field; Carve also enforces a profile byte limit during rendering.

    'body' => [ValidCarve::preset('comment')->lint()->maxLength(5000)]

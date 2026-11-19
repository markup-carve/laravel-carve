## Laravel Carve

This package renders [Carve](https://markup-carve.github.io/carve/) markup in Laravel: Blade directives, a `Carve` facade, the `AsCarve` cast, the `ValidCarve` rule, `<x-carve>`, `.crv` views and `carve:*` Artisan commands.

- Carve is not Markdown. Bold is `*text*`, italic is `/text/`, underline is `{_text_}`, strikethrough is `{~text~}`. Never write Markdown `**bold**` in Carve source; run `Carve::lint()` or `php artisan carve:lint` to catch it.
- Lists need a blank line before the first item. Block attributes such as `{#id}` go on their own line *above* the block.
- Render user content with a safe converter profile (`default` or `comment`). Use the trusted raw HTML helper or profile only for trusted authors. URL policy still denies unsafe schemes.
- Validate Carve input with `ValidCarve` before saving it, using the same profile as the converter that renders it.
- Converter profiles live in `config/carve.php` under `converters`; pass the profile name as the last argument instead of building converters by hand.
- Use the `carve-development` skill and the 0.2 migration guide for the current API and syntax.

@verbatim
<code-snippet name="Rendering Carve" lang="blade">
@carve($post->body)
@carve($comment->body, 'comment')
<x-carve :source="$post->body" profile="comment" />
</code-snippet>

<code-snippet name="Storing and validating Carve" lang="php">
use MarkupCarve\LaravelCarve\Casts\AsCarve;
use MarkupCarve\LaravelCarve\Rules\ValidCarve;

protected $casts = ['body' => AsCarve::class.':comment'];

$request->validate([
    'body' => ['required', 'string', ValidCarve::preset('comment')->maxLength(5000)],
]);
</code-snippet>
@endverbatim

# Service Usage

## Basic Injection

Inject the converter interface:

```php
use MarkupCarve\LaravelCarve\Service\CarveConverterInterface;

class ArticleController extends Controller
{
    public function __construct(
        private CarveConverterInterface $carve,
    ) {}

    public function show(Article $article): View
    {
        $html = $this->carve->toHtml($article->body);
        $plainText = $this->carve->toText($article->body);

        return view('article.show', [
            'article' => $article,
            'bodyHtml' => $html,
            'bodyText' => $plainText,
        ]);
    }
}
```

## Available Methods

### `toHtml(string $carve): string`

Converts Carve markup to HTML.

```php
$html = $this->carve->toHtml('*Hello* _world_!');
// <p><strong>Hello</strong> <em>world</em>!</p>
```

### `toText(string $carve): string`

Converts Carve markup to plain text.

```php
$text = $this->carve->toText('*Hello* _world_!');
// Hello world!
```

### `parse(string $carve): Document`

Parses Carve markup into an AST (Abstract Syntax Tree). Useful for advanced manipulation.

```php
use MarkupCarve\Carve\Node\Document;

$document = $this->carve->parse('# Heading');
```

## Using Multiple Profiles

Inject the `CarveManager` to access all registered profiles:

```php
use MarkupCarve\LaravelCarve\Service\CarveManager;

class CommentService
{
    public function __construct(
        private CarveManager $carve,
    ) {}

    public function renderUserComment(string $text): string
    {
        return $this->carve->toHtml($text); // default profile (safe mode)
    }

    public function renderAdminContent(string $text): string
    {
        return $this->carve->toHtml($text, 'trusted');
    }

    public function renderTrustedInline(string $text): string
    {
        return $this->carve->toHtmlRaw($text);
    }
}
```

You can also grab a specific converter directly:

```php
$docsConverter = $this->carve->converter('docs');
$html = $docsConverter->toHtml($content);
```

## Use Cases

### Notifications / Mail

```php
use MarkupCarve\LaravelCarve\Service\CarveConverterInterface;

class NewsletterMail extends Mailable
{
    public function __construct(private string $carveBody)
    {
    }

    public function build(CarveConverterInterface $carve): self
    {
        return $this->html($carve->toHtml($this->carveBody))
            ->text($carve->toText($this->carveBody));
    }
}
```

### Search Indexing

```php
class SearchIndexer
{
    public function __construct(
        private CarveConverterInterface $carve,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexArticle(Article $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'content' => $this->carve->toText($article->body),
            'html' => $this->carve->toHtml($article->body),
        ];
    }
}
```

### API Response

```php
public function show(Article $article): JsonResponse
{
    return response()->json([
        'id' => $article->id,
        'title' => $article->title,
        'body_raw' => $article->body,
        'body_html' => $this->carve->toHtml($article->body),
        'body_text' => $this->carve->toText($article->body),
    ]);
}
```

## Next Steps

- [Configuration](configuration.md) — set up multiple profiles
- [Safe Mode](safe-mode.md) — protect against XSS

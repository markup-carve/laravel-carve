<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Exception\ParseException;

class ValidCarve implements ValidationRule
{
    private CarveConverter $converter;

    /**
     * @param bool $strict If true, parse warnings are also treated as errors
     * @param string|null $message Custom error message template ({error} placeholder is replaced)
     */
    public function __construct(
        private bool $strict = false,
        private ?string $message = null,
    ) {
        $this->converter = new CarveConverter(
            warnings: true,
            strict: false,
        );
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            $fail($this->formatMessage('value must be a string'));

            return;
        }

        try {
            $this->converter->convert($value);

            if ($this->strict && $this->converter->hasWarnings()) {
                $warnings = $this->converter->getWarnings();
                $firstWarning = $warnings[0] ?? null;
                $errorMessage = $firstWarning?->getMessage() ?? 'Parse warnings detected';
                $fail($this->formatMessage($errorMessage));
            }
        } catch (ParseException $e) {
            $fail($this->formatMessage($e->getMessage()));
        }
    }

    private function formatMessage(string $error): string
    {
        $template = $this->message ?? 'The :attribute is not valid Carve markup: {error}';

        return str_replace('{error}', $error, $template);
    }
}

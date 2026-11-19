<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;
use MarkupCarve\Carve\Carve as Engine;
use MarkupCarve\Carve\CarveException;
use MarkupCarve\LaravelCarve\Facades\Carve;
use MarkupCarve\LaravelCarve\Service\RenderPolicy;

class ValidCarve implements ValidationRule
{
    private ?string $preset = null;

    private bool $lint = false;

    private ?int $maxLength = null;

    /**
     * @param bool $strict If true, parse warnings are also treated as errors
     * @param string|null $message Custom error message template ({error} placeholder is replaced)
     */
    public function __construct(
        private bool $strict = false,
        private ?string $message = null,
    ) {
    }

    public static function preset(string $name): self
    {
        RenderPolicy::preset($name);
        $rule = new self();
        $rule->preset = $name;

        return $rule;
    }

    public function strict(bool $strict = true): self
    {
        $this->strict = $strict;

        return $this;
    }

    public function lint(bool $lint = true): self
    {
        $this->lint = $lint;

        return $this;
    }

    public function maxLength(int $characters): self
    {
        if ($characters < 0) {
            throw new InvalidArgumentException('Carve maximum length must not be negative.');
        }
        $this->maxLength = $characters;

        return $this;
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

        if ($this->maxLength !== null && mb_strlen($value) > $this->maxLength) {
            $fail($this->formatMessage(sprintf('must not exceed %d characters', $this->maxLength)));

            return;
        }
        try {
            $options = $this->preset === null ? [] : ['profile' => $this->preset];
            $result = Engine::renderWithDiagnostics($value, $options);
            foreach ($result->diagnostics as $diagnostic) {
                if ($diagnostic->channel === 'render-loss') {
                    $fail($this->formatMessage($diagnostic->message));

                    return;
                }
            }
            $findings = ($this->lint || $this->strict) ? Carve::lint($value) : [];
            if ($findings !== []) {
                $fail($this->formatMessage($findings[0]->message));
            }
        } catch (CarveException $e) {
            $fail($this->formatMessage($e->getMessage()));
        }
    }

    private function formatMessage(string $error): string
    {
        $template = $this->message ?? 'The :attribute is not valid Carve markup: {error}';

        return str_replace('{error}', $error, $template);
    }
}

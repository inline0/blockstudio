<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Theme\Diagnostic;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

final class DiagnosticRuleErrorFactory
{
    public static function build(Diagnostic $diagnostic): IdentifierRuleError
    {
        $builder = RuleErrorBuilder::message($diagnostic->message)
            ->identifier($diagnostic->identifier)
            ->line(max(1, $diagnostic->line));

        if (is_file($diagnostic->file)) {
            $builder = $builder->file($diagnostic->file);
        }

        return $builder->build();
    }
}

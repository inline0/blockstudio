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
        return RuleErrorBuilder::message($diagnostic->message)
            ->identifier($diagnostic->identifier)
            ->file($diagnostic->file)
            ->line(max(1, $diagnostic->line))
            ->build();
    }
}

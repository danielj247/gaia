<?php

declare(strict_types=1);

use App\Enums\GraphNodeLabel;
use App\Enums\IdentifierKind;

it('maps remaining follow-the-money schemas and identifier fields', function (): void {
    expect(GraphNodeLabel::fromFollowTheMoney('BankAccount'))->toBe(GraphNodeLabel::Organization)
        ->and(GraphNodeLabel::fromFollowTheMoney('Vessel'))->toBe(GraphNodeLabel::Vessel)
        ->and(GraphNodeLabel::fromFollowTheMoney('CryptoWallet'))->toBe(GraphNodeLabel::CryptoWallet)
        ->and(GraphNodeLabel::fromFollowTheMoney('Security'))->toBe(GraphNodeLabel::Security)
        ->and(GraphNodeLabel::fromFollowTheMoney('Address'))->toBe(GraphNodeLabel::Address)
        ->and(GraphNodeLabel::fromFollowTheMoney('Sanction'))->toBe(GraphNodeLabel::Sanction)
        ->and(GraphNodeLabel::Dump->isEntity())->toBeFalse()
        ->and(GraphNodeLabel::Address->isEntity())->toBeFalse()
        ->and(GraphNodeLabel::Country->isEntity())->toBeFalse()
        ->and(GraphNodeLabel::Person->isSearchable())->toBeTrue()
        ->and(GraphNodeLabel::Dump->isSearchable())->toBeFalse()
        ->and(GraphNodeLabel::Country->isSearchable())->toBeFalse()
        ->and(GraphNodeLabel::Sanction->isSearchable())->toBeFalse()
        ->and(GraphNodeLabel::Dump->isInspectOnlyHub())->toBeTrue()
        ->and(GraphNodeLabel::Country->isInspectOnlyHub())->toBeTrue()
        ->and(GraphNodeLabel::Sanction->isInspectOnlyHub())->toBeTrue()
        ->and(GraphNodeLabel::Person->isInspectOnlyHub())->toBeFalse()
        ->and(GraphNodeLabel::fromFollowTheMoney('Person'))->toBe(GraphNodeLabel::Person)
        ->and(GraphNodeLabel::fromFollowTheMoney('Organization'))->toBe(GraphNodeLabel::Organization)
        ->and(GraphNodeLabel::fromFollowTheMoney('LegalEntity'))->toBe(GraphNodeLabel::Organization)
        ->and(GraphNodeLabel::fromFollowTheMoney('PublicBody'))->toBe(GraphNodeLabel::Organization)
        ->and(IdentifierKind::followTheMoneyProperties(IdentifierKind::Email))->toBe(['email'])
        ->and(IdentifierKind::followTheMoneyProperties(IdentifierKind::Other))->toBe([]);
});

it('defines follow-the-money fields for every identifier kind', function (): void {
    foreach (IdentifierKind::cases() as $kind) {
        expect(IdentifierKind::followTheMoneyProperties($kind))->toBeArray();
    }
});

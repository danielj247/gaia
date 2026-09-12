<?php

declare(strict_types=1);

namespace App\Enums;

enum IdentifierKind: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Passport = 'passport';
    case NationalId = 'national_id';
    case TaxNumber = 'tax_number';
    case RegistrationNumber = 'registration_number';
    case Imo = 'imo';
    case Lei = 'lei';
    case SwiftBic = 'swift_bic';
    case Wikidata = 'wikidata';
    case CryptoWallet = 'crypto_wallet';
    case Website = 'website';
    case OfacId = 'ofac_id';
    case OpenSanctionsId = 'opensanctions_id';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function followTheMoneyProperties(self $kind): array
    {
        return match ($kind) {
            self::Email => ['email'],
            self::Phone => ['phone'],
            self::Passport => ['passportNumber'],
            self::NationalId => ['idNumber'],
            self::TaxNumber => ['innCode', 'taxNumber', 'vatCode'],
            self::RegistrationNumber => ['registrationNumber', 'ogrnCode', 'okpoCode', 'dunsCode'],
            self::Imo => ['imoNumber'],
            self::Lei => ['leiCode'],
            self::SwiftBic => ['swiftBic'],
            self::Wikidata => ['wikidataId'],
            self::CryptoWallet => ['currencyCode'],
            self::Website => ['website'],
            self::OfacId, self::OpenSanctionsId, self::Other => [],
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

enum GraphNodeLabel: string
{
    case Person = 'Person';
    case Organization = 'Organization';
    case Identifier = 'Identifier';
    case Address = 'Address';
    case Country = 'Country';
    case Sanction = 'Sanction';
    case Vessel = 'Vessel';
    case Aircraft = 'Aircraft';
    case CryptoWallet = 'CryptoWallet';
    case Security = 'Security';
    case Dump = 'Dump';
    case Other = 'Other';

    public static function fromFollowTheMoney(string $schema): self
    {
        return match ($schema) {
            'Person' => self::Person,
            'Organization', 'Company', 'LegalEntity', 'PublicBody', 'BankAccount' => self::Organization,
            'Address' => self::Address,
            'Sanction' => self::Sanction,
            'Vessel' => self::Vessel,
            'Airplane' => self::Aircraft,
            'CryptoWallet' => self::CryptoWallet,
            'Security' => self::Security,
            default => self::Other,
        };
    }

    public function isEntity(): bool
    {
        return $this !== self::Identifier
            && $this !== self::Address
            && $this !== self::Country
            && $this !== self::Dump;
    }
}

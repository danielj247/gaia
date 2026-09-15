<?php

declare(strict_types=1);

namespace App\Enums;

enum GraphEdgeType: string
{
    case HasIdentifier = 'HAS_IDENTIFIER';
    case AlsoKnownAs = 'ALSO_KNOWN_AS';
    case LocatedAt = 'LOCATED_AT';
    case CitizenOf = 'CITIZEN_OF';
    case SanctionedUnder = 'SANCTIONED_UNDER';
    case MemberOf = 'MEMBER_OF';
    case Owns = 'OWNS';
    case RelatedTo = 'RELATED_TO';
    case AppearsInDump = 'APPEARS_IN_DUMP';
    case Controls = 'CONTROLS';
    case FamilyOf = 'FAMILY_OF';
    case SameAs = 'SAME_AS';
}

<?php

namespace App\GraphQL\Errors;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;
use RuntimeException;

/**
 * A membership that does not fit the account (Auftrag 58 A): an agent account is in every project only in the project
 * role Agent, a person never. extensions.code says which; the message is the sentence in the language of the request.
 * Nothing was changed.
 */
final class MembershipRefusal extends RuntimeException implements ClientAware, ProvidesExtensions
{
    /** An agent account with another project role than Agent (also: as the creator of a project). */
    public const AGENT_ROLE_ONLY = 'AGENT_ROLE_ONLY';

    /** A person (not an agent account) with the project role Agent. */
    public const AGENT_ROLE_RESERVED = 'AGENT_ROLE_RESERVED';

    public function __construct(public readonly string $reason, public readonly string $name = '')
    {
        parent::__construct(__('projects.membership_refusal.'.$reason, ['name' => $name]));
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return 'membership';
    }

    /**
     * @return array<string, string|bool>
     */
    public function getExtensions(): array
    {
        // refusal: the message is written for people (the frontend shows it as it is).
        return ['code' => $this->reason, 'refusal' => true];
    }
}

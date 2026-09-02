<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Requirements\Domain;

use Maryeperry\Bindle\Exceptions\MalformedManifestException;

/**
 * What would satisfy a requirement, expressed as intent rather than as a command.
 *
 * This class has no command field, and that absence is the contract. MME-2064
 * fences the capability on it: a README line like `brew install postgresql` is
 * recorded as Evidence, and separately summarized here as EnsureService with the
 * subject "postgresql". Deciding what that means on a given host — Homebrew,
 * apt, an already-running cluster — belongs to a later trusted installer under
 * its own approval policy, not to a scanner reading prose.
 */
final readonly class SetupHint
{
    /**
     * @param  string|null  $subject  What the intent acts on, e.g. "postgresql", "pnpm".
     * @param  string|null  $note  Human-facing detail; never machine-executed.
     */
    public function __construct(
        public SetupIntent $intent,
        public ?string $subject = null,
        public ?string $note = null,
    ) {}

    public function describe(): string
    {
        return $this->subject === null
            ? $this->intent->value
            : $this->intent->value.' '.$this->subject;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'intent' => $this->intent->value,
            'subject' => $this->subject,
            'note' => $this->note,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $intent = Wire::string($data, 'intent');

        return new self(
            intent: SetupIntent::tryFrom($intent)
                ?? throw MalformedManifestException::unknownValue('intent', $intent, 'setup intent'),
            subject: Wire::nullableString($data, 'subject'),
            note: Wire::nullableString($data, 'note'),
        );
    }
}

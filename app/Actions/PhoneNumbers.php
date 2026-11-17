<?php

namespace App\Actions;

use App\Models\Lead;
use App\Models\LeadPhone;

/**
 * Phone numbers as the telesales team types them: Bangladeshi numbers with or without +880 or the leading
 * zero, and international numbers. Finds the lead a number belongs to, whichever of its numbers it is.
 */
class PhoneNumbers
{
    /**
     * A Bangladeshi number becomes 01XXXXXXXXX. Another valid number becomes +digits. Anything else is null.
     */
    public function normalize(?string $phone): ?string
    {
        $raw = preg_replace('/[^\d+]/', '', (string) $phone) ?? '';
        $local = preg_replace('/^\+?880/', '0', $raw) ?? '';

        // A number that lost its leading zero, for example by being stored as a spreadsheet number.
        if (preg_match('/^\d{10}$/', $local) === 1) {
            $local = '0'.$local;
        }

        if (preg_match('/^0\d{10}$/', $local) === 1) {
            return $local;
        }

        $digits = str_replace('+', '', $raw);

        return preg_match('/^\d{8,15}$/', $digits) === 1 ? '+'.$digits : null;
    }

    /**
     * Every way the same number may be stored.
     *
     * @return list<string>
     */
    public function variants(string $phone): array
    {
        $normalized = $this->normalize($phone) ?? trim($phone);
        $variants = [$normalized, trim($phone)];

        if (preg_match('/^01\d{9}$/', $normalized) === 1) {
            $variants[] = '+88'.$normalized;
            $variants[] = '88'.$normalized;
            $variants[] = substr($normalized, 1);
        }

        return array_values(array_unique($variants));
    }

    /**
     * The lead that holds this number as its main, WhatsApp, alternative or extra number.
     */
    public function findLead(string $phone, ?int $exceptLeadId = null): ?Lead
    {
        $variants = $this->variants($phone);

        $lead = Lead::query()
            ->when($exceptLeadId !== null, fn ($query) => $query->whereKeyNot($exceptLeadId))
            ->where(fn ($query) => $query
                ->whereIn('contact_number', $variants)
                ->orWhereIn('whatsapp_number', $variants)
                ->orWhereIn('alternative_contact_number', $variants))
            ->orderBy('id')
            ->first();

        if ($lead !== null) {
            return $lead;
        }

        $extra = LeadPhone::query()->whereIn('phone_number', $variants)->first();

        return $extra === null || $extra->lead_id === $exceptLeadId ? null : Lead::query()->find($extra->lead_id);
    }

    /**
     * Which of the lead's numbers matched: primary, whatsapp, alternative or extra.
     */
    public function matchedAs(Lead $lead, string $phone): string
    {
        $variants = $this->variants($phone);

        return match (true) {
            in_array($lead->contact_number, $variants, true) => 'primary',
            in_array($lead->whatsapp_number, $variants, true) => 'whatsapp',
            in_array($lead->alternative_contact_number, $variants, true) => 'alternative',
            default => 'extra',
        };
    }

    /**
     * The number as WhatsApp links want it: country code, no plus.
     */
    public function forWhatsApp(string $phone): string
    {
        $normalized = $this->normalize($phone) ?? preg_replace('/\D/', '', $phone);

        return preg_match('/^01\d{9}$/', (string) $normalized) === 1 ? '88'.$normalized : ltrim((string) $normalized, '+');
    }
}

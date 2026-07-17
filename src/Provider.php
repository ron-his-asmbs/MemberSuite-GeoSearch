<?php
namespace MemberSuiteGeoSearch;

use Hashids\Hashids;

class Provider
{
private object $row;
private ?float $distance;
public function __construct(object $row, ?float $distance = null)
    {
$this->row      = $row;
$this->distance = $distance !== null ? round($distance, 1) : null;
    }
public function getMemberType(): string
    {
return $this->row->member_type ?? 'Member';
    }
public function getDistance(): ?float
    {
return $this->distance;
    }
public function getLatitude(): ?float
    {
return isset($this->row->latitude) ? (float) $this->row->latitude : null;
    }
public function getLongitude(): ?float
    {
return isset($this->row->longitude) ? (float) $this->row->longitude : null;
    }
public function getSurgeryTypes(): array
    {
if (empty($this->row->surgery_types)) {
return [];
        }
return json_decode($this->row->surgery_types, true) ?? [];
    }
public function getDisplayName(): string
    {
$parts = array_filter([
$this->row->first_name,
$this->row->last_name,
        ]);
$name = implode(' ', $parts);
if (!empty($this->row->member_type)) {
$name .= ', ' . $this->row->member_type;
        }
return $name;
    }

    /**
     * Builds the profile URL directly from local_id using the same
     * Hashids salt ('obesity') and slug pattern the profile page's
     * decode_provider_query_to_local_id() expects — no CMSPlugin, no
     * WordPress user account lookup required.
     *
     * Returns '' if this row hasn't been through a sync since the
     * local_id column was added.
     */
    public function getProfileLink(): string
    {
        if (empty($this->row->local_id)) {
            return '';
        }

        $hashids = new Hashids('obesity');

        // Same slug construction as the legacy CMSPlugin::getMemberProfileLink():
        // lowercase, strip to letters/spaces/hyphens, space-separated words
        // joined with hyphens, hash appended last.
        $slug        = strtolower(preg_replace('/[^A-Za-z -]/', '', $this->getDisplayName()));
        $slugParts   = array_filter(explode(' ', $slug));
        $slugParts[] = $hashids->encode((int) $this->row->local_id);
        $urlQuery    = implode('-', $slugParts);

        return home_url('/provider/' . $urlQuery);
    }

    public function getMemberID(): string
    {
        return (string) ($this->row->local_id ?? '');
    }

public function getPracticePhone(): string
    {
return $this->row->practice_phone ?? '';
    }
public function getAddresses(): array
    {
if (empty($this->row->city) && empty($this->row->state)) {
return [];
        }
return [new ProviderAddress((array) $this->row)];
    }
public function getPhotoUrl(): string
    {
if (!empty($this->row->image_guid)) {
return "https://images.membersuite.com/{$_ENV['MS_ASSOCIATION_ID']}/{$_ENV['MS_PARTITION_KEY']}/{$this->row->image_guid}";
        }
return '';
    }
public function getMemberTypeLabel(): string
    {
return match($this->row->member_category ?? '') {
'surgeon'          => 'Practicing Surgeon',
'integrated_health' => 'Integrated Health',
default            => 'Member',
        };
    }
public function isIntegratedHealth(): bool
    {
return ($this->row->member_category ?? '') === 'integrated_health';
    }
}
class ProviderAddress
{
private array $data;
public function __construct(array $data)
    {
$this->data = $data;
    }
public function getLine1(): string
    {
return $this->data['practice_line1'] ?? '';
    }
public function getLine2(): string
    {
return $this->data['practice_line2'] ?? '';
    }
public function getCity(): string
    {
return $this->data['city'] ?? '';
    }
public function getState(): string
    {
return $this->data['state'] ?? '';
    }
public function getZip(): string
    {
return $this->data['practice_zip'] ?? '';
    }
public function getCountry(): string
    {
return $this->data['country'] ?? 'US';
    }
}
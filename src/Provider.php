<?php

namespace MemberSuiteGeoSearch;

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

    public function getAddresses(): array
    {
        if (empty($this->row->city) && empty($this->row->state)) {
            return [];
        }
        return [new ProviderAddress((array) $this->row)];
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

    public function getProfileLink(bool $isPrivate = false): string
    {
        $wpUserId = $this->getWordPressUserId();
        if (!$wpUserId) {
            return '';
        }

        $cmsPlugin = \CMSPlugin::get_instance();
        return $cmsPlugin->getMemberProfileLink($this->getDisplayName(), $wpUserId, $isPrivate);
    }

    public function getMemberID(): string
    {
        return (string) $this->getWordPressUserId();
    }

    private function getWordPressUserId(): int
    {
        static $cache = [];

        if (!isset($cache[$this->row->member_id])) {
            $users = get_users([
                'meta_key'   => 'mem_key',
                'meta_value' => $this->row->member_id,
                'number'     => 1,
                'fields'     => 'ID',
            ]);
            $cache[$this->row->member_id] = !empty($users) ? (int) $users[0] : 0;
        }

        return $cache[$this->row->member_id];
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

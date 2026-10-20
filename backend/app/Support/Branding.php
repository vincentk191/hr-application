<?php

namespace App\Support;

use App\Models\Company;

/**
 * The colour and logo the app wears for whoever is looking, set from the admin panel.
 *
 * Looking at one company, it wears that company's colour and logo. Looking at all of them together
 * ("Semua perusahaan"), it wears the group colour, which is a setting of its own because it belongs
 * to no company, and no logo. Anything unset falls back to the app's default look, so nothing here
 * is required.
 */
class Branding
{
    public const GROUP_COLOR_KEY = 'branding.group_color';

    /**
     * The shape a colour must have: a six-digit hex, e.g. "#1d4ed8".
     */
    public const COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    public function __construct(private AppSettings $settings, private CompanyContext $context) {}

    public function groupColor(): ?string
    {
        $color = $this->settings->get(self::GROUP_COLOR_KEY);

        return is_string($color) && preg_match(self::COLOR_PATTERN, $color) === 1 ? $color : null;
    }

    public function setGroupColor(?string $color): void
    {
        $this->settings->set(self::GROUP_COLOR_KEY, $color !== null && preg_match(self::COLOR_PATTERN, $color) === 1 ? $color : null);
    }

    /**
     * @return array{name: string|null, color: string|null, logoUrl: string|null, logoDarkUrl: string|null}
     */
    public function current(): array
    {
        $companyId = $this->context->companyId();

        if ($this->context->isCombined() || $companyId === null) {
            return ['name' => null, 'color' => $this->groupColor(), 'logoUrl' => null, 'logoDarkUrl' => null];
        }

        $company = Company::query()->find($companyId, ['id', 'name', 'brand_color', 'logo_path', 'logo_dark_path']);

        if ($company === null) {
            return ['name' => null, 'color' => null, 'logoUrl' => null, 'logoDarkUrl' => null];
        }

        return [
            'name' => $company->name,
            'color' => $company->brand_color,
            'logoUrl' => $company->logoUrl(),
            'logoDarkUrl' => $company->logoDarkUrl(),
        ];
    }
}

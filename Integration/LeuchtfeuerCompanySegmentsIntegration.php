<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormFeatureSettingsInterface;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type\CompanySegmentsFeatureSettingsType;

class LeuchtfeuerCompanySegmentsIntegration extends BasicIntegration implements BasicInterface, ConfigFormFeatureSettingsInterface
{
    use ConfigurationTrait;

    public const NAME         = 'leuchtfeuercompanysegments';
    public const DISPLAY_NAME = 'Company Segments by Leuchtfeuer';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDisplayName(): string
    {
        return self::DISPLAY_NAME;
    }

    public function getIcon(): string
    {
        return 'plugins/LeuchtfeuerCompanySegmentsBundle/Assets/img/Mautic-Company-Segments.png';
    }

    public function getFeatureSettingsConfigFormName(): string
    {
        return CompanySegmentsFeatureSettingsType::class;
    }
}

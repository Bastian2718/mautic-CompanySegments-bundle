<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration;

use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\PluginBundle\Entity\Integration;

class Config
{
    public function __construct(private IntegrationsHelper $integrationsHelper)
    {
    }

    public function isPublished(): bool
    {
        try {
            $integration = $this->getIntegrationEntity();

            return (bool) $integration->getIsPublished();
        } catch (IntegrationNotFoundException) {
            return false;
        }
    }

    /**
     * @throws IntegrationNotFoundException
     */
    public function getIntegrationEntity(): Integration
    {
        $integrationObject = $this->integrationsHelper->getIntegration(LeuchtfeuerCompanySegmentsIntegration::NAME);

        return $integrationObject->getIntegrationConfiguration();
    }

    public function getCreatePlaceholderContact(): bool
    {
        try {
            $integration     = $this->getIntegrationEntity();
            $featureSettings = $integration->getFeatureSettings();

            if (!is_array($featureSettings)
                || !isset($featureSettings['integration'])
                || !is_array($featureSettings['integration'])
                || !isset($featureSettings['integration']['create_placeholder_contact'])) {
                return true;
            }

            return (bool) $featureSettings['integration']['create_placeholder_contact'];
        } catch (IntegrationNotFoundException) {
            return true;
        }
    }
}

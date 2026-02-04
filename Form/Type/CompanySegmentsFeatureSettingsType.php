<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class CompanySegmentsFeatureSettingsType extends AbstractType
{
    private ?bool $createPlaceholderContact;

    public function __construct(private Config $config)
    {
        $this->createPlaceholderContact = $this->config->getCreatePlaceholderContact();
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('create_placeholder_contact', YesNoButtonGroupType::class, [
            'label' => 'mautic.company_segments.form.config.create_placeholder_contact',
            'attr'  => [
                'tooltip' => 'mautic.company_segments.form.config.create_placeholder_contact.tooltip',
            ],
            'data' => $this->createPlaceholderContact,
        ]);
    }
}

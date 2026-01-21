<?php

namespace Functional\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\EnablePluginTrait;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\HelperCompanySegmentTestTrait;

class CompanySegmentControllerTest extends MauticMysqlTestCase
{
    use EnablePluginTrait;
    use HelperCompanySegmentTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enablePlugin(true);
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
        $this->loginAdminUser();
    }

    public function testNoSuccessDeleteCompanySegmentBecauseInUseInSegment(): void
    {
        $companySegmentGlibi = $this->createCompanySegment('Company Glibi', 'company-glibi', true);
        $companySegmentTBS   = $this->createCompanySegment('Company TBS', 'company-tbs', true);

        $filters = [
            [
                'glu'           => 'and',
                'operator'      => 'in',
                'properties'    => [
                    'filter' => [$companySegmentGlibi->getId(), $companySegmentTBS->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];

        $this->createSegment('Segment Contact Glibi', 'segment-contact-glibi', $filters, true);

        $this->client->request('POST', '/s/company-segments/delete/'.$companySegmentGlibi->getId().'?tmpl=list');
        self::assertIsString($this->client->getResponse()->getContent());
        self::assertStringContainsString('Company Segment cannot be deleted, it is required by segment', $this->client->getResponse()->getContent());
        $this->client->request('POST', '/s/company-segments/view/'.$companySegmentTBS->getId());
        self::assertIsString($companySegmentTBS->getName());
        self::assertIsString($this->client->getResponse()->getContent());
        self::assertStringContainsString($companySegmentTBS->getName(), $this->client->getResponse()->getContent());
    }

    public function testNoSuccessDeleteCompanySegmentBecauseInUseInOtherCompanySegment(): void
    {
        $companySegmentGlibi = $this->createCompanySegment('Company Glibi', 'company-glibi', true);

        $filters = [
            [
                'glu'           => 'and',
                'operator'      => 'in',
                'properties'    => [
                    'filter' => [$companySegmentGlibi->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];

        $this->createCompanySegment('Company TBS', 'company-tbs', true, $filters);
        $this->client->request('POST', '/s/company-segments/delete/'.$companySegmentGlibi->getId().'?tmpl=list');
        self::assertIsString($this->client->getResponse()->getContent());
        self::assertStringContainsString('Company Segment cannot be deleted, it is required by company segment', $this->client->getResponse()->getContent());
    }

    public function testSuccessDeleteCompanySegment(): void
    {
        $companySegmentGlibi = $this->createCompanySegment('Company Glibi', 'company-glibi', true);
        $this->client->request('POST', '/s/company-segments/delete/'.$companySegmentGlibi->getId().'?tmpl=list');
        self::assertIsString($this->client->getResponse()->getContent());
        self::assertStringContainsString('has been deleted!', $this->client->getResponse()->getContent());
    }

    public function testFailBatchDeleteCompanySegmentBecauseCompanySegmentAreInSegmentAndCompanySegment(): void
    {
        $companySegmentGlibi = $this->createCompanySegment('Company Glibi', 'company-glibi', true);
        $filters             = [
            [
                'glu'           => 'and',
                'operator'      => 'in',
                'properties'    => [
                    'filter' => [$companySegmentGlibi->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];

        $this->createSegment('Segment Contact Glibi', 'segment-contact-glibi', $filters, true);

        $companySegmentTBS = $this->createCompanySegment('Company TBS', 'company-tbs', true);

        $filters = [
            [
                'glu'           => 'and',
                'operator'      => 'in',
                'properties'    => [
                    'filter' => [$companySegmentTBS->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];

        $companySegmentRecord = $this->createCompanySegment('Company Record', 'company-record', true, $filters);
        $url                  = sprintf('/s/company-segments/batchDelete?tmpl=list&ids=["%s","%s"]', $companySegmentGlibi->getId(), $companySegmentTBS->getId());
        $this->client->request('POST', $url);
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('cannot be deleted, it is required by other segments.', $content);
        self::assertStringContainsString('cannot be deleted, it is required by other company segments.', $content);
        self::assertStringNotContainsString('has been deleted!', $content);
    }
}

<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Functional\EventListener;

use Mautic\DynamicContentBundle\Entity\DynamicContent;
use Mautic\PageBundle\Entity\Page;
use Mautic\PluginBundle\Entity\Integration;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\EnablePluginTrait;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\HelperCompanySegmentTestTrait;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\MauticMysqlTestCase;
use Symfony\Component\HttpFoundation\Request;

class DynamicContentSubscriberFunctionalTest extends MauticMysqlTestCase
{
    use EnablePluginTrait;
    use HelperCompanySegmentTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
        $this->enablePlugin(true);

        // DEBUG: Check DB directly after enablePlugin
        $integrationFromDb = $this->em->getRepository(Integration::class)->findOneBy(['name' => 'LeuchtfeuerCompanySegments']);
        error_log('[SETUP-DEBUG] Integration in DB after enablePlugin: ' . ($integrationFromDb ? 'FOUND' : 'NOT FOUND'));
        if ($integrationFromDb) {
            error_log('[SETUP-DEBUG] Integration.isPublished in DB: ' . ($integrationFromDb->getIsPublished() ? 'YES' : 'NO'));
        }

        // DEBUG: Check what Config service sees
        $config = self::getContainer()->get(\MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config::class);
        error_log('[SETUP-DEBUG] Config.isPublished(): ' . ($config->isPublished() ? 'YES' : 'NO'));

        // DEBUG: Check if subscriber is registered
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $hasListeners = $dispatcher->hasListeners(\Mautic\DynamicContentBundle\DynamicContentEvents::ON_CONTACTS_FILTER_EVALUATE);
        error_log('[SETUP-DEBUG] EventDispatcher has DWC listeners: ' . ($hasListeners ? 'YES' : 'NO'));

        if ($hasListeners) {
            $listeners = $dispatcher->getListeners(\Mautic\DynamicContentBundle\DynamicContentEvents::ON_CONTACTS_FILTER_EVALUATE);
            error_log('[SETUP-DEBUG] Number of listeners: ' . count($listeners));
            foreach ($listeners as $listener) {
                $listenerClass = is_array($listener) && is_object($listener[0]) ? get_class($listener[0]) : 'unknown';
                error_log('[SETUP-DEBUG] Listener: ' . $listenerClass);
            }
        }
    }

    public function testLeadSeesContentWhenPrimaryCompanyIsInSegment(): void
    {
        $companySegment = $this->createCompanySegment('VIP Companies', 'vip-companies');

        $company = $this->createCompany('ACME Corp');
        $this->addCompanyToCompanySegment($company, $companySegment);

        $lead = $this->createLead('john@acme.com', 'John');
        $this->addLeadToCompany($lead, $company, true);

        $contactTracker = self::getContainer()->get('mautic.tracker.contact');
        $contactTracker->setSystemContact($lead);

        $filters = [
            [
                'glue'     => 'and',
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'filter'   => [$companySegment->getId()],
                'display'  => null,
                'operator' => 'in',
            ],
        ];
        $dynamicContent = $this->createDynamicContentWithFilter($filters, 'VIP Content');

        // DEBUG: Check if plugin is published
        $config = self::getContainer()->get(\MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config::class);
        $this->assertTrue($config->isPublished(), 'Plugin should be published');

        $page = $this->createPage($dynamicContent);

        // DEBUG: Check client container's subscriber
        $clientDispatcher = $this->client->getContainer()->get('event_dispatcher');
        $clientHasListeners = $clientDispatcher->hasListeners(\Mautic\DynamicContentBundle\DynamicContentEvents::ON_CONTACTS_FILTER_EVALUATE);
        error_log('[TEST-DEBUG] Client container has DWC listeners: ' . ($clientHasListeners ? 'YES' : 'NO'));

        // DEBUG: Check if plugin is published in client container
        $clientConfig = $this->client->getContainer()->get(\MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config::class);
        error_log('[TEST-DEBUG] Client container Config.isPublished(): ' . ($clientConfig->isPublished() ? 'YES' : 'NO'));

        $this->client->request(Request::METHOD_GET, sprintf('/%s', $page->getAlias()));

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        // DEBUG: Show actual content if assertion fails
        $content = $response->getContent();
        $this->assertStringContainsString('VIP Content', $content, 'Expected VIP Content but got: ' . $content);
    }

    public function testLeadDoesNotSeeContentWhenPrimaryCompanyIsNotInSegment(): void
    {
        $companySegment = $this->createCompanySegment('VIP Companies', 'vip-companies');

        $company = $this->createCompany('Regular Corp');

        $lead = $this->createLead('jane@regular.com', 'Jane');
        $this->addLeadToCompany($lead, $company, true);

        $contactTracker = self::getContainer()->get('mautic.tracker.contact');
        $contactTracker->setSystemContact($lead);

        $filters = [
            [
                'glue'     => 'and',
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'filter'   => [$companySegment->getId()],
                'display'  => null,
                'operator' => 'in',
            ],
        ];
        $dynamicContent = $this->createDynamicContentWithFilter($filters, 'VIP Content');

        $page = $this->createPage($dynamicContent);

        $this->client->request(Request::METHOD_GET, sprintf('/%s', $page->getAlias()));

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('VIP Content', $response->getContent());
    }

    public function testLeadSeesContentWithNotInOperator(): void
    {
        $companySegment = $this->createCompanySegment('VIP Companies', 'vip-companies');

        $company = $this->createCompany('Regular Corp');

        $lead = $this->createLead('jane@regular.com', 'Jane');
        $this->addLeadToCompany($lead, $company, true);

        // Set lead as system contact for this request
        $contactTracker = self::getContainer()->get('mautic.tracker.contact');
        $contactTracker->setSystemContact($lead);

        $filters = [
            [
                'glue'     => 'and',
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'filter'   => [$companySegment->getId()],
                'display'  => null,
                'operator' => '!in',
            ],
        ];
        $dynamicContent = $this->createDynamicContentWithFilter($filters, 'Non-VIP Content');

        $page = $this->createPage($dynamicContent);

        $this->client->request(Request::METHOD_GET, sprintf('/%s', $page->getAlias()));

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Non-VIP Content', $response->getContent());
    }

    public function testLeadWithoutPrimaryCompanySeesContentWithEmptyOperator(): void
    {
        $lead = $this->createLead('solo@example.com', 'Solo');

        // Set lead as system contact for this request
        $contactTracker = self::getContainer()->get('mautic.tracker.contact');
        $contactTracker->setSystemContact($lead);

        $filters = [
            [
                'glue'     => 'and',
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'filter'   => null,
                'display'  => null,
                'operator' => 'empty',
            ],
        ];
        $dynamicContent = $this->createDynamicContentWithFilter($filters, 'No Company Segment Content');

        $page = $this->createPage($dynamicContent);

        $this->client->request(Request::METHOD_GET, sprintf('/%s', $page->getAlias()));

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('No Company Segment Content', $response->getContent());
    }

    public function testLeadWithCompanyInSegmentDoesNotSeeContentWithEmptyOperator(): void
    {
        $companySegment = $this->createCompanySegment('VIP Companies', 'vip-companies');

        $company = $this->createCompany('ACME Corp');
        $this->addCompanyToCompanySegment($company, $companySegment);

        $lead = $this->createLead('john@acme.com', 'John');
        $this->addLeadToCompany($lead, $company, true);

        $contactTracker = self::getContainer()->get('mautic.tracker.contact');
        $contactTracker->setSystemContact($lead);

        $filters = [
            [
                'glue'     => 'and',
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'filter'   => null,
                'display'  => null,
                'operator' => 'empty',
            ],
        ];
        $dynamicContent = $this->createDynamicContentWithFilter($filters, 'No Company Segment Content');

        $page = $this->createPage($dynamicContent);

        $this->client->request(Request::METHOD_GET, sprintf('/%s', $page->getAlias()));

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('No Company Segment Content', $response->getContent());
    }

    public function testLeadWithCompanyInSegmentSeesContentWithNotEmptyOperator(): void
    {
        $companySegment = $this->createCompanySegment('VIP Companies', 'vip-companies');

        $company = $this->createCompany('ACME Corp');
        $this->addCompanyToCompanySegment($company, $companySegment);

        $lead = $this->createLead('john@acme.com', 'John');
        $this->addLeadToCompany($lead, $company, true);

        // Set lead as system contact for this request
        $contactTracker = self::getContainer()->get('mautic.tracker.contact');
        $contactTracker->setSystemContact($lead);

        $filters = [
            [
                'glue'     => 'and',
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'filter'   => null,
                'display'  => null,
                'operator' => '!empty',
            ],
        ];
        $dynamicContent = $this->createDynamicContentWithFilter($filters, 'Has Company Segment Content');

        $page = $this->createPage($dynamicContent);

        $this->client->request(Request::METHOD_GET, sprintf('/%s', $page->getAlias()));

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Has Company Segment Content', $response->getContent());
    }

    public function testLeadWithoutPrimaryCompanyDoesNotSeeContentWithNotEmptyOperator(): void
    {
        $lead = $this->createLead('solo@example.com', 'Solo');

        $contactTracker = self::getContainer()->get('mautic.tracker.contact');
        $contactTracker->setSystemContact($lead);

        $filters = [
            [
                'glue'     => 'and',
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'filter'   => null,
                'display'  => null,
                'operator' => '!empty',
            ],
        ];
        $dynamicContent = $this->createDynamicContentWithFilter($filters, 'Has Company Segment Content');

        $page = $this->createPage($dynamicContent);

        $this->client->request(Request::METHOD_GET, sprintf('/%s', $page->getAlias()));

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('Has Company Segment Content', $response->getContent());
    }

    public function testLeadWithCompanyInMultipleSegments(): void
    {
        $segment1 = $this->createCompanySegment('VIP Companies', 'vip-companies');
        $segment2 = $this->createCompanySegment('Gold Companies', 'gold-companies');

        $company = $this->createCompany('Premium Corp');
        $this->addCompanyToCompanySegment($company, $segment1);
        $this->addCompanyToCompanySegment($company, $segment2);

        $lead = $this->createLead('premium@corp.com', 'Premium');
        $this->addLeadToCompany($lead, $company, true);

        $contactTracker = self::getContainer()->get('mautic.tracker.contact');
        $contactTracker->setSystemContact($lead);

        $filters = [
            [
                'glue'     => 'and',
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'filter'   => [$segment1->getId(), $segment2->getId()],
                'display'  => null,
                'operator' => 'in',
            ],
        ];
        $dynamicContent = $this->createDynamicContentWithFilter($filters, 'Premium Content');

        $page = $this->createPage($dynamicContent);

        $this->client->request(Request::METHOD_GET, sprintf('/%s', $page->getAlias()));

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Premium Content', $response->getContent());
    }

    /**
     * Helper: Create DynamicContent with company_segments filters.
     *
     * @param array<array<mixed>> $filters
     */
    private function createDynamicContentWithFilter(array $filters, string $content): DynamicContent
    {
        $dynamicContent = new DynamicContent();
        $dynamicContent->setName('Test DWC: '.$content);
        $dynamicContent->setDescription('Test Dynamic Web Content');
        $dynamicContent->setFilters($filters);
        $dynamicContent->setIsCampaignBased(false);
        $dynamicContent->setSlotName('test_slot_'.uniqid());
        $dynamicContent->setContent($content);
        $dynamicContent->setIsPublished(true);
        $this->em->persist($dynamicContent);
        $this->em->flush();

        return $dynamicContent;
    }

    private function createPage(DynamicContent $dynamicContent): Page
    {
        $dwcToken = sprintf('{dwc=%s}', $dynamicContent->getSlotName());

        $page = new Page();
        $page->setIsPublished(true);
        $page->setTitle('Test Page with DWC');
        $page->setAlias('test-page-dwc-'.uniqid());
        $page->setTemplate('Blank');
        $page->setCustomHtml('<html><body>'.$dwcToken.'</body></html>');
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }
}

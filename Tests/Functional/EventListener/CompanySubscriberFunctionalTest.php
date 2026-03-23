<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Functional\EventListener;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesPlaceholderLeads;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegmentsRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\EnablePluginTrait;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\HelperCompanySegmentTestTrait;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\MauticMysqlTestCase;

class CompanySubscriberFunctionalTest extends MauticMysqlTestCase
{
    use EnablePluginTrait;
    use HelperCompanySegmentTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
        $this->enablePlugin(true);
        $this->loginAdminUser();
    }

    public function testCreateNewCompanyWithNewPlaceholderLeadAndUpdateCompany(): void
    {
        $company  = $this->createCompany();
        $leadRepo = $this->em->getRepository(Lead::class);
        $leads    = $leadRepo->findAll();
        $this->assertCount(1, $leads);
        $lead = $leads[0];
        $this->assertInstanceOf(Lead::class, $lead);
        $this->assertIsString($lead->getEmail());
        $this->assertFieldsSetCorrectlyForPlaceholderLead($lead, $lead->getEmail());
        $this->assertCorrectCompanyPlaceholderEntryExists($company, $lead);
        $this->assertLeadAddedToCompany($company, $lead);

        // Placeholder lead email should change after updating company email
        $company->setEmail('b@b.com');
        $companyModel = $this->getContainer()->get('mautic.lead.model.company');
        $this->assertInstanceOf(CompanyModel::class, $companyModel);
        $companyModel->saveEntity($company);
        $leads    = $leadRepo->findAll();
        $this->assertCount(1, $leads);
        $lead = $leads[0];
        $this->assertInstanceOf(Lead::class, $lead);
        $this->assertFieldsSetCorrectlyForPlaceholderLead($lead, 'b@b.com');
        $this->assertCorrectCompanyPlaceholderEntryExists($company, $lead);
    }

    public function testContactWithSameEmailAsPlaceholderContactAlreadyExists(): void
    {
        $this->createLead('a@a.com');
        $company  = $this->createCompany();
        $leadRepo = $this->em->getRepository(Lead::class);
        $leads    = $leadRepo->findAll();
        $this->assertCount(2, $leads);
        usort($leads, fn ($a, $b) => $a->getId() <=> $b->getId());
        $lead = end($leads);
        $this->assertInstanceOf(Lead::class, $lead);
        $this->assertFieldsSetCorrectlyForPlaceholderLead($lead, 'a+1@a.com');
        $this->assertCorrectCompanyPlaceholderEntryExists($company, $lead);
        $this->assertLeadAddedToCompany($company, $lead);
    }

    public function testPlaceholderLeadIsDeletedWithCompany(): void
    {
        $company  = $this->createCompany();
        $leadRepo = $this->em->getRepository(Lead::class);
        $leads    = $leadRepo->findAll();
        $this->assertCount(1, $leads);

        $companyModel = $this->getContainer()->get('mautic.lead.model.company');
        $this->assertInstanceOf(CompanyModel::class, $companyModel);
        $companyModel->deleteEntity($company);
        $leadRepo = $this->em->getRepository(Lead::class);
        $leads    = $leadRepo->findAll();
        $this->assertCount(0, $leads);
    }

    public function testNoPlaceholderLeadCreatedWhenFeatureIsDisabled(): void
    {
        $this->setCreatePlaceholderContactSetting(false);

        $company = $this->createCompany();

        $leadRepo = $this->em->getRepository(Lead::class);
        $leads    = $leadRepo->findAll();
        $this->assertCount(0, $leads, 'No placeholder contact should be created when feature is disabled');

        $placeholderContactRepo    = $this->em->getRepository(CompaniesPlaceholderLeads::class);
        $placeholderContactEntries = $placeholderContactRepo->findAll();
        $this->assertCount(0, $placeholderContactEntries, 'No placeholder entry should exist when feature is disabled');
    }

    private function setCreatePlaceholderContactSetting(bool $enabled): void
    {
        $integrationHelper = $this->getContainer()->get(\Mautic\PluginBundle\Helper\IntegrationHelper::class);
        $this->assertInstanceOf(\Mautic\PluginBundle\Helper\IntegrationHelper::class, $integrationHelper);

        $integration = $integrationHelper->getIntegrationObject('LeuchtfeuerCompanySegments');
        $this->assertInstanceOf(\Mautic\IntegrationsBundle\Integration\Interfaces\IntegrationInterface::class, $integration);

        $integrationEntity = $integration->getIntegrationConfiguration();

        $featureSettings = $integrationEntity->getFeatureSettings();
        if (!is_array($featureSettings)) {
            $featureSettings = [];
        }
        if (!isset($featureSettings['integration']) || !is_array($featureSettings['integration'])) {
            $featureSettings['integration'] = [];
        }
        $featureSettings['integration']['create_placeholder_contact'] = $enabled;
        $integrationEntity->setFeatureSettings($featureSettings);

        $this->em->persist($integrationEntity);
        $this->em->flush();
    }
    
    public function testAddToAndRemoveFromSegmentsViaCompanyForm(): void
    {
        if (!class_exists(\Mautic\CoreBundle\Cache\ResultCacheOptions::class)) {
            $this->markTestSkipped('This test requires Mautic 5.1+ (ResultCacheOptions class)');
        }

        $company  = $this->createCompany('Test Company');
        $segment1 = $this->createCompanySegment('Segment 1', 'segment-1');
        $segment2 = $this->createCompanySegment('Segment 2', 'segment-2');
        $segment3 = $this->createCompanySegment('Segment 3', 'segment-3');

        $this->addCompanyToCompanySegment($company, $segment1);
        $this->addCompanyToCompanySegment($company, $segment2);

        $companyId = $company->getId();
        $this->em->clear();

        $crawler = $this->client->request('GET', '/s/companies/edit/'.$companyId);
        $this->assertTrue($this->client->getResponse()->isOk());

        $form                              = $crawler->filter('form[name="company"]')->first()->form();
        $form['company[company_segments]'] = [$segment1->getId(), $segment3->getId()];
        $this->client->submit($form);

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->em->clear();

        $company = $this->em->getRepository(Company::class)->find($companyId);
        $this->assertNotNull($company);

        /** @var CompaniesSegmentsRepository $companiesSegmentsRepository */
        $companiesSegmentsRepository = $this->em->getRepository(CompaniesSegments::class);
        $companySegments             = $companiesSegmentsRepository->getByCompany($company);
        $this->assertCount(2, $companySegments, 'Company should be in 2 segments');

        $segmentIds = array_map(fn ($cs) => $cs->getCompanySegment()->getId(), $companySegments);
        $this->assertContains($segment1->getId(), $segmentIds, 'Company should still be in segment 1');
        $this->assertNotContains($segment2->getId(), $segmentIds, 'Company should be removed from segment 2');
        $this->assertContains($segment3->getId(), $segmentIds, 'Company should be added to segment 3');
    }

    public function testRemoveAllSegmentsViaCompanyForm(): void
    {
        if (!class_exists(\Mautic\CoreBundle\Cache\ResultCacheOptions::class)) {
            $this->markTestSkipped('This test requires Mautic 5.1+ (ResultCacheOptions class)');
        }

        $company  = $this->createCompany('Test Company');
        $segment1 = $this->createCompanySegment('Segment 1', 'segment-1');
        $segment2 = $this->createCompanySegment('Segment 2', 'segment-2');

        $this->addCompanyToCompanySegment($company, $segment1);
        $this->addCompanyToCompanySegment($company, $segment2);

        $companyId = $company->getId();
        $this->em->clear();

        // Verify company is in 2 segments
        $company                     = $this->em->getRepository(Company::class)->find($companyId);
        $companiesSegmentsRepository = $this->em->getRepository(CompaniesSegments::class);
        $companySegments             = $companiesSegmentsRepository->getByCompany($company);
        $this->assertCount(2, $companySegments, 'Company should initially be in 2 segments');

        // Now remove all segments by submitting an empty array
        $crawler = $this->client->request('GET', '/s/companies/edit/'.$companyId);
        $this->assertTrue($this->client->getResponse()->isOk());

        $form                              = $crawler->filter('form[name="company"]')->first()->form();
        $form['company[company_segments]'] = []; // Empty array - remove all segments
        $this->client->submit($form);

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->em->clear();

        // Verify all segments were removed
        $company         = $this->em->getRepository(Company::class)->find($companyId);
        $this->assertNotNull($company);

        $companySegments = $companiesSegmentsRepository->getByCompany($company);
        $this->assertCount(0, $companySegments, 'Company should have no segments after removing all');
    }

    private function assertCorrectCompanyPlaceholderEntryExists(Company $company, Lead $lead): void
    {
        $placeholderContactRepo    = $this->em->getRepository(CompaniesPlaceholderLeads::class);
        $placeholderContactEntries = $placeholderContactRepo->findAll();
        $this->assertCount(1, $placeholderContactEntries);
        $this->assertInstanceOf(CompaniesPlaceholderLeads::class, $placeholderContactEntries[0]);
        $placeholderContactEntry = $placeholderContactEntries[0];
        $this->assertEquals($company->getId(), $placeholderContactEntry->getCompany()->getId());
        $this->assertEquals($lead->getId(), $placeholderContactEntry->getLead()->getId());
    }

    private function assertFieldsSetCorrectlyForPlaceholderLead(Lead $lead, string $email): void
    {
        $this->assertEquals('abc', $lead->getFirstname());
        $this->assertEquals('[PLACEHOLDER]', $lead->getLastname());
        $this->assertEquals($email, $lead->getEmail());
        $this->assertEquals('1234567890', $lead->getPhone());
        $this->assertEquals('Street 1', $lead->getAddress1());
        $this->assertEquals('Street 2', $lead->getAddress2());
        $this->assertEquals('City', $lead->getCity());
        $this->assertEquals('12345', $lead->getZipcode());
        $this->assertEquals('0987654321', $lead->getFieldValue('fax'));
    }

    private function createCompany(?string $name = null): Company
    {
        $company = new Company();
        $company->setName($name ?? 'abc');
        $company->setEmail('a@a.com');
        $company->setPhone('1234567890');
        $company->setAddress1('Street 1');
        $company->setAddress2('Street 2');
        $company->setCity('City');
        $company->setZipcode('12345');
        $company->addUpdatedField('companyfax', '0987654321');
        $company->setDateAdded(new \DateTime());
        $company->setDateModified(new \DateTime());
        $companyModel = $this->getContainer()->get('mautic.lead.model.company');
        $this->assertInstanceOf(CompanyModel::class, $companyModel);
        $companyModel->saveEntity($company);

        return $company;
    }

    private function createLead(string $email): Lead
    {
        $lead = new Lead();
        $lead->setEmail($email);
        $lead->setDateAdded(new \DateTime());
        $lead->setDateModified(new \DateTime());
        $leadModel = $this->getContainer()->get('mautic.lead.model.lead');
        $this->assertInstanceOf(LeadModel::class, $leadModel);
        $leadModel->saveEntity($lead);

        return $lead;
    }

    private function assertLeadAddedToCompany(Company $company, Lead $lead): void
    {
        /** @var CompanyLeadRepository $companyLeadRepo */
        $companyLeadRepo  = $this->em->getRepository(CompanyLead::class);
        $companyLeads     = $companyLeadRepo->getCompanyLeads($company->getId());
        $leadIds          = array_column($companyLeads, 'lead_id');
        $this->assertContains((string) $lead->getId(), $leadIds);
    }
}

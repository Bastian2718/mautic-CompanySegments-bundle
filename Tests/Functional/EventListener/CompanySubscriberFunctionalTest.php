<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Functional\EventListener;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesPlaceholderLeads;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\EnablePluginTrait;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\MauticMysqlTestCase;

class CompanySubscriberFunctionalTest extends MauticMysqlTestCase
{
    use EnablePluginTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin(true);
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
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

        // Placeholder lead email should change after updating company email
        $company->setEmail('b@b.com');
        $companyModel = $this->getContainer()->get('mautic.lead.model.company');
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
        $this->assertEquals($email, $lead->getEmail());
        $this->assertEquals('1234567890', $lead->getPhone());
        $this->assertEquals('Street 1', $lead->getAddress1());
        $this->assertEquals('Street 2', $lead->getAddress2());
        $this->assertEquals('City', $lead->getCity());
        $this->assertEquals('State', $lead->getState());
        $this->assertEquals('Country', $lead->getCountry());
        $this->assertEquals('12345', $lead->getZipcode());
    }

    private function createCompany(): Company
    {
        $company = new Company();
        $company->setName('abc');
        $company->setEmail('a@a.com');
        $company->setPhone('1234567890');
        $company->setAddress1('Street 1');
        $company->setAddress2('Street 2');
        $company->setCity('City');
        $company->setState('State');
        $company->setCountry('Country');
        $company->setZipcode('12345');
        $company->setDateAdded(new \DateTime());
        $company->setDateModified(new \DateTime());
        $companyModel = $this->getContainer()->get('mautic.lead.model.company');
        $companyModel->saveEntity($company);

        return $company;
    }

    public function createLead(string $email): Lead
    {
        $lead = new Lead();
        $lead->setEmail($email);
        $lead->setDateAdded(new \DateTime());
        $lead->setDateModified(new \DateTime());
        $leadModel = $this->getContainer()->get('mautic.lead.model.lead');
        $leadModel->saveEntity($lead);

        return $lead;
    }

    private function activePlugin(bool $isPublished = true): void
    {
        $this->client->request('GET', '/s/plugins/reload');
        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => 'LeuchtfeuerCompanySegments']);
        if (null === $integration) {
            $plugin      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => 'LeuchtfeuerCompanySegmentsBundle']);
            $integration = new Integration();
            $integration->setName('LeuchtfeuerCompanySegments');
            $integration->setPlugin($plugin);
            $integration->setApiKeys([]);
        }
        $integration->setIsPublished($isPublished);
        $integrationRepository = $this->em->getRepository(Integration::class);
        assert($integrationRepository instanceof \Mautic\PluginBundle\Entity\IntegrationRepository);
        $integrationRepository->saveEntity($integration);
        $this->em->persist($integration);
        $this->em->flush();
    }
}

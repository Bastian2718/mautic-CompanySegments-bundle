<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Functional\Commands;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\ListLead;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\MauticMysqlTestCase;

class UpdateCompanySegmentsCommandTest extends MauticMysqlTestCase
{
    /**
     * Tests that a company segment with an "in" filter on another company segment
     * correctly adds matching companies when the update command is executed.
     *
     * Setup:
     *   Company Segment 1 (test_segment)  : Manually contains Globo
     *   Company Segment 2 (test_segment2) : Filter = company_segments IN [Segment 1]
     *
     * Before command:
     *   +--------+---------------+
     *   | Company| Segments      |
     *   +--------+---------------+
     *   | Globo  | Segment 1     |
     *   | SBT    | --            |
     *   | Record | --            |
     *   +--------+---------------+
     *
     * After leuchtfeuer:abm:segments-update:
     *   +--------+---------------+
     *   | Company| Segments      |
     *   +--------+---------------+
     *   | Globo  | Segment 1, 2  |  <-- added to Segment 2 by filter
     *   | SBT    | --            |
     *   | Record | --            |
     *   +--------+---------------+
     */
    public function testUpdateCompanySegmentsCommandAddItemInNewSegment(): void
    {
        $companyGlobo  = $this->addCompany('Globo', 'contact@globo.com');
        $companySbt    = $this->addCompany('SBT', 'contact@sbt.com');
        $companyRecord = $this->addCompany('Record', 'contact@record.com');

        $leadOne   = $this->createLead('John Globo Doe', 'leadone@mautic.com');
        $leadTwo   = $this->createLead('Brian Doe', 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', 'leadthree@mautic.com');

        $leadOne->setCompany($companySbt);
        $leadOne->setPrimaryCompany($companyGlobo);

        $leadTwo->setPrimaryCompany($companyRecord);

        $leadThree->setPrimaryCompany($companyRecord);
        $leadThree->setCompany($companyGlobo);

        $this->em->persist($leadOne);
        $this->em->persist($leadTwo);
        $this->em->persist($leadThree);
        $this->em->flush();

        $companySegmentOne = $this->createCompanySegment('Test Segment 1', 'test_segment');
        $this->addCompanyToSegments($companyGlobo, $companySegmentOne);
        $filters              = [
            'filters' => [
                'glue'       => 'and',
                'operator'   => 'in',
                'properties' => [
                    'filter' => [$companySegmentOne->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];
        $companySegmentTwo             = $this->createCompanySegment('Test Segment 2', 'test_segment2', true, $filters);
        $resultCompaniesSegmentsBefore = $this->em->getRepository(CompaniesSegments::class)->findAll();

        self::assertCount(1, $resultCompaniesSegmentsBefore);

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:abm:segments-update');
        $commandTester->assertCommandIsSuccessful();

        $resultCompaniesSegmentsAfter = $this->em->getRepository(CompaniesSegments::class)->findAll();
        self::assertCount(2, $resultCompaniesSegmentsAfter);

        $segmentIds = array_map(fn ($cs) => $cs->getCompanySegment()->getId(), $resultCompaniesSegmentsAfter);
        self::assertContains($companySegmentOne->getId(), $segmentIds);
        self::assertContains($companySegmentTwo->getId(), $segmentIds);

        foreach ($resultCompaniesSegmentsAfter as $companiesSegments) {
            self::assertSame($companyGlobo->getId(), $companiesSegments->getCompany()->getId());
        }
    }

    /**
     * Tests that a lead segment with a "!in" (exclude) filter on a company segment
     * correctly excludes leads whose company belongs to that segment.
     *
     * Setup:
     *   Company Segment 1 (test_comp_segment) : Manually contains Globo
     *   Lead Segment 1 (test_segment)         : Filter = company_segments !IN [Segment 1]
     *
     * Company-Lead links:
     *   +--------+-------+
     *   | Company| Leads |
     *   +--------+-------+
     *   | Globo  | 1, 2  |
     *   | SBT    | 3, 4  |
     *   | Record | --    |
     *   +--------+-------+
     *
     * After mautic:segments:update:
     *   Lead Segment 1 should contain leads 3 and 4 (SBT leads).
     *   Leads 1 and 2 are excluded because Globo is in Company Segment 1.
     */
    public function testUpdateLeadSegmentsUsingExcludeACompanySegment(): void
    {
        $companyGlobo  = $this->addCompany('Globo', 'contact@globo.com');
        $companySbt    = $this->addCompany('SBT', 'contact@sbt.com');
        $companyRecord = $this->addCompany('Record', 'contact@record.com');

        $leadOne   = $this->createLead('John Globo Doe', 'leadone@mautic.com');
        $leadTwo   = $this->createLead('Brian Doe', 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', 'leadthree@mautic.com');
        $leadFour  = $this->createLead('Braw Doe', 'leadfour@mautic.com');

        $this->addLeadToCompany($companyGlobo, $leadOne);
        $this->addLeadToCompany($companyGlobo, $leadTwo);
        $this->addLeadToCompany($companySbt, $leadThree);
        $this->addLeadToCompany($companySbt, $leadFour);

        $totalCompanyLeadsBefore = $this->em->getRepository(CompanyLead::class)->findAll();
        self::assertCount(4, $totalCompanyLeadsBefore);
        $companySegmentOne = $this->createCompanySegment('Test Company Segment 1', 'test_comp_segment');
        $this->addCompanyToSegments($companyGlobo, $companySegmentOne);
        $resultCompaniesSegmentsBefore = $this->em->getRepository(CompaniesSegments::class)->findAll();
        self::assertCount(1, $resultCompaniesSegmentsBefore);

        $filtersToLeadSegment = [
            [
                'glue'       => 'and',
                'operator'   => '!in',
                'properties' => [
                    'filter' => [$companySegmentOne->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];

        $leadSegmentOne = $this->createLeadSegment('Test Segment 1', 'test_segment', true, $filtersToLeadSegment);
        $leadListModel  = static::getContainer()->get('mautic.lead.model.list');
        assert($leadListModel instanceof \Mautic\LeadBundle\Model\ListModel);

        // Before running the segment update command, no leads should be in the segment yet
        $leadListTotalBefore = $leadListModel->getListLeadRepository()->findAll();
        self::assertCount(0, $leadListTotalBefore);

        // Run Mautic contact segment update command
        $commandTester = $this->testSymfonyCommand('mautic:segments:update');
        $commandTester->assertCommandIsSuccessful();

        self::assertStringContainsString('2 total contact(s) to be added', $commandTester->getDisplay());

        $leadListTotalAfter = $leadListModel->getListLeadRepository()->findAll();
        self::assertCount(2, $leadListTotalAfter);
    }

    /**
     * Tests a combined workflow: first running the company segment update, then the lead segment update.
     *
     * Setup:
     *   Company Segment 1 (test_comp_segment)  : Manually contains Globo
     *   Company Segment 2 (test_comp_segment2) : Filter = company_segments IN [Segment 1]
     *   Lead Segment 2 (test_segment2)         : Filter = address1 != "asdasdaadasd"
     *                                            AND company_segments !IN [Segment 2]
     *
     * Company-Lead links:
     *   +--------+-------+
     *   | Company| Leads |
     *   +--------+-------+
     *   | Globo  | 1, 2  |
     *   | SBT    | 3, 4  |
     *   | Record | --    |
     *   +--------+-------+
     *
     * Step 1 - leuchtfeuer:abm:segments-update:
     *   Globo is auto-added to Company Segment 2 (matches filter).
     *
     * Step 2 - mautic:segments:update:
     *   Lead Segment 2 should contain leads 3 and 4 only.
     *   Leads 1 and 2 are excluded because Globo is now in Company Segment 2.
     */
    public function testUpdateCompanySegmentsAndUpdateLeadSegmentCommandAddingAllContactsLessCompanySegment(): void
    {
        $companyGlobo  = $this->addCompany('Globo', 'contact@globo.com');
        $companySbt    = $this->addCompany('SBT', 'contact@sbt.com');
        $companyRecord = $this->addCompany('Record', 'contact@record.com');

        $leadOne   = $this->createLead('John Globo Doe', 'leadone@mautic.com');
        $leadTwo   = $this->createLead('Brian Doe', 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', 'leadthree@mautic.com');
        $leadFour  = $this->createLead('Braw Doe', 'leadfour@mautic.com');

        $this->addLeadToCompany($companyGlobo, $leadOne);
        $this->addLeadToCompany($companyGlobo, $leadTwo);
        $this->addLeadToCompany($companySbt, $leadThree);
        $this->addLeadToCompany($companySbt, $leadFour);

        $totalCompanyLeadsBefore = $this->em->getRepository(CompanyLead::class)->findAll();
        self::assertCount(4, $totalCompanyLeadsBefore);

        $companySegmentOne = $this->createCompanySegment('Test Company Segment 1', 'test_comp_segment');

        // Manually add Globo to Company Segment 1
        $this->addCompanyToSegments($companyGlobo, $companySegmentOne);
        $resultCompaniesSegmentsBefore = $this->em->getRepository(CompaniesSegments::class)->findAll();
        self::assertCount(1, $resultCompaniesSegmentsBefore);

        $filtersToCompanySegment  = [
            'filters' => [
                'glue'       => 'and',
                'operator'   => 'in',
                'properties' => [
                    'filter' => [$companySegmentOne->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];

        // Company Segment 2 filters for companies that are in Company Segment 1 (Globo should match after update)
        $companySegmentTwo = $this->createCompanySegment('Test Company Segment 2', 'test_comp_segment2', true, $filtersToCompanySegment);

        $leadSegmentOne = $this->createLeadSegment('Test Segment 1', 'test_segment');

        $filtersToLeadSegment = [
            [
                'glue'       => 'and',
                'operator'   => '!=',
                'properties' => [
                    'filter' => 'asdasdaadasd',
                ],
                'field'  => 'address1',
                'type'   => 'text',
                'object' => 'lead',
            ],
            [
                'glue'       => 'and',
                'operator'   => '!in',
                'properties' => [
                    'filter' => [$companySegmentTwo->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];

        $leadSegmentTwo = $this->createLeadSegment('Test Segment 2', 'test_segment2', true, $filtersToLeadSegment);

        $leadListModel = static::getContainer()->get('mautic.lead.model.list');
        assert($leadListModel instanceof \Mautic\LeadBundle\Model\ListModel);

        // Before running the segment update command, no leads should be in the segment yet
        $leadListTotalBefore = $leadListModel->getListLeadRepository()->findAll();
        self::assertCount(0, $leadListTotalBefore);

        // Run ABM company segment update command
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:abm:segments-update');
        $commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('1 total company(es) to be added', $commandTester->getDisplay());

        $resultCompaniesSegmentsAfter = $this->em->getRepository(CompaniesSegments::class)->findAll();

        // globo was added now in second company segment
        self::assertCount(2, $resultCompaniesSegmentsAfter);

        // Run Mautic contact segment update command
        $commandTester = $this->testSymfonyCommand('mautic:segments:update');
        $commandTester->assertCommandIsSuccessful();

        self::assertStringContainsString('2 total contact(s) to be added', $commandTester->getDisplay());

        $leadListTotalAfter = $leadListModel->getListLeadRepository()->findAll();
        self::assertCount(2, $leadListTotalAfter);
    }

    /**
     * Tests that a lead segment with an "empty" filter on company_segments correctly
     * includes only leads whose company has no company segments assigned.
     *
     * Setup:
     *   Company Segment 1 (test_comp_segment) : Manually contains Globo
     *   Lead Segment 1 (test_segment)         : Filter = company_segments IS EMPTY
     *
     * Company-Lead links:
     *   +--------+-------+-------------------+
     *   | Company| Leads | Company Segments  |
     *   +--------+-------+-------------------+
     *   | Globo  | 1, 2  | Segment 1         |
     *   | SBT    | 3, 4  | --                |
     *   | Record | --    | --                |
     *   +--------+-------+-------------------+
     *
     * After mautic:segments:update:
     *   Lead Segment 1 should contain leads 3 and 4 (SBT leads).
     *   Leads 1 and 2 are excluded because their company (Globo) has a segment.
     */
    public function testUpdateLeadSegmentWithCompanySegmentEmpty(): void
    {
        $companyGlobo  = $this->addCompany('Globo', 'contact@globo.com');
        $companySbt    = $this->addCompany('SBT', 'contact@sbt.com');
        $companyRecord = $this->addCompany('Record', 'contact@record.com');

        $leadOne   = $this->createLead('John Globo Doe', 'leadone@mautic.com');
        $leadTwo   = $this->createLead('Brian Doe', 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', 'leadthree@mautic.com');
        $leadFour  = $this->createLead('Braw Doe', 'leadfour@mautic.com');

        $this->addLeadToCompany($companyGlobo, $leadOne);
        $this->addLeadToCompany($companyGlobo, $leadTwo);
        $this->addLeadToCompany($companySbt, $leadThree);
        $this->addLeadToCompany($companySbt, $leadFour);

        $totalCompanyLeadsBefore = $this->em->getRepository(CompanyLead::class)->findAll();
        self::assertCount(4, $totalCompanyLeadsBefore);
        $companySegmentOne = $this->createCompanySegment('Test Company Segment 1', 'test_comp_segment');
        $this->addCompanyToSegments($companyGlobo, $companySegmentOne);
        $resultCompaniesSegmentsBefore = $this->em->getRepository(CompaniesSegments::class)->findAll();
        self::assertCount(1, $resultCompaniesSegmentsBefore);
        $filtersToLeadSegment = [
            [
                'glue'       => 'and',
                'operator'   => 'empty',
                'field'      => 'company_segments',
                'type'       => 'company_segments',
                'object'     => 'company_segments',
            ],
        ];
        $leadSegmentOne = $this->createLeadSegment('Test Segment 1', 'test_segment', true, $filtersToLeadSegment);
        $leadListModel  = static::getContainer()->get('mautic.lead.model.list');
        assert($leadListModel instanceof \Mautic\LeadBundle\Model\ListModel);

        // Before running the segment update command, no leads should be in the segment yet
        $leadListTotalBefore = $leadListModel->getListLeadRepository()->findAll();
        self::assertCount(0, $leadListTotalBefore);

        // Run Mautic contact segment update command
        $commandTester = $this->testSymfonyCommand('mautic:segments:update');
        $commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('2 total contact(s) to be added', $commandTester->getDisplay());
        $leadListTotalAfter = $leadListModel->getListLeadRepository()->findAll();
        self::assertCount(2, $leadListTotalAfter);
    }

    /**
     * Tests that a lead segment with a "!empty" filter on company_segments correctly
     * includes all leads whose company has at least one company segment assigned.
     *
     * Setup:
     *   Company Segment globo  : Manually contains Globo
     *   Company Segment sbt    : Manually contains SBT
     *   Company Segment record : Manually contains Record
     *   Lead Segment (test_segment_all_not_empty) : Filter = company_segments IS NOT EMPTY
     *
     * Company-Lead links:
     *   +--------+-------+-------------------+
     *   | Company| Leads | Company Segments  |
     *   +--------+-------+-------------------+
     *   | Globo  | 1     | Segment globo     |
     *   | SBT    | 3, 4  | Segment sbt       |
     *   | Record | --    | Segment record    |
     *   +--------+-------+-------------------+
     *
     * After mautic:segments:update:
     *   Lead Segment should contain leads 1, 3, and 4.
     *   Lead 2 is excluded because it is not linked to any company.
     */
    public function testUpdateLeadSegmentsWithContactsWithAllContactsInAnyCompanySegment(): void
    {
        $companyGlobo  = $this->addCompany('Globo', 'contact@globo.com');
        $companySbt    = $this->addCompany('SBT', 'contact@sbt.com');
        $companyRecord = $this->addCompany('Record', 'contact@record.com');

        $leadOne   = $this->createLead('John Globo Doe', 'leadone@mautic.com');
        $leadTwo   = $this->createLead('Brian Doe', 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', 'leadthree@mautic.com');
        $leadFour  = $this->createLead('Braw Doe', 'leadfour@mautic.com');

        $this->addLeadToCompany($companyGlobo, $leadOne);
        $this->addLeadToCompany($companySbt, $leadThree);
        $this->addLeadToCompany($companySbt, $leadFour);

        $totalCompanyLeadsBefore = $this->em->getRepository(CompanyLead::class)->findAll();
        self::assertCount(3, $totalCompanyLeadsBefore);

        $companySegmentGlobo  = $this->createCompanySegment('Test Company Segment globo', 'test_comp_segment_globo');
        $companySegmentSbt    = $this->createCompanySegment('Test Company Segment Sbt', 'test_comp_segment_sbt');
        $companySegmentRecord = $this->createCompanySegment('Test Company Segment Record', 'test_comp_segment_record');

        // Add companies to their respective segments
        $this->addCompanyToSegments($companyGlobo, $companySegmentGlobo);
        $this->addCompanyToSegments($companySbt, $companySegmentSbt);
        $this->addCompanyToSegments($companyRecord, $companySegmentRecord);

        $filtersToLeadSegment = [
            [
                'glue'       => 'and',
                'operator'   => '!empty',
                'field'      => 'company_segments',
                'type'       => 'company_segments',
                'object'     => 'company_segments',
            ],
        ];

        $leadSegmentTwo = $this->createLeadSegment('Test Segment all not empty', 'test_segment_all_not_empty', true, $filtersToLeadSegment);

        // Run Mautic contact segment update command
        $commandTester = $this->testSymfonyCommand('mautic:segments:update');
        $commandTester->assertCommandIsSuccessful();

        self::assertStringContainsString('3 total contact(s) to be added', $commandTester->getDisplay());

        $leadListModel = static::getContainer()->get('mautic.lead.model.list');
        assert($leadListModel instanceof \Mautic\LeadBundle\Model\ListModel);
        $leadListTotalAfter = $leadListModel->getListLeadRepository()->findAll();
        self::assertCount(3, $leadListTotalAfter);
    }

    /**
     * Tests that company segments can be filtered by contact segment membership.
     *
     * Setup:
     *   Lead Segment 1 (segment_1) : Contains contactWithSegment1
     *   Lead Segment 2 (segment_2) : Contains contactWithSegment2
     *
     * Company-Contact links:
     *   +--------------------+---------+------------------+
     *   | Company            | Contact | Lead Segments    |
     *   +--------------------+---------+------------------+
     *   | noleadsegment      | 1       | --               |
     *   | leadsegment1       | 2       | Segment 1        |
     *   | leadsegment2       | 3       | Segment 2        |
     *   | companywithoutlead | --      | --               |
     *   +--------------------+---------+------------------+
     *
     * Company Segments and their filters:
     *   +---------------------------+------------------------------------------+
     *   | Company Segment           | Filter                                   |
     *   +---------------------------+------------------------------------------+
     *   | Lead List 1 Segment Filter| contactsegmentmembership IN [Segment 1]  |
     *   | Lead List 2 Segment Filter| contactsegmentmembership IN [Segment 2]  |
     *   | Empty Lead Segments       | contactsegmentmembership IS EMPTY        |
     *   | Not Empty Lead Segments   | contactsegmentmembership IS NOT EMPTY    |
     *   +---------------------------+------------------------------------------+
     *
     * After leuchtfeuer:abm:segments-update:
     *   | Lead List 1 Segment Filter | -> leadsegment1                      |
     *   | Lead List 2 Segment Filter | -> leadsegment2                      |
     *   | Empty Lead Segments        | -> noleadsegment, companywithoutlead |
     *   | Not Empty Lead Segments    | -> leadsegment1, leadsegment2        |
     */
    public function testUpdateCompanySegmentsWithLeadListFilter(): void
    {
        $companyWithLeadWithoutSegment  = $this->addCompany('noleadsegment', 'contact@globo.com');
        $companyWithLeadWithSegment1    = $this->addCompany('leadsegment1', 'contact@sbt.com');
        $companyWithLeadWithSegment2    = $this->addCompany('leadsegment2', 'contact@record.com');
        $companyWithoutLead             = $this->addCompany('companywithoutlead', 'companywithout@lead.com');

        $contactWithoutSegment   = $this->createLead('Nosegment', 'leadone@mautic.com');
        $contactWithSegment1     = $this->createLead('Segment1', 'leadtwo@mautic.com');
        $contactWithSegment2     = $this->createLead('Segment2', 'leadthree@mautic.com');

        $leadSegment1 = $this->createLeadSegment('Segment 1', 'segment_1');
        $leadSegment2 = $this->createLeadSegment('Segment 2', 'segment_2');

        $this->addLeadToSegment($contactWithSegment1, $leadSegment1);
        $this->addLeadToSegment($contactWithSegment2, $leadSegment2);

        $this->addLeadToCompany($companyWithLeadWithoutSegment, $contactWithoutSegment);
        $this->addLeadToCompany($companyWithLeadWithSegment1, $contactWithSegment1);
        $this->addLeadToCompany($companyWithLeadWithSegment2, $contactWithSegment2);

        $filterSegment1              = [
            'filters' => [
                'glue'       => 'and',
                'operator'   => 'in',
                'properties' => [
                    'filter' => [$leadSegment1->getId()],
                ],
                'field'  => 'contactsegmentmembership',
                'type'   => 'leadlist',
                'object' => 'any_companycontact',
            ],
        ];
        $filterSegment2              = [
            'filters' => [
                'glue'       => 'and',
                'operator'   => 'in',
                'properties' => [
                    'filter' => [$leadSegment2->getId()],
                ],
                'field'  => 'contactsegmentmembership',
                'type'   => 'leadlist',
                'object' => 'any_companycontact',
            ],
        ];
        $filterEmptySegment           = [
            'filters' => [
                'glue'       => 'and',
                'operator'   => 'empty',
                'properties' => [
                    'filter' => null,
                ],
                'field'  => 'contactsegmentmembership',
                'type'   => 'leadlist',
                'object' => 'any_companycontact',
            ],
        ];
        $filterNotEmptySegment              = [
            'filters' => [
                'glue'       => 'and',
                'operator'   => '!empty',
                'properties' => [
                    'filter' => null,
                ],
                'field'  => 'contactsegmentmembership',
                'type'   => 'leadlist',
                'object' => 'any_companycontact',
            ],
        ];
        $companySegmentLeadList1        = $this->createCompanySegment('Lead List 1 Segment Filter', 'lead_list_1_segment_filter', true, $filterSegment1);
        $companySegmentLeadList2        = $this->createCompanySegment('Lead List 2 Segment Filter', 'lead_list_2_segment_filter', true, $filterSegment2);
        $companySegmentEmptyLeadList    = $this->createCompanySegment('Empty Lead Segments', 'empty_lead_segments', true, $filterEmptySegment);
        $companySegmentNotEmptyLeadList = $this->createCompanySegment('Not Empty Lead Segments', 'not_empty_lead_segments', true, $filterNotEmptySegment);

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:abm:segments-update');
        $commandTester->assertCommandIsSuccessful();

        $companiesInSegment1 = $this->em->getRepository(CompaniesSegments::class)
    ->findBy(['companySegment' => $companySegmentLeadList1]);
        self::assertCount(1, $companiesInSegment1);
        self::assertEquals('leadsegment1', $companiesInSegment1[0]->getCompany()->getName());

        $companiesInSegment2 = $this->em->getRepository(CompaniesSegments::class)
            ->findBy(['companySegment' => $companySegmentLeadList2]);
        self::assertCount(1, $companiesInSegment2);
        self::assertEquals('leadsegment2', $companiesInSegment2[0]->getCompany()->getName());

        $companiesInEmptySegment = $this->em->getRepository(CompaniesSegments::class)
            ->findBy(['companySegment' => $companySegmentEmptyLeadList]);
        $companyNames = array_map(fn ($cs) => $cs->getCompany()->getName(), $companiesInEmptySegment);
        self::assertCount(2, $companiesInEmptySegment);
        self::assertContains('noleadsegment', $companyNames);
        self::assertContains('companywithoutlead', $companyNames);

        $companiesInNotEmptySegment = $this->em->getRepository(CompaniesSegments::class)
            ->findBy(['companySegment' => $companySegmentNotEmptyLeadList]);
        self::assertCount(2, $companiesInNotEmptySegment);
        $companyNames = array_map(fn ($cs) => $cs->getCompany()->getName(), $companiesInNotEmptySegment);
        self::assertContains('leadsegment1', $companyNames);
        self::assertContains('leadsegment2', $companyNames);
    }

    private function createLead(string $name, string $email, ?Company $companyName = null): Lead
    {
        $lead = new Lead();
        $lead->setFirstname($name);
        $lead->setLastname($name.' lastname');
        $lead->setEmail($email);
        if (null !== $companyName) {
            $lead->setCompany($companyName);
        }
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }

    /**
     * @param array<mixed> $filters
     */
    private function createLeadSegment(string $name, string $alias, bool $isPublished = true, array $filters = []): LeadList
    {
        $leadList = new LeadList();
        $leadList->setPublicName($name);
        $leadList->setName($name);
        $leadList->setAlias($alias);
        $leadList->setIsPublished($isPublished);
        if ([] !== $filters) {
            $leadList->setFilters($filters);
        }
        $this->em->persist($leadList);
        $this->em->flush();

        return $leadList;
    }

    /**
     * @param array<array<mixed>> $filters
     */
    private function createCompanySegment(string $name, string $alias, bool $isPublished = true, array $filters = []): CompanySegment
    {
        $companySegment = new CompanySegment();
        $companySegment->setName($name);
        $companySegment->setAlias($alias);
        $companySegment->setIsPublished($isPublished);
        if ([] !== $filters) {
            $companySegment->setFilters($filters);
        }
        $this->em->persist($companySegment);
        $this->em->flush();

        return $companySegment;
    }

    private function addCompany(string $name, string $email): Company
    {
        $company = new Company();
        $company->setName($name);
        $company->setEmail($email);
        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }

    private function addCompanyToSegments(Company $company, CompanySegment $companySegment): CompaniesSegments
    {
        $companiesSegments = new CompaniesSegments();
        $companiesSegments->setCompany($company);
        $companiesSegments->setCompanySegment($companySegment);
        $companiesSegments->setDateAdded(new \DateTime());
        $this->em->persist($companiesSegments);
        $this->em->flush();

        return $companiesSegments;
    }

    private function addLeadToSegment(Lead $lead, LeadList $segment): void
    {
        $listLead = new ListLead();
        $listLead->setLead($lead);
        $listLead->setList($segment);
        $listLead->setDateAdded(new \DateTime());
        $listLead->setManuallyAdded(true);
        $listLead->setManuallyRemoved(false);
        $this->em->persist($listLead);
        $this->em->flush();
    }

    private function addLeadToCompany(Company $company, Lead $lead, bool $isPrimary = true): CompanyLead
    {
        $companyLead = new CompanyLead();
        $companyLead->setCompany($company);
        $companyLead->setLead($lead);
        $companyLead->setPrimary($isPrimary);
        $companyLead->setDateAdded(new \DateTime());
        $this->em->persist($companyLead);
        $this->em->flush();

        return $companyLead;
    }
}

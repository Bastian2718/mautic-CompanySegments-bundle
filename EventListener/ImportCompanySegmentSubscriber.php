<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\Event\ImportEvent;
use Mautic\LeadBundle\Event\ImportProcessEvent;
use Mautic\LeadBundle\Event\ImportValidateEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\ImportModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class ImportCompanySegmentSubscriber implements EventSubscriberInterface
{
    /**
     * @var int|null Tracks the active import ID to determine if we're in import context
     */
    private ?int $activeImportId = null;

    public function __construct(
        private CompanySegmentModel $companySegmentModel,
        private ImportModel $importModel,
        private RequestStack $requestStack,
        private Config $config,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::IMPORT_ON_VALIDATE      => ['onValidateImport', 10],
            LeadEvents::IMPORT_POST_SAVE        => ['onImportPostSave', 0],
            LeadEvents::IMPORT_ON_PROCESS       => ['onImportProcess', 10],
            LeadEvents::COMPANY_POST_SAVE       => ['onCompanyPostSave', -10],
        ];
    }

    public function onValidateImport(ImportValidateEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        if (!$event->importIsForRouteObject('companies')) {
            return;
        }

        $form = $event->getForm();
        if (!$form->has('company_segments')) {
            return;
        }

        $companySegments = $form->get('company_segments')->getData();
        if (!$companySegments || !is_array($companySegments)) {
            return;
        }

        $segmentIds = array_map(
            fn ($segment) => $segment instanceof CompanySegment ? $segment->getId() : (int) $segment,
            $companySegments
        );

        $request = $this->requestStack->getCurrentRequest();
        if ($request?->hasSession()) {
            $request->getSession()->set('mautic.company.import.segments.temp', $segmentIds);
        }
    }

    public function onImportPostSave(ImportEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $import = $event->getEntity();
        if ('company' !== $import->getObject()) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request?->hasSession()) {
            return;
        }

        $session  = $request->getSession();
        $segments = $session->get('mautic.company.import.segments.temp');

        if (!$segments || !is_array($segments)) {
            return;
        }

        $session->remove('mautic.company.import.segments.temp');

        $import->setDefault('company_segments', $segments);
        $this->importModel->saveEntity($import, false);
    }

    public function onImportProcess(ImportProcessEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        if ($event->importIsForObject('company')) {
            $this->activeImportId = $event->import->getId();
        }
    }

    public function onCompanyPostSave(CompanyEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        if (null === $this->activeImportId) {
            return;
        }

        $company = $event->getCompany();
        if (!$company instanceof Company || !$company->getId()) {
            return;
        }

        $import = $this->importModel->getEntity($this->activeImportId);
        if (!$import || 'company' !== $import->getObject()) {
            return;
        }

        $segmentIds = $import->getDefault('company_segments');
        if (empty($segmentIds) || !is_array($segmentIds)) {
            return;
        }

        $this->companySegmentModel->addCompany($company, $segmentIds, true);
    }
}

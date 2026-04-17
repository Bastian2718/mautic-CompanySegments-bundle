<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type;

use Doctrine\DBAL\Connection;
use Mautic\DynamicContentBundle\Form\Type\DwcEntryFiltersType;
use Mautic\LeadBundle\Entity\RegexTrait;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Decorates DwcEntryFiltersType to add company_segments support.
 *
 * Strategy: Pre-process company_segments → text, let parent create basic field, then replace with ChoiceType.
 */
class DwcEntryFiltersTypeDecorator extends DwcEntryFiltersType
{
    use RegexTrait;

    private TranslatorInterface $translatorLocal;
    private array $companySegmentChoices;

    public function __construct(
        TranslatorInterface $translator,
        ListModel $listModel,
        private CompanySegmentModel $companySegmentModel,
        ?Connection $connection = null
    ) {
        parent::__construct($translator, $listModel);
        $this->translatorLocal = $translator;
        $this->companySegmentChoices = $this->getCompanySegmentChoices();

        if ($connection) {
            $this->setConnection($connection);
        }
    }

    public function getBlockPrefix(): string
    {
        return 'dwc_entry_filters';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('companySegments', $this->companySegmentChoices);
        $resolver->setDefined('companySegments');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'glue',
            ChoiceType::class,
            [
                'label'   => false,
                'choices' => [
                    'mautic.lead.list.form.glue.and' => 'and',
                    'mautic.lead.list.form.glue.or'  => 'or',
                ],
                'attr' => [
                    'class'    => 'form-control not-chosen glue-select',
                    'onchange' => 'Mautic.updateFilterPositioning(this)',
                ],
            ]
        );

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($options): void {
                $this->preProcessCompanySegments($event, $options);
            },
            10
        );

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($options): void {
                $this->preProcessCompanySegments($event, $options);
            },
            10
        );

        $formModifier = function (FormEvent $event, $eventName): void {
            $this->buildFiltersForm($eventName, $event, $this->translatorLocal);
        };

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($formModifier, $options): void {
                $formModifier($event, FormEvents::PRE_SET_DATA);
                $this->postProcessCompanySegments($event, $options);
            }
        );

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($formModifier, $options): void {
                $formModifier($event, FormEvents::PRE_SUBMIT);
                $this->postProcessCompanySegments($event, $options);
            }
        );

        $builder->add('field', HiddenType::class);
        $builder->add('object', HiddenType::class);
        $builder->add('type', HiddenType::class);
    }

    private function preProcessCompanySegments(FormEvent $event, array $options): void
    {
        $data = $event->getData();

        if (!isset($data['type']) || $data['type'] !== 'company_segments') {
            return;
        }

        $data['__original_type'] = 'company_segments';
        $data['__original_operator'] = $data['operator'] ?? null;
        $data['type'] = 'text';

        if (!isset($data['filter'])) {
            $data['filter'] = [];
        } elseif (!is_array($data['filter'])) {
            $data['filter'] = [$data['filter']];
        }

        $event->setData($data);
    }

    private function postProcessCompanySegments(FormEvent $event, array $options): void
    {
        $data = $event->getData();

        if (!isset($data['__original_type']) || $data['__original_type'] !== 'company_segments') {
            return;
        }

        $form = $event->getForm();

        if ($form->has('filter')) {
            $form->remove('filter');
        }

        $operator = $data['__original_operator'] ?? $data['operator'] ?? '';
        $multiple = in_array($operator, ['in', '!in']);

        $form->add(
            'filter',
            ChoiceType::class,
            [
                'label'                     => false,
                'attr'                      => ['class' => 'form-control filter-value'],
                'data'                      => $data['filter'] ?? ($multiple ? [] : ''),
                'choices'                   => $options['companySegments'],
                'multiple'                  => $multiple,
                'choice_translation_domain' => false,
                'error_bubbling'            => false,
            ]
        );

        $data['type'] = 'company_segments';
        unset($data['__original_type'], $data['__original_operator']);
        $event->setData($data);
    }

    /**
     * @return array<string, int>
     */
    private function getCompanySegmentChoices(): array
    {
        $items = $this->companySegmentModel->getCompanySegments();
        $choices = [];

        foreach ($items as $item) {
            $choices[$item['name']] = $item['id'];
        }

        return $choices;
    }
}


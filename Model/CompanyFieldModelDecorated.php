<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Mautic\CoreBundle\Cache\ResultCacheOptions;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Model\FieldModel;

class CompanyFieldModelDecorated extends FieldModel
{
    /**
     * @param string $object
     *
     * @return array<array<string, mixed>>
     */
    public function getPublishedFieldArrays($object = 'lead'): array
    {
        $entities = $this->getEntities(
            [
                'filter' => [
                    'force' => [
                        [
                            'column' => 'f.isPublished',
                            'expr'   => 'eq',
                            'value'  => true,
                        ],
                        [
                            'column' => 'f.object',
                            'expr'   => 'eq',
                            'value'  => $object,
                        ],
                    ],
                ],
                'hydration_mode' => 'HYDRATE_ARRAY',
                'result_cache'   => new ResultCacheOptions(LeadField::CACHE_NAMESPACE),
            ]
        );

        /** @var array<array<string, mixed>> $rows */
        $rows = [];
        if ($entities instanceof Paginator) {
            $rows = iterator_to_array($entities->getIterator(), true); // preserve keys
        } elseif (is_array($entities)) {
            $rows = $entities;
        }

        foreach ($rows as $k => $row) {
            if (
                is_array($row)
                && array_key_exists('properties', $row)
                && is_array($row['properties'])
                && array_key_exists('list', $row['properties'])
                && is_array($row['properties']['list'])
                && [] !== $row['properties']['list']
            ) {
                uasort(
                    $row['properties']['list'],
                    function ($a, $b): int {
                        $va = '';
                        $vb = '';

                        if (is_array($a) && array_key_exists('value', $a) && is_string($a['value'])) {
                            $va = $a['value'];
                        }

                        if (is_array($b) && array_key_exists('value', $b) && is_string($b['value'])) {
                            $vb = $b['value'];
                        }

                        return strcasecmp($va, $vb);
                    }
                );
                $row['properties']['list'] = array_values($row['properties']['list']);
                $rows[$k]                  = $row;
            }
        }

        return $rows;
    }
}

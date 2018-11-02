<?php

namespace Larastic\Tests\Stubs;

use Larastic\SearchRule as ElasticSearchRule;

class SearchRule extends ElasticSearchRule
{
    /**
     * @inheritdoc
     */
    public function buildHighlightPayload()
    {
        $highlight = null;

        foreach ($this->builder->select as $field) {
            if (empty($highlight)) {
                $highlight = [
                    'fields' => []
                ];
            }

            $highlight['fields'][$field] = [
                'type' => 'plain'
            ];
        }

        return $highlight;
    }
}